<?php
session_start();

// Vérification de l'authentification et du rôle admin
if (!isset($_SESSION['utilisateur']) || $_SESSION['utilisateur']['role'] !== 'admin') {
    header("Location: connection.php");
    exit();
}

require_once 'include/database.php';

// Requêtes des statistiques intégrées
$nbND = $pdo->query("SELECT COUNT(*) FROM all_flotte")->fetchColumn();
$nbUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$nbEtab = $pdo->query("SELECT COUNT(*) FROM z_etab")->fetchColumn();
$nbSuspendus = $pdo->query("
    SELECT COUNT(nd)
    FROM all_flotte
    WHERE id_statut = (
        SELECT id_statut FROM r_statuts WHERE libelle = 'suspendu' LIMIT 1
    )
")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #6c63ff;
            --primary-light: #a29bfe;
            --secondary-color: #f8f9fa;
            --text-color: #4a4a4a;
            --soft-shadow: 0 4px 20px rgba(108, 99, 255, 0.15);
        }

        body {
            background-color: #fafafa;
            color: var(--text-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        h1 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 2rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-light);
        }
    </style>
</head>
<body>
    <?php include 'include/nav.php'; ?>

    <div class="container py-5 dashboard-container">
        <h1>Tableau de bord - Administrateur</h1>

        <div class="mb-5">
            <canvas id="statsChart" height="100"></canvas>
        </div>

        <div class="row row-cols-1 row-cols-md-3 g-4 mt-4">
            <div class="col">
                <div class="card h-100 shadow-sm text-center">
                    <div class="card-body">
                        <i class="bi bi-people-fill fs-2 text-primary mb-3"></i>
                        <h5 class="card-title">Affectations</h5>
                        <p class="card-text">Gérer les affectations des utilisateurs.</p>
                        <a href="affectation_form.php" class="btn btn-outline-primary">Accéder</a>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card h-100 shadow-sm text-center">
                    <div class="card-body">
                        <i class="bi bi-phone-fill fs-2 text-success mb-3"></i>
                        <h5 class="card-title">Numéros</h5>
                        <p class="card-text">Voir tous les ND et leur état.</p>
                        <a href="flotte.php" class="btn btn-outline-success">Accéder</a>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card h-100 shadow-sm text-center">
                    <div class="card-body">
                        <i class="bi bi-person-fill fs-2 text-info mb-3"></i>
                        <h5 class="card-title">Agents</h5>
                        <p class="card-text">Ajouter et modifier des agents.</p>
                        <a href="ajouter_agent.php" class="btn btn-outline-info">Accéder</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Carte Historique centrée -->
        <div class="row justify-content-center mt-4">
            <div class="col-md-4">
                <div class="card h-100 shadow-sm text-center">
                    <div class="card-body">
                        <i class="bi bi-clock-history fs-2 text-warning mb-3"></i>
                        <h5 class="card-title">Historique</h5>
                        <p class="card-text">Suivi des affectations</p>
                        <a href="historique.php" class="btn btn-outline-warning">Accèder</a>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const ctx = document.getElementById('statsChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['ND actifs', 'Utilisateurs', 'Etablissements', 'ND suspendus'],
                datasets: [{
                    label: 'Statistiques globales',
                    data: [<?= $nbND ?>, <?= $nbUsers ?>, <?= $nbEtab ?>, <?= $nbSuspendus ?>],
                    backgroundColor: ['#6c63ff', '#28a745', '#17a2b8', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    });
    </script>
</body>
</html>
