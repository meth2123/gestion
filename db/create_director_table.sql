-- Création de la table director
CREATE TABLE IF NOT EXISTS director (
    director_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(userid)
);

-- Création de la table comptable
CREATE TABLE IF NOT EXISTS comptable (
    comptable_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(userid)
);

-- Création de la table pour les actions du directeur
CREATE TABLE IF NOT EXISTS director_actions (
    action_id INT PRIMARY KEY AUTO_INCREMENT,
    director_id INT NOT NULL,
    action_type VARCHAR(50),
    action_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    details TEXT,
    FOREIGN KEY (director_id) REFERENCES director(director_id)
);
