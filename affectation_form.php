<?php
session_start();
error_log('Session utilisateur: ' . print_r($_SESSION['utilisateur'] ?? 'Aucune', true));
require_once 'include/database.php';
require_once 'mail.php';

if (!isset($_SESSION['utilisateur'])) {
    header("Location: connection.php");
    exit();
}

// Précharger les statuts
$idStatutActif = $pdo->query("SELECT id_statut FROM r_statuts WHERE libelle = 'actif'")->fetchColumn();
$idStatutInactif = $pdo->query("SELECT id_statut FROM r_statuts WHERE libelle = 'inactif'")->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'affecter') {
        $nd = $_POST['nd'] ?? '';
        $ppr = $_POST['ppr'] ?? '';
        $cd_etab = $_POST['etablissement'] ?? '';
        $id_fonction = $_POST['fonction'] ?? '';
        $etablissement = $cd_etab;
        $fonction = $id_fonction;

        if (empty($nd) || empty($ppr) || empty($cd_etab) || empty($id_fonction)) {
            $_SESSION['error'] = "Tous les champs sont obligatoires.";
        } else {
            $affectationExists = $pdo->prepare("SELECT COUNT(*) FROM affectation_flotte WHERE nd = ?");
            $affectationExists->execute([$nd]);
            $agentHasNd = $pdo->prepare("SELECT COUNT(*) FROM affectation_flotte WHERE ppr = ?");
            $agentHasNd->execute([$ppr]);

            if ($affectationExists->fetchColumn() > 0) {
                $_SESSION['error'] = "Ce numéro est déjà affecté à un agent.";
            } elseif ($agentHasNd->fetchColumn() > 0) {
                $_SESSION['error'] = "Cet agent a déjà un numéro affecté.";
            } else {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("UPDATE all_flotte SET id_statut = ?, CD_ETAB = ? WHERE nd = ?")
                        ->execute([$idStatutActif, $cd_etab, $nd]);

                    $pdo->prepare("INSERT INTO affectation_flotte (nd, ppr, date_affectation, id_fonction, id_statut) VALUES (?, ?, NOW(), ?, ?)")
                        ->execute([$nd, $ppr, $id_fonction, $idStatutActif]);

                    $stmt = $pdo->prepare("SELECT id_statut FROM all_flotte WHERE nd = ?");
                    $stmt->execute([$nd]);
                    $id_statut_nd = $stmt->fetchColumn();

                    $statut_nd = ($id_statut_nd == $idStatutActif) ? 1 : (($id_statut_nd == $idStatutInactif) ? 2 : 3);

                    $pdo->prepare("INSERT INTO historique_flotte (ppr, nom, prenom, lib_etab, lib_fonction, date_affectation, statut_nd, CD_PROV) 
                        SELECT a.ppr, a.nom, a.prenom, e.NOM_ETABL, f.libelle_fr, NOW(), ?, e.CD_ETAB
                        FROM tb_agents a 
                        JOIN z_etab e ON a.CD_ETAB = e.CD_ETAB 
                        JOIN r_fonction f ON a.id_fonction = f.id_fonction 
                        WHERE a.ppr = ?")
                        ->execute([$statut_nd, $ppr]);

                    $pdo->commit();

                    $stmt = $pdo->prepare("SELECT email, nom, prenom FROM tb_agents WHERE ppr = ?");
                    $stmt->execute([$ppr]);
                    $agent = $stmt->fetch();

                    if ($agent && !empty($agent['email'])) {
                        envoyerMailAffectation(
                            $agent['email'],
                            $agent['prenom'] . ' ' . $agent['nom'],
                            $nd,
                            $etablissement,
                            $fonction
                        );
                    }

                    $_SESSION['success'] = "Affectation réussie !";
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log('Erreur PDO: ' . $e->getMessage());
                    $_SESSION['error'] = "Erreur technique : " . $e->getMessage();
                }
            }
        }
    }

    if ($action === 'perte_carte') {
        $nd = $_POST['nd_perte'] ?? '';
        $motif = $_POST['motif_perte'] ?? '';
        $commentaire = $_POST['commentaire_perte'] ?? '';

        if (!empty($nd) && !empty($motif)) {
            $stmt = $pdo->prepare("INSERT INTO signalement_perte (nd, motif, commentaire, date_signalement) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$nd, $motif, $commentaire]);
            $_SESSION['success'] = "Signalement enregistré. Vous pouvez générer le bon de commande.";
        } else {
            $_SESSION['error'] = "Veuillez remplir tous les champs requis.";
        }
    }
}

