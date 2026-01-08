-- Mettre à jour la table des notifications pour inclure le type 'parent'
ALTER TABLE notifications 
MODIFY COLUMN user_type ENUM('admin', 'teacher', 'student', 'parent') NOT NULL COMMENT 'Type d\'utilisateur';
