<?php
session_start();
require_once 'include/database.php';

// Vérification de l'authentification et des permissions
if (!isset($_SESSION['utilisateur']) || !in_array($_SESSION['utilisateur']['role'], ['admin', 'moderator'])) {
    header("Location: connection.php");
    exit();
}

// Traitement ajout établissement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Ajout établissement
    if ($_POST['action'] === 'ajouter') {
        try {
            $pdo->beginTransaction();
            $nom = trim($_POST['nom_etabl'] ?? '');
            $ville = trim($_POST['la_ville'] ?? '');
            $type = trim($_POST['typeEtab'] ?? '');
            if (empty($nom) || empty($ville) || empty($type)) {
                throw new Exception("Tous les champs sont obligatoires");
            }
            $code = generateEtabCode($nom, $ville);
            $stmt = $pdo->prepare("INSERT INTO z_etab (CD_ETAB, NOM_ETABL, LA_VILLE, typeEtab, Actif, DateModification) VALUES (?, ?, ?, ?, 1, NOW())");
            $stmt->execute([$code, $nom, $ville, $type]);
            $pdo->commit();
            $_SESSION['success'] = "Établissement ajouté avec succès";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Erreur : " . $e->getMessage();
        }
        header("Location: etablissement_liste.php");
        exit();
    }
    // Modification établissement
    if ($_POST['action'] === 'modifier' && isset($_POST['code_etab'])) {
        try {
            $pdo->beginTransaction();
            $code = $_POST['code_etab'];
            $nom = trim($_POST['nom_etabl_modif'] ?? '');
            $ville = trim($_POST['la_ville_modif'] ?? '');
            $type = trim($_POST['typeEtab_modif'] ?? '');
            if (empty($nom) || empty($ville) || empty($type)) {
                throw new Exception("Tous les champs sont obligatoires");
            }
            $stmt = $pdo->prepare("UPDATE z_etab SET NOM_ETABL = ?, LA_VILLE = ?, typeEtab = ?, DateModification = NOW() WHERE CD_ETAB = ?");
            $stmt->execute([$nom, $ville, $type, $code]);
            $pdo->commit();
            $_SESSION['success'] = "Établissement modifié avec succès";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Erreur : " . $e->getMessage();
        }
        header("Location: etablissement_liste.php");
        exit();
    }
    // Toggle statut via AJAX
    if ($_POST['action'] === 'toggle_status' && isset($_POST['id'], $_POST['status'])) {
        $id = $_POST['id'];
        $status = $_POST['status'] == '1' ? 1 : 0;
        $stmt = $pdo->prepare("UPDATE z_etab SET Actif = ? WHERE CD_ETAB = ?");
        $success = $stmt->execute([$status, $id]);
        header('Content-Type: application/json');
        echo json_encode(['success' => $success]);
        exit();
    }
}

// Récupérer les établissements avec pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Requête avec pagination et comptage
$total = $pdo->query("SELECT COUNT(*) FROM z_etab")->fetchColumn();
$etablissements = $pdo->prepare("SELECT * FROM z_etab ORDER BY NOM_ETABL LIMIT :limit OFFSET :offset");
$etablissements->bindValue(':limit', $perPage, PDO::PARAM_INT);
$etablissements->bindValue(':offset', $offset, PDO::PARAM_INT);
$etablissements->execute();
$etablissements = $etablissements->fetchAll();

