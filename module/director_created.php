<?php
session_start();
require_once dirname(dirname(__FILE__)) . '/config.php';
require_once MYSQLCON_PATH;
require_once DB_CONFIG_PATH;

// Messages d'erreur
$error_messages = [
    'login_required' => 'Vous devez être connecté pour accéder à cette page.',
    'unauthorized' => 'Accès non autorisé. Vous n\'avez pas les permissions nécessaires.',
    'invalid_page' => 'Page non trouvée ou non autorisée.',
    'invalid_credentials' => 'Identifiants invalides. Veuillez réessayer.',
    'director_exists' => 'Le compte directeur existe déjà. Veuillez vous connecter avec vos identifiants existants.',
    'director_created' => 'Le compte directeur a été créé avec succès !'
];

// Vérifier si l'utilisateur est connecté et est admin
if (!isLoggedIn()) {
    header("Location: ../../login.php?error=unauthorized");
    exit();
}

// Récupérer les informations du directeur depuis l'URL
$email = $_GET['email'] ?? '';
$firstname = $_GET['firstname'] ?? '';
$lastname = $_GET['lastname'] ?? '';
// On ne vérifie plus la présence du mot de passe temporaire ni des noms/prénoms, car seule l'email est toujours passée par la redirection.

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compte Directeur Créé</title>
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
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .note {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .btn-primary {
            background-color: #3498db;
            border-color: #3498db;
        }
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-body">
                <h2 class="card-title text-center mb-4">Compte Directeur Créé</h2>
                
                <?php if (isset($email)): ?>
                <div class="alert alert-success">
                    Le compte directeur a été créé avec succès !<br>
                    <strong>Nom complet:</strong> <?php echo htmlspecialchars($firstname . ' ' . $lastname); ?><br>
                    <strong>Email:</strong> <?php echo htmlspecialchars($email); ?><br>
                    <br>
                    <div class="alert alert-info">
                        Un mot de passe a été créé et doit être changé lors de la première connexion.
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <a href="../login.php" class="btn btn-primary">Se connecter</a>
                    <a href="../module/admin/index.php" class="btn btn-secondary">Retour</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
