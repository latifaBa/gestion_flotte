<?php
session_start();
require_once 'include/database.php';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ppr = $_POST['ppr'];
    $nom = $_POST['nom'];
    $prenom = $_POST['prenom'];
    $id_fonction = $_POST['id_fonction'];
    $cd_etab = $_POST['cd_etab'];
    $cin = $_POST['cin'];
    $email = $_POST['email'];

    // Vérifier si le PPR existe déjà
    $exists = $pdo->prepare("SELECT COUNT(*) FROM tb_agents WHERE ppr = ?");
    $exists->execute([$ppr]);
    if ($exists->fetchColumn() > 0) {
        $_SESSION['error'] = "Ce PPR existe déjà.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO tb_agents (ppr, nom, prenom, id_fonction, CD_ETAB, cin, email) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$ppr, $nom, $prenom, $id_fonction, $cd_etab, $cin, $email])) {
            $_SESSION['success'] = "Agent ajouté avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de l'ajout.";
        }
    }
}

// Récupérer les fonctions et établissements pour les selects
$fonctions = $pdo->query("SELECT id_fonction, libelle_fr FROM r_fonction")->fetchAll();
$etablissements = $pdo->query("SELECT CD_ETAB, NOM_ETABL FROM z_etab WHERE Actif = 1")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter un agent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        h2 {
            color: #343a40;
            font-weight: 600;
        }
        .form-label {
            font-weight: 500;
        }
        .form-control, .form-select {
            border-radius: 6px;
            box-shadow: none;
        }
        .btn-primary {
            background-color: #0056b3;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
        }
        .btn-primary:hover {
            background-color: #004095;
        }
        .form-section {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body>
<?php include 'include/nav.php'; ?>

<div class="container mt-5">
    <h2 class="mb-4"><i class="bi bi-person-plus me-2"></i>Ajouter un agent</h2>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <form method="post" class="form-section">
        <div class="row g-4">
            <div class="col-md-6">
                <label class="form-label">PPR</label>
                <input type="text" name="ppr" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Nom</label>
                <input type="text" name="nom" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Prénom</label>
                <input type="text" name="prenom" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">CIN</label>
                <input type="text" name="cin" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Fonction</label>
                <select name="id_fonction" class="form-select" required>
                    <option value="">Choisir une fonction...</option>
                    <?php foreach ($fonctions as $fonction): ?>
                        <option value="<?= $fonction['id_fonction'] ?>"><?= $fonction['libelle_fr'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-12">
                <label class="form-label">Établissement</label>
                <select name="cd_etab" class="form-select" required>
                    <option value="">Choisir un établissement...</option>
                    <?php foreach ($etablissements as $etab): ?>
                        <option value="<?= $etab['CD_ETAB'] ?>"><?= $etab['NOM_ETABL'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 text-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle me-1"></i> Ajouter l'agent
                </button>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.querySelector('form').addEventListener('submit', function(e) {
        if (!confirm("Confirmez-vous la création de cet agent ?")) {
            e.preventDefault();
        }
    });
</script>
</body>
</html>