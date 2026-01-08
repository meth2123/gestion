<?php
/**
 * Script d'installation de la table de vérification
 * Exécutez ce script une fois pour créer la table nécessaire
 */

require_once __DIR__ . '/service/mysqlcon.php';

try {
    // Créer la table si elle n'existe pas
    $sql = "
    CREATE TABLE IF NOT EXISTS subscription_verification_tokens (
        id INT PRIMARY KEY AUTO_INCREMENT,
        email VARCHAR(255) NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used BOOLEAN DEFAULT FALSE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token (token),
        INDEX idx_email (email),
        INDEX idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    if ($link->multi_query($sql)) {
        do {
            if ($result = $link->store_result()) {
                $result->free();
            }
        } while ($link->next_result());
    }
    
    echo "✅ Table 'subscription_verification_tokens' créée avec succès !\n";
    echo "Vous pouvez maintenant utiliser le système de vérification par email.\n";
    
} catch (Exception $e) {
    echo "❌ Erreur lors de la création de la table : " . $e->getMessage() . "\n";
}

$link->close();
?>

