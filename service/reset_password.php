<?php
include_once('mysqlcon.php');

// Démarrer la session si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer le code depuis la session ou depuis POST (pour compatibilité)
    $user_id = isset($_POST['user_id']) ? trim($_POST['user_id']) : '';
    $reset_code = isset($_POST['reset_code']) ? trim($_POST['reset_code']) : '';
    
    // Si le code n'est pas dans POST, essayer de le récupérer depuis la session
    if (empty($reset_code) && isset($_SESSION['reset_code']) && isset($_SESSION['reset_code_expiry'])) {
        // Vérifier que la session n'a pas expiré
        if (time() < $_SESSION['reset_code_expiry']) {
            $reset_code = $_SESSION['reset_code'];
            // Si user_id n'est pas fourni, utiliser celui de la session
            if (empty($user_id) && isset($_SESSION['reset_user_id'])) {
                $user_id = $_SESSION['reset_user_id'];
            }
        } else {
            // Session expirée
            unset($_SESSION['reset_code'], $_SESSION['reset_user_id'], $_SESSION['reset_code_expiry']);
            header("Location: ../?error=" . urlencode("Le code de réinitialisation a expiré. Veuillez en demander un nouveau."));
            exit();
        }
    }
    
    $new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
    $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';

    // Validation des données
    if (empty($user_id) || empty($reset_code) || empty($new_password) || empty($confirm_password)) {
        header("Location: ../?error=" . urlencode("Tous les champs sont obligatoires"));
        exit();
    }

    if ($new_password !== $confirm_password) {
        header("Location: ../?error=" . urlencode("Les mots de passe ne correspondent pas"));
        exit();
    }

    // Vérifier le code de réinitialisation
    $sql = "SELECT * FROM password_resets 
            WHERE user_id = ? 
            AND reset_code = ? 
            AND expiry > NOW() 
            AND used = 0";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("ss", $user_id, $reset_code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        header("Location: ../?error=" . urlencode("Code de réinitialisation invalide ou expiré"));
        exit();
    }

    // Hasher le mot de passe avant de le stocker
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    // Mettre à jour le mot de passe dans la table users
    $sql = "UPDATE users SET password = ? WHERE userid = ?";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("ss", $hashed_password, $user_id);
    
    if (!$stmt->execute()) {
        header("Location: ../?error=" . urlencode("Erreur lors de la mise à jour du mot de passe"));
        exit();
    }

    // Mettre à jour le mot de passe dans la table spécifique selon le type d'utilisateur
    $sql = "SELECT usertype FROM users WHERE userid = ?";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    switch($user['usertype']) {
        case 'teacher':
            $sql = "UPDATE teachers SET password = ? WHERE id = ?";
            break;
        case 'staff':
            $sql = "UPDATE staff SET password = ? WHERE id = ?";
            break;
        case 'student':
            $sql = "UPDATE students SET password = ? WHERE id = ?";
            break;
        case 'parent':
            $sql = "UPDATE parents SET password = ? WHERE id = ?";
            break;
        case 'admin':
            $sql = "UPDATE admin SET password = ? WHERE id = ?";
            break;
         case 'director':
                // Pas de colonne password dans director, rien à faire
                $sql = null;
                break;
    }

    if ($sql) {
        $stmt = $link->prepare($sql);
        $stmt->bind_param("ss", $hashed_password, $user_id);
        $stmt->execute();
    }

    // Marquer le code comme utilisé
    $sql = "UPDATE password_resets SET used = 1 WHERE user_id = ? AND reset_code = ?";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("ss", $user_id, $reset_code);
    $stmt->execute();

    // Nettoyer la session
    unset($_SESSION['reset_code'], $_SESSION['reset_user_id'], $_SESSION['reset_code_expiry']);

    // Rediriger vers la page de connexion avec un message de succès
    header("Location: ../?success=" . urlencode("Votre mot de passe a été réinitialisé avec succès"));
    exit();
}

// Récupérer le code depuis la session ou depuis l'URL (pour compatibilité avec les anciens liens)
$reset_code_from_url = isset($_GET['code']) ? trim($_GET['code']) : '';
$user_id_from_url = isset($_GET['user_id']) ? trim($_GET['user_id']) : '';

// Si le code est dans l'URL, le stocker dans la session et rediriger sans l'URL
if (!empty($reset_code_from_url) && !empty($user_id_from_url)) {
    $_SESSION['reset_code'] = $reset_code_from_url;
    $_SESSION['reset_user_id'] = $user_id_from_url;
    $_SESSION['reset_code_expiry'] = time() + 3600;
    // Rediriger sans les paramètres dans l'URL
    header("Location: reset_password.php");
    exit();
}

// Récupérer depuis la session pour pré-remplir le formulaire
$prefill_code = '';
$prefill_user_id = '';
if (isset($_SESSION['reset_code']) && isset($_SESSION['reset_code_expiry']) && time() < $_SESSION['reset_code_expiry']) {
    $prefill_code = $_SESSION['reset_code'];
    $prefill_user_id = $_SESSION['reset_user_id'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation du mot de passe</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full space-y-8 p-8 bg-white rounded-lg shadow-md">
            <div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    Réinitialisation du mot de passe
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    Entrez votre code de réinitialisation et votre nouveau mot de passe
                </p>
            </div>
            <form class="mt-8 space-y-6" action="" method="POST">
                <div class="rounded-md shadow-sm -space-y-px">
                    <div>
                        <label for="user_id" class="sr-only">Identifiant</label>
                        <input id="user_id" name="user_id" type="text" required 
                               value="<?= htmlspecialchars($prefill_user_id) ?>"
                               class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                               placeholder="Votre identifiant" <?= !empty($prefill_user_id) ? 'readonly' : '' ?>>
                    </div>
                    <div>
                        <label for="reset_code" class="sr-only">Code de réinitialisation</label>
                        <input id="reset_code" name="reset_code" type="text" required 
                               value="<?= htmlspecialchars($prefill_code) ?>"
                               class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                               placeholder="Code de réinitialisation" <?= !empty($prefill_code) ? 'readonly' : '' ?>>
                        <?php if (!empty($prefill_code)): ?>
                            <p class="mt-1 text-xs text-gray-500">Code pré-rempli depuis votre session</p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label for="new_password" class="sr-only">Nouveau mot de passe</label>
                        <input id="new_password" name="new_password" type="password" required 
                               class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                               placeholder="Nouveau mot de passe">
                    </div>
                    <div>
                        <label for="confirm_password" class="sr-only">Confirmer le mot de passe</label>
                        <input id="confirm_password" name="confirm_password" type="password" required 
                               class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                               placeholder="Confirmer le mot de passe">
                    </div>
                </div>

                <div>
                    <button type="submit" 
                            class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Réinitialiser le mot de passe
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html> 