<?php
/**
 * Fonction pour corriger automatiquement les abonnements expirés
 * Cette fonction vérifie et met à jour les abonnements dont la date d'expiration est passée
 * mais qui sont encore marqués comme 'completed'
 * 
 * Utilise un mécanisme de cache pour éviter d'exécuter la requête trop souvent
 */

// Fonction pour corriger automatiquement les abonnements expirés
function autoFixExpiredSubscriptions($db) {
    static $last_check = 0;
    static $cache_duration = 60; // Vérifier au maximum une fois par minute
    
    // Vérifier si on doit exécuter la vérification (éviter trop de requêtes)
    $current_time = time();
    if (($current_time - $last_check) < $cache_duration) {
        return; // Ne pas vérifier trop souvent
    }
    
    $last_check = $current_time;
    
    try {
        // Vérifier et corriger les abonnements expirés en une seule requête
        // Utiliser une transaction pour garantir la cohérence
        $db->begin_transaction();
        
        // Mettre à jour directement les abonnements expirés
        $update_stmt = $db->prepare("
            UPDATE subscriptions 
            SET payment_status = 'expired',
                updated_at = NOW()
            WHERE payment_status = 'completed' 
            AND expiry_date < NOW()
            AND expiry_date != '0000-00-00 00:00:00'
            AND expiry_date IS NOT NULL
        ");
        
        $update_stmt->execute();
        $affected_rows = $update_stmt->affected_rows;
        
        $db->commit();
        
        // Logger seulement si des abonnements ont été corrigés
        if ($affected_rows > 0) {
            error_log("AUTO-FIX: {$affected_rows} abonnement(s) expiré(s) corrigé(s) automatiquement");
        }
        
    } catch (Exception $e) {
        // En cas d'erreur, rollback et logger silencieusement
        $db->rollback();
        error_log("AUTO-FIX ERROR: Erreur lors de la correction automatique des abonnements expirés: " . $e->getMessage());
    }
}

