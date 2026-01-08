<?php
/**
 * Script cron pour vérifier et mettre à jour les abonnements expirés
 * À exécuter quotidiennement
 */

require_once __DIR__ . '/../service/mysqlcon.php';
require_once __DIR__ . '/../service/ExpirationManager.php';

// Log du début d'exécution
error_log("=== Début de la vérification des abonnements expirés - " . date('Y-m-d H:i:s') . " ===");

try {
    $expirationManager = new ExpirationManager($link);
    
    // 1. Vérifier et mettre à jour les abonnements expirés
    $expired_result = $expirationManager->checkAndUpdateExpiredSubscriptions();
    
    if ($expired_result['success']) {
        error_log("✅ " . $expired_result['message']);
        
        // Log des abonnements expirés
        if ($expired_result['expired_count'] > 0) {
            foreach ($expired_result['expired_subscriptions'] as $subscription) {
                error_log("   - Abonnement ID {$subscription['id']} ({$subscription['school_name']}) expiré le {$subscription['expiry_date']}");
            }
        }
    } else {
        error_log("❌ Erreur lors de la vérification des abonnements expirés: " . $expired_result['message']);
    }
    
    // 2. Vérifier les abonnements qui vont expirer bientôt
    $upcoming_result = $expirationManager->checkUpcomingExpirations();
    
    if ($upcoming_result['success']) {
        error_log("✅ " . $upcoming_result['message']);
        
        // Log des abonnements qui vont expirer
        if ($upcoming_result['upcoming_count'] > 0) {
            foreach ($upcoming_result['upcoming_subscriptions'] as $subscription) {
                error_log("   - Abonnement ID {$subscription['id']} ({$subscription['school_name']}) expire dans {$subscription['days_until_expiry']} jour(s)");
            }
        }
    } else {
        error_log("❌ Erreur lors de la vérification des expirations proches: " . $upcoming_result['message']);
    }
    
    // 3. Obtenir les statistiques
    $stats_result = $expirationManager->getSubscriptionStats();
    
    if ($stats_result['success']) {
        $stats = $stats_result['stats'];
        error_log("📊 Statistiques des abonnements:");
        error_log("   - Actifs: {$stats['active']}");
        error_log("   - Expirés: {$stats['expired']}");
        error_log("   - En attente: {$stats['pending']}");
    }
    
    error_log("=== Fin de la vérification des abonnements expirés - " . date('Y-m-d H:i:s') . " ===");
    
    // Retourner un statut de succès
    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'expired_count' => $expired_result['expired_count'] ?? 0,
        'upcoming_count' => $upcoming_result['upcoming_count'] ?? 0,
        'stats' => $stats_result['stats'] ?? []
    ]);
    
} catch (Exception $e) {
    error_log("❌ Erreur fatale dans le script d'expiration: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>

