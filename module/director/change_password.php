<?php
require_once 'check_director_access.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config.php';
require_once MYSQLCON_PATH;
require_once DB_CONFIG_PATH;

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Vérifier si les nouveaux mots de passe correspondent
    if ($new_password !== $confirm_password) {
        $error = "Les nouveaux mots de passe ne correspondent pas.";
    } else {
        // Vérifier l'ancien mot de passe
        $stmt = $conn->prepare("SELECT password FROM users WHERE userid = ?");
        $stmt->bind_param("s", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if (password_verify($current_password, $user['password'])) {
            // Mettre à jour le mot de passe
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE userid = ?");
            $stmt->bind_param("ss", $hashed_password, $_SESSION['user_id']);
            $stmt->execute();

            // Créer l'entrée dans la table director
            $stmt = $conn->prepare("INSERT INTO director (user_id) VALUES (?)");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();

            // Redirection vers le tableau de bord
            header("Location: index.php?success=password_changed");
            exit();
        } else {
            $error = "Le mot de passe actuel est incorrect.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Changer le Mot de Passe - Directeur</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 400px; margin: 50px auto; padding: 20px; }
        .change-password-form { background: #f9f9f9; padding: 20px; border-radius: 5px; }
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
    <h2>Changer le Mot de Passe</h2>
    
    <?php if (isset($error)): ?>
    <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <form class="change-password-form" method="POST" action="">
        <div class="form-group">
            <label for="current_password">Mot de passe actuel</label>
            <input type="password" id="current_password" name="current_password" required>
        </div>
        
        <div class="form-group">
            <label for="new_password">Nouveau mot de passe</label>
            <input type="password" id="new_password" name="new_password" required>
        </div>
        
        <div class="form-group">
            <label for="confirm_password">Confirmer le nouveau mot de passe</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
        </div>
        
        <button type="submit" class="btn">Changer le mot de passe</button>
    </form>
</body>
</html>
