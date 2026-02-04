<?php

// SEO (page publique)
$page_title = 'Renouvellement d'abonnement - SchoolManager';
$page_description = 'Plateforme SchoolManager pour la gestion scolaire.';
$robots = 'index, follow';
$include_google_verification = false;
/**
 * Page de renouvellement d'abonnement - Version simplifiée
 */

session_start();
require_once __DIR__ . '/../../service/mysqlcon.php';
require_once __DIR__ . '/../../service/SubscriptionService.php';
require_once __DIR__ . '/../../service/SubscriptionDetector.php';

$error_message = '';
$success_message = '';
$subscription = null;

// Détecter l'abonnement
$detector = new SubscriptionDetector($link);

if (isset($_GET['email'])) {
    $email = urldecode($_GET['email']);
    $detection = $detector->detectSubscriptionStatus($email);
    
    if ($detection['exists']) {
        $subscription = $detection['subscription'];
    } else {
        $error_message = "Aucun abonnement trouvé pour cet email.";
    }
} else {
    $error_message = "Email requis pour le renouvellement.";
}

// Traiter le renouvellement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['renew']) && $subscription) {
    try {
        $subscriptionService = new SubscriptionService($link);
        $renewal = $subscriptionService->processRenewal($subscription['id']);
        
        if ($renewal['success']) {
            $success_message = "Redirection vers le paiement...";
            header("Location: " . $renewal['payment_url']);
            exit;
        } else {
            $error_message = $renewal['message'];
        }
    } catch (Exception $e) {
        $error_message = "Erreur lors du renouvellement: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renouvellement d'abonnement - SchoolManager</title>
    <?php require_once __DIR__ . '/../../seo.php'; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    Renouvellement d'abonnement
                </h2>
            </div>

            <?php if ($error_message): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($subscription): ?>
                <div class="bg-white shadow rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        <i class="fas fa-school mr-2"></i>
                        Informations de l'abonnement
                    </h3>
                    
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">École:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($subscription['school_name']); ?></span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-gray-600">Email:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($subscription['admin_email']); ?></span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-gray-600">Statut:</span>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                Expiré
                            </span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-gray-600">Montant:</span>
                            <span class="font-bold text-lg text-green-600">
                                <?php echo number_format($subscription['amount'], 0, ',', ' '); ?> FCFA
                            </span>
                        </div>
                    </div>
                    
                    <form method="POST" class="mt-6">
                        <button type="submit" name="renew" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-credit-card mr-2"></i>
                            Renouveler mon abonnement
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="bg-white shadow rounded-lg p-6 text-center">
                    <i class="fas fa-exclamation-triangle text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Aucun abonnement trouvé</h3>
                    <p class="text-gray-600 mb-4">Veuillez vérifier vos informations ou créer un nouvel abonnement.</p>
                    <div class="space-y-2">
                        <a href="../subscription/register.php" class="block w-full bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700">
                            Créer un nouvel abonnement
                        </a>
                        <a href="../../login.php" class="block w-full bg-gray-600 text-white py-2 px-4 rounded hover:bg-gray-700">
                            Se connecter
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
