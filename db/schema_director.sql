

drop table director_actions;
drop table director;

-- Création de la table director
CREATE TABLE IF NOT EXISTS director (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    admin_id INT NOT NULL,
    firstname VARCHAR(50) NOT NULL,
    lastname VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (admin_id) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Création de la table director_actions
CREATE TABLE IF NOT EXISTS director_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    director_id INT NOT NULL,
    firstname VARCHAR(50) NOT NULL,
    lastname VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    action_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    details TEXT,
    FOREIGN KEY (director_id) REFERENCES director(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);
    FOREIGN KEY (created_by) REFERENCES users(id)
);

drop table comptable;
-- Création de la table comptable
CREATE TABLE IF NOT EXISTS comptable (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    admin_id INT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (admin_id) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

drop table director_actions;
-- Création de la table pour les actions du directeur
CREATE TABLE IF NOT EXISTS director_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    director_id INT NOT NULL,
    email VARCHAR(100) NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    action_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    details TEXT,
    FOREIGN KEY (director_id) REFERENCES director(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);
