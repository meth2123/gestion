<?php
session_start();
require_once __DIR__ . '/service/mysqlcon.php';
require_once __DIR__ . '/components/SecureSubscriptionChecker.php';

$error_message = '';
$success_message = '';
$email = '';
$school = '';

$checker = new SecureSubscriptionChecker($link);

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $school = trim($_POST['school'] ?? '');
    $consent = isset($_POST['consent']);
    
    if (empty($email)) {
        $error_message = "L'email est obligatoire.";
    } elseif (!$consent) {
        $error_message = "Vous devez accepter l'utilisation de vos informations.";
    } else {
        // Générer un code de vérification et rediriger immédiatement
        $code = $checker->generateVerificationCode($email, $school);
        // Rediriger vers la même page avec les paramètres pour afficher les résultats
        header("Location: secure_subscription_check.php?verified=1");
        exit;
    }
}

// Si on a un code de vérification, afficher les résultats
if (isset($_SESSION['verification_code']) && isset($_SESSION['verification_email'])) {
    $email = $_SESSION['verification_email'];
    $school = $_SESSION['verification_school'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification Sécurisée - SchoolManager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <img src="source/logo.jpg" class="me-2" width="40" height="40" alt="Logo"/>
                <span class="fw-bold">SchoolManager</span>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">
                    <i class="fas fa-home"></i> Accueil
                </a>
                <a class="nav-link" href="login.php">
                    <i class="fas fa-sign-in-alt"></i> Se connecter
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">
                            <i class="fas fa-shield-alt"></i> Vérification Sécurisée de l'Abonnement
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success_message): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['verification_code'])): ?>
                            <!-- Résultats de la vérification -->
                            <div class="mb-4">
                                <h5>Résultats de la vérification :</h5>
                                <?php if ($email): ?>
                                    <p><strong>Email vérifié :</strong> <?php echo htmlspecialchars($email); ?></p>
                                <?php endif; ?>
                                <?php if ($school): ?>
                                    <p><strong>École vérifiée :</strong> <?php echo htmlspecialchars($school); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <?php echo $checker->renderSecureResults($email, $school); ?>
                            
                            <hr>
                            <p class="text-muted">
                                <a href="secure_subscription_check.php" class="btn btn-outline-secondary" onclick="clearVerification()">
                                    <i class="fas fa-search"></i> Nouvelle recherche
                                </a>
                            </p>
                        <?php else: ?>
                            <!-- Formulaire de vérification -->
                            <div class="text-center mb-4">
                                <p class="lead">Vérifiez votre abonnement de manière sécurisée</p>
                                <p class="text-muted">Vos informations sont protégées et ne seront utilisées que pour vérifier votre abonnement.</p>
                            </div>
                            
                            <?php echo $checker->renderSecureChecker(); ?>
                            
                            <div class="mt-4">
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-shield-alt"></i> Sécurité et confidentialité</h6>
                                    <ul class="mb-0">
                                        <li>Vos informations sont cryptées et protégées</li>
                                        <li>La vérification expire automatiquement après 10 minutes</li>
                                        <li>Vos données ne sont pas stockées de manière permanente</li>
                                        <li>Seul le propriétaire de l'abonnement peut accéder aux informations</li>
                                    </ul>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Liens utiles -->
                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-plus-circle text-success fa-2x mb-3"></i>
                                <h5>Nouvel abonnement</h5>
                                <p class="text-muted">Créez un nouveau compte et abonnement</p>
                                <a href="module/subscription/register.php" class="btn btn-success">
                                    S'abonner
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-sign-in-alt text-primary fa-2x mb-3"></i>
                                <h5>Se connecter</h5>
                                <p class="text-muted">Accédez à votre espace administrateur</p>
                                <a href="login.php" class="btn btn-primary">
                                    Connexion
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-question-circle text-info fa-2x mb-3"></i>
                                <h5>Besoin d'aide ?</h5>
                                <p class="text-muted">Consultez notre documentation</p>
                                <a href="documentation/index.php" class="btn btn-info">
                                    Documentation
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function clearVerification() {
            // Nettoyer les données de vérification côté client
            if (confirm('Êtes-vous sûr de vouloir effacer la vérification en cours ?')) {
                // Rediriger vers une page qui nettoie la session
                window.location.href = 'clear_verification.php';
            }
        }
    </script>
</body>
</html>

