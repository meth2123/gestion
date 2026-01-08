-- Table pour les salons de chat (par classe)
CREATE TABLE chat_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    class_id VARCHAR(20) NULL,
    description TEXT NULL,
    is_class_room BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table pour les messages
CREATE TABLE chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    sender_id VARCHAR(20) NOT NULL,
    sender_type ENUM('student', 'teacher') NOT NULL,
    message TEXT NOT NULL,
    has_attachment BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE
);

-- Table pour les pièces jointes
CREATE TABLE chat_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    original_file_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE
);

-- Table pour les participants des salons de chat
CREATE TABLE chat_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    user_id VARCHAR(20) NOT NULL,
    user_type ENUM('student', 'teacher') NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
    UNIQUE KEY (room_id, user_id, user_type)
);

-- Créer automatiquement un salon de chat pour chaque classe existante
INSERT INTO chat_rooms (name, class_id, description, is_class_room)
SELECT CONCAT('Chat de la classe ', name), id, CONCAT('Salon de discussion pour les étudiants de la classe ', name), TRUE
FROM class;
