<?php
/**
 * Service pour gérer les salons de chat
 * Crée automatiquement les salons de chat manquants pour les classes
 */

require_once __DIR__ . '/mysqlcon.php';
require_once __DIR__ . '/db_utils.php';

/**
 * Crée automatiquement les salons de chat manquants pour toutes les classes
 * @param mysqli $link Connexion à la base de données
 * @return array ['created' => nombre créé, 'total' => total de classes, 'errors' => tableau d'erreurs]
 */
function create_missing_chat_rooms($link) {
    $result = [
        'created' => 0,
        'total' => 0,
        'errors' => []
    ];
    
    try {
        // Vérifier si la table chat_rooms existe
        $check_table = $link->query("SHOW TABLES LIKE 'chat_rooms'");
        if (!$check_table || $check_table->num_rows == 0) {
            $result['errors'][] = "La table chat_rooms n'existe pas. Veuillez d'abord exécuter le script sql/chat_tables.sql";
            return $result;
        }

        // Trouver toutes les classes qui n'ont pas de salon de chat
        $sql = "
            SELECT c.id, c.name, c.section
            FROM class c
            LEFT JOIN chat_rooms cr ON c.id = cr.class_id AND cr.is_class_room = 1
            WHERE cr.id IS NULL
            ORDER BY c.name, c.section
        ";
        
        $query_result = $link->query($sql);
        
        if (!$query_result) {
            throw new Exception("Erreur lors de la requête : " . $link->error);
        }
        
        $classes_without_chat = [];
        while ($row = $query_result->fetch_assoc()) {
            $classes_without_chat[] = $row;
        }
        
        $result['total'] = count($classes_without_chat);
        
        if (count($classes_without_chat) == 0) {
            return $result; // Tous les salons existent déjà
        }
        
        // Créer les salons manquants
        $link->begin_transaction();
        
        try {
            foreach ($classes_without_chat as $class) {
                // Vérifier à nouveau si le salon n'existe pas (au cas où)
                $check_chat = $link->prepare("SELECT id FROM chat_rooms WHERE class_id = ? AND is_class_room = 1");
                $check_chat->bind_param("s", $class['id']);
                $check_chat->execute();
                $chat_result = $check_chat->get_result();
                
                if ($chat_result->num_rows == 0) {
                    // Créer le salon de chat
                    $chat_sql = "INSERT INTO chat_rooms (name, class_id, description, is_class_room) VALUES (?, ?, ?, 1)";
                    $chat_stmt = $link->prepare($chat_sql);
                    if ($chat_stmt) {
                        $chat_name = "Chat de la classe " . $class['name'];
                        $chat_description = "Salon de discussion pour les étudiants de la classe " . $class['name'];
                        $chat_stmt->bind_param("sss", $chat_name, $class['id'], $chat_description);
                        
                        if ($chat_stmt->execute()) {
                            $result['created']++;
                        } else {
                            $result['errors'][] = "Erreur lors de la création du salon pour la classe {$class['name']}: " . $chat_stmt->error;
                        }
                        $chat_stmt->close();
                    } else {
                        $result['errors'][] = "Erreur lors de la préparation de la requête pour la classe {$class['name']}: " . $link->error;
                    }
                }
                $check_chat->close();
            }
            
            $link->commit();
            
        } catch (Exception $e) {
            $link->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        $result['errors'][] = $e->getMessage();
    }
    
    return $result;
}

/**
 * Crée un salon de chat pour une classe spécifique
 * @param mysqli $link Connexion à la base de données
 * @param string $class_id ID de la classe
 * @param string $class_name Nom de la classe
 * @return bool True si créé avec succès, False sinon
 */
function create_chat_room_for_class($link, $class_id, $class_name) {
    try {
        // Vérifier si la table chat_rooms existe
        $check_table = $link->query("SHOW TABLES LIKE 'chat_rooms'");
        if (!$check_table || $check_table->num_rows == 0) {
            error_log("La table chat_rooms n'existe pas");
            return false;
        }
        
        // Vérifier si un salon existe déjà pour cette classe
        $check_chat = $link->prepare("SELECT id FROM chat_rooms WHERE class_id = ? AND is_class_room = 1");
        $check_chat->bind_param("s", $class_id);
        $check_chat->execute();
        $chat_result = $check_chat->get_result();
        
        if ($chat_result->num_rows > 0) {
            $check_chat->close();
            return true; // Le salon existe déjà
        }
        $check_chat->close();
        
        // Créer le salon de chat
        $chat_sql = "INSERT INTO chat_rooms (name, class_id, description, is_class_room) VALUES (?, ?, ?, 1)";
        $chat_stmt = $link->prepare($chat_sql);
        if ($chat_stmt) {
            $chat_name = "Chat de la classe " . $class_name;
            $chat_description = "Salon de discussion pour les étudiants de la classe " . $class_name;
            $chat_stmt->bind_param("sss", $chat_name, $class_id, $chat_description);
            
            $success = $chat_stmt->execute();
            $chat_stmt->close();
            
            return $success;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Erreur lors de la création du salon de chat pour la classe {$class_id}: " . $e->getMessage());
        return false;
    }
}

