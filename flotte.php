<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// Vérification de l'authentification
if (!isset($_SESSION['utilisateur']) || $_SESSION['utilisateur']['role'] !== 'admin') {
    header("Location: connection.php");
    exit();
}

require_once 'include/database.php';

// Initialisation des variables
$search = $_GET['search'] ?? '';
$statut_id = $_GET['statut'] ?? '';
$operateur_id = $_GET['operateur'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;
$total = 0;
$numeros = [];
$error = null;
$statuts = [];
$operateurs = [];

try {
    
    // Requête principale
  $query = "SELECT 
            f.nd, 
            s.libelle AS statut, 
            o.libelle AS operateur,
            ta.libelle AS type_abonnement,
            d.libelle_fr AS direction,
            e.NOM_ETABL AS etablissement,
            a.ppr, 
            a.nom, 
            a.prenom
          FROM all_flotte f
          JOIN r_statuts s ON f.id_statut = s.id_statut
          JOIN r_operateurs o ON f.id_operateur = o.id_operateur
          JOIN r_type_abonnement ta ON f.id_type_abonnement = ta.id_type_abonnement
          JOIN z_direction d ON f.CD_PROV = d.CD_PROV
          JOIN z_etab e ON CONVERT(f.CD_ETAB USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(e.CD_ETAB USING utf8mb4) COLLATE utf8mb4_unicode_ci
          LEFT JOIN affectation_flotte af ON f.nd = af.nd
          LEFT JOIN tb_agents a ON af.ppr = a.ppr
          WHERE 1=1";

    $params = [];
    
    // Filtres
    if (!empty($search)) {
        $query .= " AND (f.nd LIKE :search OR a.nom LIKE :search OR a.prenom LIKE :search OR e.NOM_ETABL LIKE :search)";
        $params[':search'] = "%$search%";
    }
    if (!empty($statut_id)) {
        $query .= " AND f.id_statut = :statut_id";
        $params[':statut_id'] = $statut_id;
    }
    if (!empty($operateur_id)) {
        $query .= " AND f.id_operateur = :operateur_id";
        $params[':operateur_id'] = $operateur_id;
    }

    // Comptage total
    $countQuery = "SELECT COUNT(*) FROM ($query) AS total";
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();

    // Requête paginée
    $query .= " ORDER BY f.nd ASC LIMIT :offset, :perPage";
    $params[':offset'] = ($page - 1) * $perPage;
    $params[':perPage'] = $perPage;

    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $paramType);
    }
    $stmt->execute();
    $numeros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupération des filtres disponibles
    $statuts = $pdo->query("SELECT id_statut AS id, libelle FROM r_statuts ORDER BY libelle")->fetchAll();
    $operateurs = $pdo->query("SELECT id_operateur AS id, libelle AS nom FROM r_operateurs ORDER BY libelle")->fetchAll();


} catch (PDOException $e) {
   $error = "Erreur SQL: " . $e->getMessage();
 // Affiche le détail de l'erreur
    error_log("Erreur SQL dans flotte.php: " . $e->getMessage());
    }

