<?php
session_start();
require_once 'include/database.php';

// Vérification de l'authentification
if (!isset($_SESSION['utilisateur'])) {
    header("Location: connection.php");
    exit();
}

// Récupération de l'historique avec votre structure de table
$query = $pdo->query("
    SELECT 
        h.id_hf,
        h.ppr,
        h.nom,
        h.prenom,
        h.lib_etab,
        h.lib_fonction,
        h.date_affectation,
        h.date_session,
        h.statut_nd,
        h.CD_PROV,
        z.libelle_fr as direction
    FROM historique_flotte h
    LEFT JOIN z_direction z ON h.CD_PROV = z.CD_PROV
    ORDER BY h.date_affectation DESC, h.date_session DESC
");
$historique = $query->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des affectations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .historique-table th { background-color: #6c63ff; color: white; }
        .badge-statut { font-size: 0.8rem; padding: 5px 8px; }
    </style>
</head>
<body>
    <?php include 'include/nav.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="text-primary">
                <i class="bi bi-clock-history me-2"></i>Historique des affectations
            </h1>
            <div>
                <button class="btn btn-success" onclick="exportToExcel()">
                    <i class="bi bi-file-earmark-excel me-1"></i>Exporter
                </button>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="tableHistorique">
                        <thead>
                            <tr class="historique-table">
                                <th>Date Affectation</th>
                                <th>Date Session</th>
                                <th>Agent</th>
                                <th>Établissement</th>
                                <th>Fonction</th>
                                <th>Direction</th>
                                <th>Statut ND</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historique as $entry): ?>
                            <tr>
                                <td><?= $entry['date_affectation'] ? date('d/m/Y', strtotime($entry['date_affectation'])) : 'N/A' ?></td>
                                <td><?= $entry['date_session'] ? date('d/m/Y', strtotime($entry['date_session'])) : 'N/A' ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($entry['nom']) ?> <?= htmlspecialchars($entry['prenom']) ?></strong>
                                    <div class="text-muted small">PPR: <?= htmlspecialchars($entry['ppr']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($entry['lib_etab']) ?></td>
                                <td><?= htmlspecialchars($entry['lib_fonction']) ?></td>
                                <td><?= htmlspecialchars($entry['direction']) ?></td>
                                <td>
                                    <?php if ($entry['statut_nd']): ?>
                                    <span class="badge rounded-pill badge-statut 
                                        bg-<?= match(strtolower($entry['statut_nd'])) {
                                            'actif' => 'success',
                                            'suspendu' => 'warning',
                                            'inactif' => 'danger',
                                            default => 'secondary'
                                        } ?>">
                                        <?= htmlspecialchars($entry['statut_nd']) ?>
                                    </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.sheetjs.com/xlsx-0.19.3/package/dist/xlsx.full.min.js"></script>
    <script>
    function exportToExcel() {
        const table = document.getElementById('tableHistorique');
        const wb = XLSX.utils.table_to_book(table);
        XLSX.writeFile(wb, 'historique_affectations.xlsx');
    }
    </script>
</body>
</html>