<?php

// SEO (page publique)
$page_title = 'Tableau de Bord - Mon Abonnement';
$page_description = 'Plateforme SchoolManager pour la gestion scolaire.';
$robots = 'index, follow';
$include_google_verification = false;
session_start();
require_once __DIR__ . '/../../service/mysqlcon.php';
require_once __DIR__ . '/../../service/SubscriptionDetector.php';
require_once __DIR__ . '/../../service/SubscriptionService.php';

$error_message = '';
$success_message = '';
$subscription = null;
$renewals = [];
$notifications = [];

// Initialiser les services
$detector = new SubscriptionDetector($link);
$subscriptionService = new SubscriptionService($link);

// Détecter l'abonnement de l'utilisateur connecté
$detection = null;
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    $detection = $detector->detectForLoggedUser();
    if ($detection['exists']) {
        $subscription = $detection['subscription'];
    }
}

// Si pas d'abonnement trouvé, rediriger vers l'inscription
if (!$subscription) {
    header("Location: register.php");
    exit;
}

// Récupérer l'historique des renouvellements
try {
    $stmt = $link->prepare("
        SELECT * FROM subscription_renewals 
        WHERE subscription_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->bind_param("i", $subscription['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $renewals[] = $row;
    }
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des renouvellements : " . $e->getMessage());
}

// Récupérer les notifications
try {
    $stmt = $link->prepare("
        SELECT * FROM subscription_notifications 
        WHERE subscription_id = ? 
        ORDER BY sent_at DESC 
        LIMIT 10
    ");
    $stmt->bind_param("i", $subscription['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des notifications : " . $e->getMessage());
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'renew':
                if ($detection['can_renew']) {
                    try {
                        $payment = $subscriptionService->processRenewal($subscription['id']);
                        if ($payment['success']) {
                            header("Location: " . $payment['invoice_url']);
                            exit;
                        } else {
                            $error_message = "Erreur lors de la création du paiement de renouvellement.";
                        }
                    } catch (Exception $e) {
                        $error_message = "Erreur lors du renouvellement : " . $e->getMessage();
                    }
                } else {
                    $error_message = "Votre abonnement ne peut pas être renouvelé pour le moment.";
                }
                break;
                
            case 'mark_notification_read':
                $notification_id = $_POST['notification_id'];
                try {
                    $stmt = $link->prepare("
                        UPDATE subscription_notifications 
                        SET read_at = NOW() 
                        WHERE id = ? AND subscription_id = ?
                    ");
                    $stmt->bind_param("ii", $notification_id, $subscription['id']);
                    $stmt->execute();
                    $success_message = "Notification marquée comme lue.";
                } catch (Exception $e) {
                    $error_message = "Erreur lors de la mise à jour de la notification.";
                }
                break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - Mon Abonnement</title>
    <?php require_once __DIR__ . '/../../seo.php'; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Navigation -->
        <nav class="bg-white shadow-sm border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <a href="../../index.php" class="flex items-center">
                            <img src="../../source/logo.jpg" class="h-8 w-8 object-contain" alt="Logo"/>
                            <span class="ml-2 text-xl font-bold text-gray-900">SchoolManager</span>
                        </a>
                    </div>
                    <div class="flex items-center space-x-4">
                        <a href="../../index.php" class="text-gray-600 hover:text-gray-900">
                            <i class="fas fa-home mr-1"></i>Accueil
                        </a>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="../../logout.php" class="text-red-600 hover:text-red-700">
                                <i class="fas fa-sign-out-alt mr-1"></i>Déconnexion
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <!-- Messages -->
            <?php if ($error_message): ?>
                <div class="rounded-md bg-red-50 p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-400"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">
                                <?php echo htmlspecialchars($error_message); ?>
                            </h3>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="rounded-md bg-green-50 p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-green-400"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-green-800">
                                <?php echo htmlspecialchars($success_message); ?>
                            </h3>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- En-tête -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Mon Abonnement</h1>
                <p class="mt-2 text-sm text-gray-600">Gérez votre abonnement SchoolManager</p>
            </div>

            <!-- Informations principales -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <!-- Statut de l'abonnement -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <?php
                                $statusIcon = [
                                    'active' => 'fas fa-check-circle text-green-500',
                                    'expired' => 'fas fa-exclamation-triangle text-red-500',
                                    'pending' => 'fas fa-clock text-yellow-500',
                                    'failed' => 'fas fa-times-circle text-red-500'
                                ][$detection['status']] ?? 'fas fa-question-circle text-gray-500';
                                ?>
                                <i class="<?php echo $statusIcon; ?> text-2xl"></i>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                        Statut
                                    </dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        <?php
                                        $statusText = [
                                            'active' => 'Actif',
                                            'expired' => 'Expiré',
                                            'pending' => 'En attente',
                                            'failed' => 'Échoué'
                                        ][$detection['status']] ?? 'Inconnu';
                                        echo $statusText;
                                        ?>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Date d'expiration -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-calendar-alt text-blue-500 text-2xl"></i>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                        Expiration
                                    </dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        <?php echo date('d/m/Y', strtotime($subscription['expiry_date'])); ?>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Jours restants -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <?php
                                $daysIcon = $detection['days_until_expiry'] < 0 ? 'fas fa-exclamation-triangle text-red-500' : 
                                           ($detection['days_until_expiry'] <= 7 ? 'fas fa-clock text-orange-500' : 'fas fa-calendar-check text-green-500');
                                ?>
                                <i class="<?php echo $daysIcon; ?> text-2xl"></i>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                        Jours restants
                                    </dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        <?php 
                                        if ($detection['days_until_expiry'] < 0) {
                                            echo abs($detection['days_until_expiry']) . ' jour(s) en retard';
                                        } else {
                                            echo $detection['days_until_expiry'] . ' jour(s)';
                                        }
                                        ?>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="bg-white shadow rounded-lg mb-8">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        Actions disponibles
                    </h3>
                    <div class="flex flex-wrap gap-4">
                        <?php if ($detection['can_renew']): ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="renew">
                                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                    <i class="fas fa-sync-alt mr-2"></i>
                                    Renouveler mon abonnement
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <a href="renew.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-info-circle mr-2"></i>
                            Détails du renouvellement
                        </a>
                        
                        <a href="register.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-plus-circle mr-2"></i>
                            Nouvel abonnement
                        </a>
                    </div>
                </div>
            </div>

            <!-- Détails de l'abonnement -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-8">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        Détails de l'abonnement
                    </h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">
                        Informations complètes sur votre abonnement
                    </p>
                </div>
                <div class="border-t border-gray-200">
                    <dl>
                        <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">
                                École
                            </dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                <?php echo htmlspecialchars($subscription['school_name']); ?>
                            </dd>
                        </div>
                        <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">
                                Email administrateur
                            </dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                <?php echo htmlspecialchars($subscription['admin_email']); ?>
                            </dd>
                        </div>
                        <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">
                                Téléphone
                            </dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                <?php echo htmlspecialchars($subscription['admin_phone']); ?>
                            </dd>
                        </div>
                        <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">
                                Date de création
                            </dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                <?php echo date('d/m/Y H:i', strtotime($subscription['created_at'])); ?>
                            </dd>
                        </div>
                        <?php if ($subscription['payment_method']): ?>
                        <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">
                                Méthode de paiement
                            </dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                <?php echo ucfirst($subscription['payment_method']); ?>
                            </dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>

            <!-- Historique des renouvellements -->
            <?php if (!empty($renewals)): ?>
            <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-8">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        Historique des renouvellements
                    </h3>
                </div>
                <div class="border-t border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Montant</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Référence</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($renewals as $renewal): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo date('d/m/Y H:i', strtotime($renewal['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo number_format($renewal['amount'], 0, ',', ' '); ?> FCFA
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $statusClass = [
                                            'completed' => 'bg-green-100 text-green-800',
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'failed' => 'bg-red-100 text-red-800'
                                        ][$renewal['payment_status']] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                            <?php echo ucfirst($renewal['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($renewal['payment_reference'] ?? '-'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Notifications -->
            <?php if (!empty($notifications)): ?>
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        Notifications récentes
                    </h3>
                </div>
                <div class="border-t border-gray-200">
                    <ul class="divide-y divide-gray-200">
                        <?php foreach ($notifications as $notification): ?>
                            <li class="px-6 py-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center">
                                            <?php
                                            $typeIcon = [
                                                'expiry_warning' => 'fas fa-exclamation-triangle text-yellow-500',
                                                'payment_failed' => 'fas fa-times-circle text-red-500',
                                                'renewal_success' => 'fas fa-check-circle text-green-500',
                                                'renewal_failed' => 'fas fa-times-circle text-red-500'
                                            ][$notification['type']] ?? 'fas fa-info-circle text-blue-500';
                                            ?>
                                            <i class="<?php echo $typeIcon; ?> mr-3"></i>
                                            <div>
                                                <p class="text-sm font-medium text-gray-900">
                                                    <?php echo str_replace('_', ' ', ucfirst($notification['type'])); ?>
                                                </p>
                                                <p class="text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($notification['message']); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="text-xs text-gray-500">
                                            <?php echo date('d/m/Y H:i', strtotime($notification['sent_at'])); ?>
                                        </span>
                                        <?php if (!$notification['read_at']): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="mark_notification_read">
                                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                <button type="submit" class="text-xs text-blue-600 hover:text-blue-800">
                                                    Marquer comme lu
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-xs text-green-600">
                                                <i class="fas fa-check mr-1"></i>Lu
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
