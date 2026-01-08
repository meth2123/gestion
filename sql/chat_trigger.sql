-- Créer un déclencheur pour créer automatiquement un salon de chat pour chaque nouvelle classe
DELIMITER //

-- Supprimer le déclencheur s'il existe déjà
DROP TRIGGER IF EXISTS after_class_insert//

-- Créer le déclencheur
CREATE TRIGGER after_class_insert
AFTER INSERT ON class
FOR EACH ROW
BEGIN
    -- Insérer un nouveau salon de chat pour la classe
    INSERT INTO chat_rooms (name, class_id, description, is_class_room)
    VALUES (
        CONCAT('Chat de la classe ', NEW.name),
        NEW.id,
        CONCAT('Salon de discussion pour les étudiants de la classe ', NEW.name),
        TRUE
    );
END//

DELIMITER ;
