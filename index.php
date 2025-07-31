<?php
session_start();

$error_message = '';
$success_message = '';

if(isset($_POST['ajouter'])) {
    // Nettoyage des entrées
    $username = htmlspecialchars(trim($_POST['username']));
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = trim($_POST['password']);
    $full_name = htmlspecialchars(trim($_POST['full_name']));
    $role = htmlspecialchars(trim($_POST['role']));
    
    // Validation
    if(empty($username) || empty($email) || empty($password) || empty($full_name) || empty($role)) {
        $error_message = "Veuillez remplir tous les champs obligatoires";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Format d'email invalide";
    } elseif(strlen($password) < 8) {
        $error_message = "Le mot de passe doit contenir au moins 8 caractères";
    } else {
        require_once 'include/database.php';
        
        // Vérifier si l'email ou le username existe déjà
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $check->execute([$email, $username]);
        
        if($check->rowCount() > 0) {
            $error_message = "Un utilisateur avec cet email ou nom d'utilisateur existe déjà";
        } else {
            // Hash du mot de passe
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $created_at = date('Y-m-d H:i:s');
            $target_file = null;
            
            // Gestion de l'upload
            if(isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $max_size = 2 * 1024 * 1024; // 2MB
                
                if(in_array($_FILES['profile_picture']['type'], $allowed_types) && 
                   $_FILES['profile_picture']['size'] <= $max_size) {
                    
                    $upload_dir = "uploads/";
                    if(!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                    $file_name = uniqid('user_') . '.' . $file_ext;
                    $target_file = $upload_dir . $file_name;
                    
                    if(!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                        $target_file = null;
                    }
                }
            }
            
            // Insertion
            try {
                $sql = "INSERT INTO users (username, email, password_hash, full_name, role, profile_picture, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$username, $email, $password_hash, $full_name, $role, $target_file, $created_at]);
                
                // Message de succès
                $success_message = "Compte créé avec succès!";
                
                // Option 1: Rediriger vers la page de connexion avec un message
                $_SESSION['success_message'] = "Votre compte a été créé. Vous pouvez maintenant vous connecter.";
                header("Location: connection.php");
                exit();
                
                // Option 2: Afficher le message sur la même page (décommenter et commenter les lignes ci-dessus)
                // $success_message = "Compte créé avec succès!";
                
            } catch(PDOException $e) {
                $error_message = "Erreur lors de la création du compte: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un compte</title>
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
        
        .container {
            position: relative;
            z-index: 1;
        }
        
                
        .register-container {
            max-width: 520px;
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
        
        .form-control, .form-select {
            border-radius: 8px;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s;
            margin-bottom: 1rem;
        }
        
        .form-control:focus, .form-select:focus {
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
            width: 100%;
            margin-top: 1rem;
        }
        
        .btn-primary:hover {
            background-color: #5a52e0;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 99, 255, 0.4);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .btn-outline-secondary {
            border-radius: 8px;
            padding: 12px;
            transition: all 0.3s;
            width: 100%;
            margin-top: 0.5rem;
        }
        
        .btn-outline-secondary:hover {
            background-color: #f8f9fa;
        }
        
        .alert {
            border-radius: 8px;
            margin-bottom: 1.5rem;
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
        
        .required-field::after {
            content: " *";
            color: #dc3545;
        }
        
        .text-muted {
            font-size: 0.85rem;
            display: block;
            margin-top: -0.5rem;
            margin-bottom: 1rem;
        }
        
        .file-upload {
            position: relative;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        
        .file-upload-input {
            position: absolute;
            font-size: 100px;
            opacity: 0;
            right: 0;
            top: 0;
            cursor: pointer;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <?php include 'include/nav.php'; ?>
    
    
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 register-container">
                <div class="card shadow-lg">
                    <div class="card-body">
                        <h2 class="card-title text-center mb-4">Créer un nouveau compte</h2>
                        
                        <?php if(!empty($error_message)): ?>
                            <div class="alert alert-danger"><?= $error_message ?></div>
                        <?php endif; ?>
                        
                        <?php if(!empty($success_message)): ?>
                            <div class="alert alert-success"><?= $success_message ?></div>
                        <?php endif; ?>
                        
                        <form method="post" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label required-field">Nom d'utilisateur</label>
                                <input type="text" class="form-control" name="username" required
                                       value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label required-field">Email</label>
                                <input type="email" class="form-control" name="email" required
                                       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label required-field">Mot de passe</label>
                                <input type="password" class="form-control" name="password" required minlength="8">
                                <small class="text-muted">Minimum 8 caractères</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label required-field">Nom complet</label>
                                <input type="text" class="form-control" name="full_name" required
                                       value="<?= isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : '' ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label required-field">Rôle</label>
                                <select class="form-select" name="role" required>
                                    <option value="">Sélectionner un rôle</option>
                                    <option value="admin" <?= (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : '' ?>>Admin</option>
                                    <option value="user" <?= (isset($_POST['role']) && $_POST['role'] === 'user') ? 'selected' : '' ?>>Utilisateur</option>
                                    <option value="moderator" <?= (isset($_POST['role']) && $_POST['role'] === 'moderator') ? 'selected' : '' ?>>Modérateur</option>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Photo de profil</label>
                                <div class="file-upload">
                                    <input type="file" class="form-control file-upload-input" name="profile_picture" accept="image/*">
                                    <div class="input-group">
                                        <input type="text" class="form-control" placeholder="Choisir un fichier" disabled>
                                        <button class="btn btn-outline-secondary" type="button">Parcourir</button>
                                    </div>
                                </div>
                                <small class="text-muted">Formats acceptés: JPG, PNG, GIF (max 2MB)</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary py-3 fw-bold" name="ajouter">
                                Créer le compte
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-plus ms-2" viewBox="0 0 16 16">
                                    <path d="M6 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H1s-1 0-1-1 1-4 6-4 6 3 6 4zm-1-.004c-.001-.246-.154-.986-.832-1.664C9.516 10.68 8.289 10 6 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10z"/>
                                    <path fill-rule="evenodd" d="M13.5 5a.5.5 0 0 1 .5.5V7h1.5a.5.5 0 0 1 0 1H14v1.5a.5.5 0 0 1-1 0V8h-1.5a.5.5 0 0 1 0-1H13V5.5a.5.5 0 0 1 .5-.5z"/>
                                </svg>
                            </button>
                            
                            <a href="connection.php" class="btn btn-outline-secondary py-3 mt-2">
                                Déjà un compte? Se connecter
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-right ms-2" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8z"/>
                                </svg>
                            </a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Script pour afficher le nom du fichier sélectionné
        document.querySelector('.file-upload-input').addEventListener('change', function(e) {
            const fileName = e.target.files[0] ? e.target.files[0].name : 'Aucun fichier sélectionné';
            this.previousElementSibling.querySelector('input').value = fileName;
        });
        
        // Déclencher le click sur l'input file quand on clique sur le bouton Parcourir
        document.querySelector('.file-upload button').addEventListener('click', function() {
            this.parentElement.parentElement.querySelector('input[type="file"]').click();
        });
    </script>
</body>
</html>