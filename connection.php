<?php
session_start();

require_once 'include/database.php';

$error = '';

if (isset($_POST['connection'])) {
    // Journalisation de la tentative de connexion
    error_log("Tentative de connexion depuis l'IP: " . $_SERVER['REMOTE_ADDR']);
    
    $identifiant = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($identifiant) || empty($password)) {
        $error = "Veuillez remplir tous les champs.";
        error_log("Champs manquants pour la connexion - IP: " . $_SERVER['REMOTE_ADDR']);
    } else {
        try {
            // Journalisation avant la requête
            error_log("Tentative de connexion pour l'identifiant: " . $identifiant);

            $stmt = $pdo->prepare("SELECT id, username, email, password_hash, role FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$identifiant, $identifiant]);
            $user = $stmt->fetch();

            if ($user) {
                error_log("Utilisateur trouvé: " . $user['username'] . " (ID: " . $user['id'] . ")");
                if (password_verify($password, $user['password_hash'])) {
                    error_log("Connexion réussie pour l'utilisateur: " . $user['username'] . " (ID: " . $user['id'] . ")");
                    session_regenerate_id(true);
                    $_SESSION['utilisateur'] = [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'role' => $user['role']
                    ];
                    error_log("Redirection selon le rôle pour l'utilisateur: " . $user['username']);
                    switch ($_SESSION['utilisateur']['role']) {
                        case 'admin':
                            header("Location: admin.php");
                            break;
                        case 'user':
                            header("Location: user_dashboard.php");
                            break;
                        case 'moderator':
                            header("Location: moderator_dashboard.php");
                            break;
                        default:
                            header("Location: index.php");
                    }
                    exit();
                } else {
                    $error = "Mot de passe incorrect.";
                    error_log("Échec de connexion: mot de passe incorrect pour l'identifiant: " . $identifiant);
                }
            } else {
                $error = "Utilisateur non trouvé.";
                error_log("Échec de connexion: utilisateur non trouvé: " . $identifiant);
            }
        } catch (PDOException $e) {
            $error = "Erreur système. Veuillez réessayer plus tard.";
            error_log("ERREUR PDO lors de la connexion: " . $e->getMessage() . " - Identifiant: " . $identifiant);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6c63ff;
            --primary-light: #a29bfe;
            --secondary-color: #f8f9fa;
            --text-color: #4a4a4a;
            --soft-shadow: 0 4px 20px rgba(108, 99, 255, 0.15);
        }
        
       body {
            position: relative;
            min-height: 100vh;
            background-image: url('gf.webp');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-repeat: no-repeat;
        }
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background: rgba(255,255,255,0.92); /* Plus blanc, plus lisible */
            backdrop-filter: blur(2px); /* Optionnel : ajoute un léger flou */
            z-index: 0;
            pointer-events: none;
        }
        
        
        .login-container {
            max-width: 420px;
            margin: 0 auto;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--soft-shadow);
            transition: transform 0.3s ease;
            background: white;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-body {
            padding: 2.5rem;
        }
        
        .card-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1.5rem !important;
        }
        
        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 0.25rem rgba(108, 99, 255, 0.15);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-weight: 500;
            letter-spacing: 0.5px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(108, 99, 255, 0.3);
        }
        
        .btn-primary:hover {
            background-color: #5a52e0;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 99, 255, 0.4);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .alert {
            border-radius: 8px;
        }
        
        a {
            color: var(--primary-color);
            text-decoration: none;
            transition: color 0.3s;
        }
        
        a:hover {
            color: #5a52e0;
            text-decoration: underline;
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 1.5rem 0;
            color: #9e9e9e;
        }
        
        .divider::before, .divider::after {
            content: "";
            flex: 1;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .divider::before {
            margin-right: 1rem;
        }
        
        .divider::after {
            margin-left: 1rem;
        }
        
        .forgot-password {
            display: block;
            text-align: center;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <?php include 'include/nav.php'; ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6 login-container">
                <div class="card shadow-lg">
                    <div class="card-body">
                        <h2 class="card-title text-center mb-4">Connexion</h2>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <form method="post">
                            <div class="mb-4">
                                <label for="username" class="form-label">Nom d'utilisateur</label>
                                <input type="text" class="form-control" id="username" name="username" required autofocus>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">Mot de passe</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <a href="forgot_password.php" class="forgot-password">Mot de passe oublié ?</a>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 py-3 fw-bold" name="connection">
                                Se connecter
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-right ms-2" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8z"/>
                                </svg>
                            </button>
                        </form>
                        
                        <div class="divider">ou</div>
                        
                        <div class="text-center">
                            <p>Pas encore de compte ? <a href="index.php" class="fw-bold">Créer un compte</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>