// Fonction pour générer les liens de pagination
function generatePageLink($page, $currentPage, $queryParams) {
    $queryParams['page'] = $page;
    $class = $page == $currentPage ? 'active' : '';
    return [
        'url' => '?' . http_build_query($queryParams),
        'class' => $class,
        'label' => $page
    ];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de la flotte téléphonique</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #6c63ff;
            --primary-light: #a29bfe;
            --text-color: #4a4a4a;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .card {
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .statut-active { color: #28a745; font-weight: 500; }
        .statut-inactive { color: #6c757d; }
        .statut-suspendu { color: #dc3545; font-weight: 500; }
        
        .table th {
            background-color: #f1f3ff;
            color: var(--primary-color);
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .pagination .page-link {
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <?php include 'include/nav.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="bi bi-phone"></i> Gestion de la flotte téléphonique
            </h1>
            <?php if ($_SESSION['utilisateur']['role'] === 'admin'): ?>
                <a href="affectation_form.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Nouvelle affectation
                </a>
            <?php endif; ?>
        </div>

        <!-- Filtres -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Recherche</label>
                        <input type="text" id="search" name="search" class="form-control" 
                               placeholder="Numéro, nom, établissement..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="statut" class="form-label">Statut</label>
                        <select id="statut" name="statut" class="form-select">
                            <option value="">Tous les statuts</option>
                            <?php foreach ($statuts as $statut): ?>
                                <option value="<?= $statut['id'] ?>" <?= $statut_id == $statut['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($statut['libelle']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="operateur" class="form-label">Opérateur</label>
                        <select id="operateur" name="operateur" class="form-select">
                            <option value="">Tous les opérateurs</option>
                            <?php foreach ($operateurs as $operateur): ?>
                               <option value="<?= $operateur['id'] ?>" <?= $operateur_id == $operateur['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($operateur['nom']) ?>
                               </option>

                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel"></i> Filtrer
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Message d'erreur -->
        <?php if ($error): ?>
            <div class="alert alert-danger mb-4"><?= $error ?></div>
        <?php endif; ?>

        <!-- Tableau des résultats -->
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Numéro</th>
                                <th>Statut</th>
                                <th>Opérateur</th>
                                <th>Abonnement</th>
                                <th>Direction</th>
                                <th>Établissement</th>
                                <th>Affecté à</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($numeros)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <div class="text-muted">Aucun numéro trouvé</div>
                                        <?php if (!empty($search) || !empty($statut_id) || !empty($operateur_id)): ?>
                                            <a href="flotte.php" class="btn btn-sm btn-outline-primary mt-2">
                                                Réinitialiser les filtres
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($numeros as $numero): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($numero['nd']) ?></td>
                                        <td>
                                            <?php
                                            $statutClass = match(strtolower($numero['statut'])) {
                                                'actif' => 'statut-active',
                                                'inactif' => 'statut-inactive',
                                                'suspendu' => 'statut-suspendu',
                                                default => ''
                                            };
                                            ?>
                                            <span class="<?= $statutClass ?>">
                                                <?= htmlspecialchars($numero['statut']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($numero['operateur']) ?></td>
                                        <td><?= htmlspecialchars($numero['type_abonnement']) ?></td>
                                        <td><?= htmlspecialchars($numero['direction']) ?></td>
                                        <td><?= htmlspecialchars($numero['etablissement'] ?? 'Non affecté') ?></td>
                                        <td>
                                            <?php if (!empty($numero['ppr'])): ?>
                                                <?= htmlspecialchars($numero['prenom'] . ' ' . $numero['nom']) ?>
                                                <small class="text-muted d-block">PPR: <?= $numero['ppr'] ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">Non affecté</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="historique.php?nd=<?= urlencode($numero['nd']) ?>" 
                                                   class="btn btn-sm btn-outline-secondary" 
                                                   title="Historique"
                                                   data-bs-toggle="tooltip">
                                                    <i class="bi bi-clock-history"></i>
                                                </a>
                                                <?php if ($_SESSION['utilisateur']['role'] === 'admin'): ?>
                                                    <a href="affectation_form.php?nd=<?= urlencode($numero['nd']) ?>" 
                                                       class="btn btn-sm btn-outline-primary" 
                                                       title="Modifier"
                                                       data-bs-toggle="tooltip">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total > $perPage): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <!-- Précédent -->
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>

                    <!-- Première page -->
                    <?php if ($page > 3): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a>
                        </li>
                        <?php if ($page > 4): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Pages autour de la page courante -->
                    <?php 
                    $start = max(1, $page - 2);
                    $end = min(ceil($total / $perPage), $page + 2);
                    
                    for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <!-- Dernière page -->
                    <?php if ($page < ceil($total / $perPage) - 2): ?>
                        <?php if ($page < ceil($total / $perPage) - 3): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => ceil($total / $perPage)])) ?>">
                                <?= ceil($total / $perPage) ?>
                            </a>
                        </li>
                    <?php endif; ?>

                    <!-- Suivant -->
                    <?php if ($page < ceil($total / $perPage)): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Activation des tooltips Bootstrap
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>