$numeros = $pdo->prepare("
    SELECT f.nd
    FROM all_flotte f
    LEFT JOIN affectation_flotte af ON f.nd = af.nd
    WHERE f.id_statut = ? OR (f.id_statut = ? AND af.ppr IS NULL)
");
$numeros->execute([$idStatutInactif, $idStatutActif]);
$numeros = $numeros->fetchAll();

$agents = $pdo->query("SELECT ppr, nom, prenom FROM tb_agents")->fetchAll();
$etablissements = $pdo->query("SELECT CD_ETAB, NOM_ETABL FROM z_etab WHERE actif = 1")->fetchAll();
$fonctions = $pdo->query("SELECT id_fonction, libelle_fr FROM r_fonction")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Affectation de numéro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .form-section {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .btn-primary {
            background-color: #0056b3;
            border: none;
        }
        .btn-primary:hover {
            background-color: #004095;
        }
    </style>
</head>
<body>
<?php include 'include/nav.php'; ?>

<div class="container mt-5">
    <h2 class="mb-4"><i class="bi bi-sim me-2"></i>Affectation de numéro</h2>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <form method="post" class="form-section mb-4">
        <input type="hidden" name="action" value="affecter">
        <div class="row g-4">
            <div class="col-md-6">
                <label class="form-label">Numéro à affecter</label>
                <input list="ndList" name="nd" class="form-control" required>
                <datalist id="ndList">
                    <?php foreach ($numeros as $num): ?>
                        <option value="<?= htmlspecialchars($num['nd']) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="col-md-6">
                <label class="form-label">Agent (PPR)</label>
                <select name="ppr" class="form-select" required>
                    <option value="">Choisir un agent...</option>
                    <?php foreach ($agents as $agent): ?>
                        <option value="<?= $agent['ppr'] ?>">
                            <?= htmlspecialchars($agent['ppr']) ?> - <?= htmlspecialchars($agent['nom'] . ' ' . $agent['prenom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Établissement</label>
                <select name="etablissement" class="form-select" required>
                    <option value="">Choisir un établissement...</option>
                    <?php foreach ($etablissements as $etab): ?>
                        <option value="<?= $etab['CD_ETAB'] ?>"><?= htmlspecialchars($etab['NOM_ETABL']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Fonction</label>
                <select name="fonction" class="form-select" required>
                    <option value="">Choisir une fonction...</option>
                    <?php foreach ($fonctions as $fonction): ?>
                        <option value="<?= $fonction['id_fonction'] ?>"><?= htmlspecialchars($fonction['libelle_fr']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 text-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle me-1"></i> Valider l'affectation
                </button>
            </div>
        </div>
    </form>

    <div class="form-section mb-4">
        <h4 class="mb-3"><i class="bi bi-exclamation-triangle me-2"></i>Signalement de perte ou changement de carte</h4>
        <form method="post">
            <input type="hidden" name="action" value="perte_carte">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Numéro ND concerné</label>
                    <input list="ndList" name="nd_perte" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Motif</label>
                    <select name="motif_perte" class="form-select" required>
                        <option value="perte">Perte</option>
                        <option value="vol">Vol</option>
                        <option value="changement">Changement de carte</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Commentaire</label>
                    <textarea name="commentaire_perte" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-exclamation-circle me-1"></i> Signaler
                    </button>
                </div>
            </div>
        </form>

        <?php if (isset($_SESSION['success']) && str_contains($_SESSION['success'], 'Signalement enregistré')): ?>
            <form method="post" action="generer_bon_commande.php" target="_blank" class="mt-3">
                <input type="hidden" name="nd" value="<?= htmlspecialchars($_POST['nd_perte'] ?? '') ?>">
                <input type="hidden" name="motif" value="<?= htmlspecialchars($_POST['motif_perte'] ?? '') ?>">
                <input type="hidden" name="commentaire" value="<?= htmlspecialchars($_POST['commentaire_perte'] ?? '') ?>">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-file-earmark-plus me-1"></i> Générer le bon de commande
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.querySelectorAll('form').forEach(form => {
        if (form.querySelector('input[name="action"][value="affecter"]')) {
            form.addEventListener('submit', function(e) {
                if (!confirm("Confirmez-vous cette affectation ?")) {
                    e.preventDefault();
                }
            });
        }
    });
</script>
</body>
</html>
