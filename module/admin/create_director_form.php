
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un Directeur</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .container {
            max-width: 600px;
            margin: 40px auto;
            padding: 20px;
        }
        .card {
            border: none;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        .btn-primary {
            background-color: #3498db;
            border-color: #3498db;
        }
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
        .alert {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php
require_once dirname(dirname(dirname(__FILE__))) . '/config.php';
require_once MYSQLCON_PATH;

if (session_status() === PHP_SESSION_NONE) session_start();
require_once DB_CONFIG_PATH;
$admin_id = $_SESSION['admin_id'] ?? $_SESSION['login_id'] ?? null;
if ($admin_id) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT firstname, lastname, email FROM director WHERE created_by = ? LIMIT 1");
    $stmt->bind_param("s", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $params = http_build_query([
            'email' => $row['email'],
            'firstname' => $row['firstname'],
            'lastname' => $row['lastname']
        ]);
        header("Location: ../director_created.php?$params");
        exit();
    }
}

require_once DB_CONFIG_PATH;

if (!isLoggedIn()) {
    header("Location: ../../login.php?error=unauthorized");
    exit();
}

// Contrôle d'accès : seulement admin connecté

// Messages d'erreur
$error_messages = [
    'login_required' => 'Vous devez être connecté pour accéder à cette page.',
    'unauthorized' => 'Accès non autorisé. Vous n\'avez pas les permissions nécessaires.',
    'invalid_page' => 'Page non trouvée ou non autorisée.',
    'invalid_credentials' => 'Identifiants invalides. Veuillez réessayer.',
    'director_exists' => 'Le compte directeur existe déjà. Veuillez vous connecter avec vos identifiants existants.',
    'director_created' => 'Le compte directeur a été créé avec succès !',
    'firstname_required' => 'Le prénom est requis',
    'lastname_required' => 'Le nom est requis',
    'empty_email' => 'L\'email est requis',
    'email_exists' => 'Cet email est déjà utilisé',
    'password_required' => 'Le mot de passe est requis',
    'password_mismatch' => 'Les mots de passe ne correspondent pas'
];
    
// Affichage du formulaire uniquement

// Gérer les erreurs dans l'URL
$error = $_GET['error'] ?? '';
if ($error) {
    $error = $error_messages[$error] ?? 'Une erreur est survenue.';
    // Affichage du message SQL si présent (debug)
    if ($error === 'Une erreur est survenue.' && isset($_GET['sqlmsg'])) {
        $error .= '<br><b>Erreur SQL :</b> ' . htmlspecialchars($_GET['sqlmsg']);
    }
}
?>
                    
<?php
// Affichage du message d'erreur si besoin
if (!empty($error)) {
    echo '<div class="alert alert-danger">' . htmlspecialchars($error) . '</div>';
}
?>
<form method="post" action="create_director.php">
    <div class="mb-3">
        <label for="firstname" class="form-label">Prénom du directeur</label>
        <input type="text" class="form-control" id="firstname" name="firstname" required>
    </div>
    <div class="mb-3">
        <label for="lastname" class="form-label">Nom du directeur</label>
        <input type="text" class="form-control" id="lastname" name="lastname" required>
    </div>
    <div class="mb-3">
        <label for="email" class="form-label">Adresse email du directeur</label>
        <input type="email" class="form-control" id="email" name="email" required>
    </div>
    <div class="alert alert-info">Un mot de passe temporaire sera généré automatiquement et envoyé par email au directeur.</div>
    <div class="d-grid gap-2">
        <button type="submit" class="btn btn-primary">Créer le compte</button>
        <a href="../index.php" class="btn btn-secondary">Retour à l'accueil</a>
    </div>
</form>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
