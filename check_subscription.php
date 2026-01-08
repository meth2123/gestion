<?php
session_start();
require_once __DIR__ . '/service/mysqlcon.php';
require_once __DIR__ . '/components/SecureSubscriptionChecker.php';

$error_message = '';
$success_message = '';
$subscription_info = null;

$checker = new SecureSubscriptionChecker($link);

// Traitement de l'envoi d'email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error_message = "L'email est obligatoire.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "L'adresse email n'est pas valide.";
    } else {
        $result = $checker->sendVerificationEmail($email);
        if ($result['success']) {
            $success_message = $result['message'];
            // Les informations sont envoyées par email, rien à afficher sur le site
        } else {
            $error_message = $result['message'];
        }
    }
}

// Vérification du token
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $verification = $checker->verifyToken($token);
    
    if ($verification['valid']) {
        $email = $verification['email'];
        // Utiliser le détecteur du checker
        $detection = $checker->detector->detectSubscriptionStatus($email, null);
        
        if ($detection['exists']) {
            $subscription_info = [
                'subscription' => $detection['subscription'],
                'status' => $detection['status'],
                'message' => $detection['message'],
                'can_renew' => $detection['can_renew'] ?? false,
                'days_until_expiry' => $detection['days_until_expiry'] ?? 0
            ];
        } else {
            $error_message = "Aucun abonnement trouvé avec cet email.";
        }
    } else {
        $error_message = $verification['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérifier mon Abonnement - SchoolManager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .verification-card {
            max-width: 600px;
            margin: 0 auto;
        }
        .subscription-status {
            border-left: 4px solid;
        }
        .status-active { border-left-color: #28a745; }
        .status-expired { border-left-color: #ffc107; }
        .status-pending { border-left-color: #17a2b8; }
    </style>
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
                <div class="card shadow verification-card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">
                            <i class="fas fa-shield-alt"></i> Vérifier mon Abonnement
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

                        <?php if ($subscription_info): ?>
                            <!-- Affichage des informations d'abonnement -->
                            <?php
                            $subscription = $subscription_info['subscription'];
                            $status = $subscription_info['status'];
                            $statusClass = 'status-' . $status;
                            ?>
                            <div class="alert subscription-status <?php echo $statusClass; ?> bg-white p-4 mb-4">
                                <h5 class="mb-3">
                                    <?php
                                    $statusIcons = [
                                        'active' => '<i class="fas fa-check-circle text-success"></i> Abonnement actif',
                                        'expired' => '<i class="fas fa-exclamation-triangle text-warning"></i> Abonnement expiré',
                                        'pending' => '<i class="fas fa-clock text-info"></i> Paiement en attente'
                                    ];
                                    echo $statusIcons[$status] ?? '<i class="fas fa-question-circle"></i> Statut inconnu';
                                    ?>
                                </h5>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong>École :</strong><br>
                                        <?php echo htmlspecialchars($subscription['school_name']); ?>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Email :</strong><br>
                                        <?php echo htmlspecialchars($subscription['admin_email']); ?>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong>Date d'expiration :</strong><br>
                                        <?php echo date('d/m/Y', strtotime($subscription['expiry_date'])); ?>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Jours restants :</strong><br>
                                        <?php 
                                        $days = $subscription_info['days_until_expiry'];
                                        if ($days < 0) {
                                            echo '<span class="text-danger">' . abs($days) . ' jour(s) en retard</span>';
                                        } elseif ($days <= 5) {
                                            echo '<span class="text-warning">' . $days . ' jour(s)</span>';
                                        } else {
                                            echo '<span class="text-success">' . $days . ' jour(s)</span>';
                                        }
                                        ?>
                                    </div>
                                </div>
                                
                                <div class="mt-4">
                                    <?php if ($subscription_info['can_renew']): ?>
                                        <a href="module/subscription/renew.php?email=<?php echo urlencode($subscription['admin_email']); ?>" class="btn btn-warning me-2">
                                            <i class="fas fa-sync-alt"></i> Renouveler mon abonnement
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($status === 'active'): ?>
                                        <a href="login.php" class="btn btn-success">
                                            <i class="fas fa-sign-in-alt"></i> Se connecter
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="check_subscription.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-search"></i> Nouvelle vérification
                                    </a>
                                </div>
                            </div>
                        <?php elseif (!isset($_GET['token'])): ?>
                            <!-- Formulaire de demande de vérification -->
                            <div class="text-center mb-4">
                                <p class="lead">Vérifiez votre abonnement en toute sécurité</p>
                                <p class="text-muted">Entrez votre adresse email et nous vous enverrons un lien de vérification sécurisé.</p>
                            </div>
                            
                            <?php echo $checker->renderSecureChecker(); ?>
                            
                            <div class="mt-4">
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-info-circle"></i> Comment ça fonctionne ?</h6>
                                    <ul class="mb-0 small">
                                        <li>Entrez votre adresse email associée à votre abonnement</li>
                                        <li>Vous recevrez un email avec un lien de vérification sécurisé</li>
                                        <li>Cliquez sur le lien pour voir les informations de votre abonnement</li>
                                        <li>Le lien est valide pendant 1 heure</li>
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
                                <p class="text-muted small">Créez un nouveau compte</p>
                                <a href="module/subscription/register.php" class="btn btn-success btn-sm">
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
                                <p class="text-muted small">Accédez à votre espace</p>
                                <a href="login.php" class="btn btn-primary btn-sm">
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
                                <p class="text-muted small">Consultez la documentation</p>
                                <a href="documentation/index.php" class="btn btn-info btn-sm">
                                    Documentation
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