// Fonction pour générer un code établissement
function generateEtabCode($nom, $ville) {
    $prefix = substr(strtoupper($ville), 0, 3);
    $suffix = substr(strtoupper(preg_replace('/[^A-Z]/', '', $nom)), 0, 3);
    return $prefix . $suffix . rand(100, 999);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des établissements</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css">
    <style>
        :root {
            --primary-color: #6c63ff;
            --primary-light: #a29bfe;
            --secondary-color: #f8f9fa;
            --text-color: #4a4a4a;
        }
        
        body {
            background-color: #f8fafc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-color);
        }
        
        .card {
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: none;
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(108,99,255,0.15);
        }
        
        .table-responsive {
            border-radius: 12px;
            overflow: hidden;
        }
        
        .table thead th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            border: none;
            padding: 1rem;
        }
        
        .table tbody tr {
            transition: background-color 0.2s;
        }
        
        .table tbody tr:hover {
            background-color: rgba(108,99,255,0.05);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .btn-primary:hover {
            background-color: #5a52d4;
        }
        
        .page-title {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 1.5rem;
            position: relative;
            padding-bottom: 0.5rem;
        }
        
        .page-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 3px;
            background-color: var(--primary-light);
        }
        
        .floating-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 20px rgba(108,99,255,0.3);
            transition: all 0.3s ease;
            z-index: 1000;
        }
        
        .floating-btn:hover {
            transform: scale(1.1);
            background-color: #5a52d4;
            color: white;
        }
        
        .search-box {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .search-box input {
            padding-left: 2.5rem;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        
        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .status-badge {
            padding: 0.35rem 0.65rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-active {
            background-color: rgba(40,167,69,0.1);
            color: #28a745;
        }
        
        .status-inactive {
            background-color: rgba(108,117,125,0.1);
            color: #6c757d;
        }
    </style>
</head>
<body>
<?php include 'include/nav.php'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title">
            <i class="bi bi-building me-2"></i>Gestion des établissements
        </h1>
        
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addEtabModal">
                <i class="bi bi-plus-lg me-1"></i> Ajouter
            </button>
            <a href="export_etablissements.php" class="btn btn-outline-secondary">
                <i class="bi bi-download me-1"></i> Exporter
            </a>
        </div>
    </div>

    <!-- Barre de recherche et filtres -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" id="searchInput" class="form-control" placeholder="Rechercher un établissement...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filterType">
                        <option value="">Tous les types</option>
                        <option value="ECOLE">École</option>
                        <option value="COLLEGE">Collège</option>
                        <option value="LYCEE">Lycée</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filterStatus">
                        <option value="">Tous les statuts</option>
                        <option value="1">Actif</option>
                        <option value="0">Inactif</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Messages d'alerte -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Tableau des établissements -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="etablissementsTable">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Nom</th>
                            <th>Ville</th>
                            <th>Type</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($etablissements as $etab): ?>
                            <tr>
                                <td><?= htmlspecialchars($etab['CD_ETAB']) ?></td>
                                <td><?= htmlspecialchars($etab['NOM_ETABL']) ?></td>
                                <td><?= htmlspecialchars($etab['LA_VILLE'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($etab['typeEtab'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="status-badge <?= $etab['Actif'] ? 'status-active' : 'status-inactive' ?>">
                                        <?= $etab['Actif'] ? 'Actif' : 'Inactif' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editEtabModal"
                                                data-code="<?= htmlspecialchars($etab['CD_ETAB']) ?>"
                                                data-nom="<?= htmlspecialchars($etab['NOM_ETABL']) ?>"
                                                data-ville="<?= htmlspecialchars($etab['LA_VILLE'] ?? '') ?>"
                                                data-type="<?= htmlspecialchars($etab['typeEtab'] ?? '') ?>"
                                                title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger toggle-status" 
                                                data-id="<?= htmlspecialchars($etab['CD_ETAB']) ?>"
                                                data-status="<?= $etab['Actif'] ?>"
                                                data-bs-toggle="tooltip"
                                                title="<?= $etab['Actif'] ? 'Désactiver' : 'Activer' ?>">
                                            <i class="bi bi-power"></i>
                                        </button>
                                        <a href="supprimer_etablissement.php?id=<?= urlencode($etab['CD_ETAB']) ?>" 
                                           class="btn btn-sm btn-outline-danger" 
                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet établissement ?');"
                                           data-bs-toggle="tooltip"
                                           title="Supprimer">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total > $perPage): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page - 1 ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= ceil($total / $perPage); $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < ceil($total / $perPage)): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page + 1 ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<!-- Modal d'ajout -->
</div>

<!-- Modal de modification -->
<div class="modal fade" id="editEtabModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="modifier">
                <input type="hidden" name="code_etab" id="editCodeEtab">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier l'établissement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nom de l'établissement</label>
                        <input type="text" name="nom_etabl_modif" id="editNomEtab" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ville</label>
                        <input type="text" name="la_ville_modif" id="editVilleEtab" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="typeEtab_modif" id="editTypeEtab" class="form-select" required>
                            <option value="">Sélectionner un type</option>
                            <option value="ECOLE">École</option>
                            <option value="COLLEGE">Collège</option>
                            <option value="LYCEE">Lycée</option>
                            <option value="UNIVERSITE">Université</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="addEtabModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="ajouter">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter un établissement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nom de l'établissement</label>
                        <input type="text" name="nom_etabl" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ville</label>
                        <input type="text" name="la_ville" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="typeEtab" class="form-select" required>
                            <option value="">Sélectionner un type</option>
                            <option value="ECOLE">École</option>
                            <option value="COLLEGE">Collège</option>
                            <option value="LYCEE">Lycée</option>
                            <option value="UNIVERSITE">Université</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Recherche en temps réel
        document.getElementById('searchInput').addEventListener('keyup', function() {
            var input = this.value.toLowerCase();
            var rows = document.querySelectorAll('#etablissementsTable tbody tr');
            rows.forEach(function(row) {
                var text = row.textContent.toLowerCase();
                row.style.display = text.includes(input) ? '' : 'none';
            });
        });

        // Filtres
        document.getElementById('filterType').addEventListener('change', filterTable);
        document.getElementById('filterStatus').addEventListener('change', filterTable);
        function filterTable() {
            var type = document.getElementById('filterType').value;
            var status = document.getElementById('filterStatus').value;
            var rows = document.querySelectorAll('#etablissementsTable tbody tr');
            rows.forEach(function(row) {
                var rowType = row.cells[3].textContent;
                var rowStatus = row.cells[4].querySelector('.status-badge').textContent.trim();
                var typeMatch = !type || rowType.includes(type);
                var statusMatch = !status || 
                    (status === '1' && rowStatus === 'Actif') || 
                    (status === '0' && rowStatus === 'Inactif');
                row.style.display = typeMatch && statusMatch ? '' : 'none';
            });
        }

        // Toggle statut
        document.querySelectorAll('.toggle-status').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const currentStatus = this.getAttribute('data-status');
                const newStatus = currentStatus === '1' ? '0' : '1';
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=toggle_status&id=${id}&status=${newStatus}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        Swal.fire('Erreur', data.message || 'Une erreur est survenue', 'error');
                    }
                });
            });
        });

        // Pré-remplir le modal de modification
        var editModal = document.getElementById('editEtabModal');
        editModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var code = button.getAttribute('data-code');
            var nom = button.getAttribute('data-nom');
            var ville = button.getAttribute('data-ville');
            var type = button.getAttribute('data-type');
            document.getElementById('editCodeEtab').value = code;
            document.getElementById('editNomEtab').value = nom;
            document.getElementById('editVilleEtab').value = ville;
            document.getElementById('editTypeEtab').value = type;
        });
    });
</script>
</body>
</html>