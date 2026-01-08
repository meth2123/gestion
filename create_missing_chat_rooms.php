<?php
/**
 * Script pour créer les salons de chat manquants pour les classes existantes
 * À exécuter une fois si le trigger MySQL n'a pas pu être créé
 */

require_once __DIR__ . '/service/mysqlcon.php';

// Démarrer la session si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Création des salons de chat manquants</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">
                    <i class="fas fa-comments"></i> Création des salons de chat manquants
                </h3>
            </div>
            <div class="card-body">
                <?php
                try {
                    // Vérifier si la table chat_rooms existe
                    $check_table = $link->query("SHOW TABLES LIKE 'chat_rooms'");
                    if (!$check_table || $check_table->num_rows == 0) {
                        echo "<div class='alert alert-warning'>";
                        echo "<h5><i class='fas fa-exclamation-triangle'></i> Table chat_rooms non trouvée</h5>";
                        echo "<p>La table chat_rooms n'existe pas. Veuillez d'abord exécuter le script de création des tables de chat.</p>";
                        echo "<p>Fichier à exécuter : <code>sql/chat_tables.sql</code></p>";
                        echo "</div>";
                        exit;
                    }

                    // Trouver toutes les classes qui n'ont pas de salon de chat
                    $sql = "
                        SELECT c.id, c.name, c.section
                        FROM class c
                        LEFT JOIN chat_rooms cr ON c.id = cr.class_id AND cr.is_class_room = 1
                        WHERE cr.id IS NULL
                        ORDER BY c.name, c.section
                    ";
                    
                    $result = $link->query($sql);
                    
                    if (!$result) {
                        throw new Exception("Erreur lors de la requête : " . $link->error);
                    }
                    
                    $classes_without_chat = [];
                    while ($row = $result->fetch_assoc()) {
                        $classes_without_chat[] = $row;
                    }
                    
                    if (count($classes_without_chat) == 0) {
                        echo "<div class='alert alert-success'>";
                        echo "<h5><i class='fas fa-check-circle'></i> Aucune action nécessaire</h5>";
                        echo "<p>Toutes les classes ont déjà un salon de chat associé.</p>";
                        echo "</div>";
                    } else {
                        echo "<div class='alert alert-info'>";
                        echo "<h5><i class='fas fa-info-circle'></i> Classes sans salon de chat</h5>";
                        echo "<p><strong>" . count($classes_without_chat) . "</strong> classe(s) trouvée(s) sans salon de chat.</p>";
                        echo "</div>";
                        
                        echo "<div class='table-responsive'>";
                        echo "<table class='table table-striped'>";
                        echo "<thead><tr><th>ID</th><th>Nom</th><th>Section</th><th>Action</th></tr></thead>";
                        echo "<tbody>";
                        
                        foreach ($classes_without_chat as $class) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($class['id']) . "</td>";
                            echo "<td>" . htmlspecialchars($class['name']) . "</td>";
                            echo "<td>" . htmlspecialchars($class['section']) . "</td>";
                            echo "<td><span class='badge bg-warning'>À créer</span></td>";
                            echo "</tr>";
                        }
                        
                        echo "</tbody></table></div>";
                        
                        // Si on a un paramètre action=create, créer les salons
                        if (isset($_GET['action']) && $_GET['action'] === 'create') {
                            $link->begin_transaction();
                            
                            try {
                                $created_count = 0;
                                
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
                                            $chat_stmt->execute();
                                            $created_count++;
                                        }
                                    }
                                }
                                
                                $link->commit();
                                
                                echo "<div class='alert alert-success mt-3'>";
                                echo "<h5><i class='fas fa-check-circle'></i> Création réussie !</h5>";
                                echo "<p><strong>$created_count</strong> salon(s) de chat créé(s) avec succès.</p>";
                                echo "</div>";
                                
                                // Recharger la page après 2 secondes
                                echo "<script>
                                        setTimeout(function() {
                                            window.location.href = 'create_missing_chat_rooms.php';
                                        }, 2000);
                                      </script>";
                                
                            } catch (Exception $e) {
                                $link->rollback();
                                throw $e;
                            }
                        } else {
                            // Afficher le bouton pour créer
                            echo "<div class='mt-3'>";
                            echo "<a href='?action=create' class='btn btn-primary btn-lg'>";
                            echo "<i class='fas fa-plus-circle'></i> Créer les salons de chat manquants";
                            echo "</a>";
                            echo "</div>";
                        }
                    }
                    
                } catch (Exception $e) {
                    echo "<div class='alert alert-danger'>";
                    echo "<h5><i class='fas fa-exclamation-triangle'></i> Erreur</h5>";
                    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
                    echo "</div>";
                }
                ?>
                
                <div class="mt-4">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-home"></i> Retour à l'accueil
                    </a>
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Information</h5>
            </div>
            <div class="card-body">
                <p>Ce script crée les salons de chat manquants pour les classes existantes.</p>
                <p><strong>Note :</strong> Les nouvelles classes créées via l'interface auront automatiquement leur salon de chat créé (sans trigger MySQL).</p>
            </div>
        </div>
    </div>
</body>
</html>

