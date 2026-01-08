<?php
require_once dirname(dirname(dirname(__FILE__))) . '/config.php';
require_once MYSQLCON_PATH;
require_once DB_CONFIG_PATH;

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];

    // 1. Chercher le directeur par email
    $stmt = $conn->prepare("SELECT userid FROM director WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $director = $result->fetch_assoc();
        $userid = $director['userid'];

        // 2. Vérifier le mot de passe dans users
        $stmt = $conn->prepare("SELECT password FROM users WHERE userid = ? AND usertype = 'director'");
        $stmt->bind_param("s", $userid);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                // Authentification réussie
                $_SESSION['user_id'] = $userid;
                $_SESSION['usertype'] = 'director';
                header("Location: index.php");
                exit();
            }
        }
    }
    // Authentification échouée
    $error = "Identifiants invalides. Veuillez réessayer.";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Connexion Directeur</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 400px; margin: 50px auto; padding: 20px; }
        .login-form { background: #f9f9f9; padding: 20px; border-radius: 5px; }
        .error { color: #e74c3c; margin-bottom: 10px; }
        .success { color: #2ecc71; margin-bottom: 10px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input { width: 100%; padding: 8px; }
        .btn { background: #3498db; color: white; padding: 10px 15px; border: none; border-radius: 3px; cursor: pointer; }
        .btn:hover { background: #2980b9; }
    </style>
</head>
<body>
    <h2>Connexion Directeur</h2>
    
    <?php if (isset($error)): ?>
    <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <form class="login-form" method="POST" action="">
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
        </div>
        
        <div class="form-group">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" required>
            <div class="back-btn">
                <a href="../index.php" class="btn">Retour</a>
            </div>
        </div>
        
        <button type="submit" class="btn">Se connecter</button>
    </form>
</body>
</html>
