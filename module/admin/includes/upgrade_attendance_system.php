<?php
/**
 * Script de migration pour améliorer le système de présence
 * - Ajoute les colonnes nécessaires à la table attendance
 * - Crée la table student_attendance pour les élèves
 * - Migre les données existantes
 */

// Utiliser la connexion $link existante si disponible
global $link;
if (isset($link) && $link !== null) {
    $conn = $link;
} else {
    require_once(__DIR__ . '/../../../service/mysqlcon.php');
    $conn = $link;
    
    if ($conn === null || !$conn) {
        die('Erreur de connexion à la base de données.');
    }
}

try {
    echo "<h2>Migration du système de présence</h2>";
    echo "<ul>";
    
    // 1. Vérifier et ajouter la colonne person_type
    $check_column = "SHOW COLUMNS FROM attendance LIKE 'person_type'";
    $result = $conn->query($check_column);
    
    if ($result->num_rows == 0) {
        $alter_table = "ALTER TABLE attendance ADD COLUMN person_type ENUM('teacher', 'staff', 'student') NULL AFTER attendedid";
        if ($conn->query($alter_table)) {
            echo "<li>✅ Colonne person_type ajoutée avec succès</li>";
            
            // Déterminer le type basé sur le préfixe de attendedid
            $update_teachers = "UPDATE attendance SET person_type = 'teacher' WHERE attendedid LIKE 'te-%' OR attendedid LIKE 'TE-%'";
            $conn->query($update_teachers);
            
            $update_staff = "UPDATE attendance SET person_type = 'staff' WHERE attendedid LIKE 'sta-%' OR attendedid LIKE 'STF%' OR attendedid LIKE 'ST-%'";
            $conn->query($update_staff);
            
            $update_students = "UPDATE attendance SET person_type = 'student' WHERE attendedid LIKE 'st%' AND person_type IS NULL";
            $conn->query($update_students);
            
            echo "<li>✅ Types de personnes mis à jour</li>";
        } else {
            throw new Exception("Erreur lors de l'ajout de person_type: " . $conn->error);
        }
    } else {
        echo "<li>ℹ️ Colonne person_type existe déjà</li>";
    }
    
    // 2. Vérifier et ajouter la colonne course_id
    $check_column = "SHOW COLUMNS FROM attendance LIKE 'course_id'";
    $result = $conn->query($check_column);
    
    if ($result->num_rows == 0) {
        $alter_table = "ALTER TABLE attendance ADD COLUMN course_id INT NULL AFTER person_type";
        if ($conn->query($alter_table)) {
            echo "<li>✅ Colonne course_id ajoutée avec succès</li>";
        } else {
            throw new Exception("Erreur lors de l'ajout de course_id: " . $conn->error);
        }
    } else {
        echo "<li>ℹ️ Colonne course_id existe déjà</li>";
    }
    
    // 3. Vérifier et ajouter la colonne time_slot_id
    $check_column = "SHOW COLUMNS FROM attendance LIKE 'time_slot_id'";
    $result = $conn->query($check_column);
    
    if ($result->num_rows == 0) {
        $alter_table = "ALTER TABLE attendance ADD COLUMN time_slot_id INT NULL AFTER course_id";
        if ($conn->query($alter_table)) {
            echo "<li>✅ Colonne time_slot_id ajoutée avec succès</li>";
        } else {
            throw new Exception("Erreur lors de l'ajout de time_slot_id: " . $conn->error);
        }
    } else {
        echo "<li>ℹ️ Colonne time_slot_id existe déjà</li>";
    }
    
    // 4. Vérifier si la colonne datetime existe, sinon renommer date en datetime
    $check_datetime = "SHOW COLUMNS FROM attendance WHERE Field = 'datetime'";
    $result = $conn->query($check_datetime);
    
    if ($result->num_rows == 0) {
        // Vérifier si la colonne date existe
        $check_date = "SHOW COLUMNS FROM attendance WHERE Field = 'date'";
        $date_result = $conn->query($check_date);
        
        if ($date_result && $date_result->num_rows > 0) {
            $column_info = $date_result->fetch_assoc();
            // Renommer date en datetime et convertir en DATETIME
            $alter_table = "ALTER TABLE attendance CHANGE COLUMN date datetime DATETIME NOT NULL";
            if ($conn->query($alter_table)) {
                echo "<li>✅ Colonne date renommée en datetime et convertie en DATETIME</li>";
                
                // Convertir les dates existantes (ajouter 00:00:00 si nécessaire)
                $update_dates = "UPDATE attendance SET datetime = CONCAT(datetime, ' 00:00:00') WHERE datetime NOT LIKE '%:%'";
                $conn->query($update_dates);
                echo "<li>✅ Dates existantes mises à jour</li>";
            } else {
                throw new Exception("Erreur lors de la conversion: " . $conn->error);
            }
        } else {
            // Si date n'existe pas, créer datetime
            $alter_table = "ALTER TABLE attendance ADD COLUMN datetime DATETIME NOT NULL AFTER id";
            if ($conn->query($alter_table)) {
                echo "<li>✅ Colonne datetime ajoutée</li>";
            } else {
                throw new Exception("Erreur lors de l'ajout de datetime: " . $conn->error);
            }
        }
    } else {
        echo "<li>ℹ️ Colonne datetime existe déjà</li>";
    }
    
    // 5. Vérifier et ajouter la colonne comment
    $check_column = "SHOW COLUMNS FROM attendance LIKE 'comment'";
    $result = $conn->query($check_column);
    
    if ($result->num_rows == 0) {
        $alter_table = "ALTER TABLE attendance ADD COLUMN comment TEXT NULL AFTER status";
        if ($conn->query($alter_table)) {
            echo "<li>✅ Colonne comment ajoutée avec succès</li>";
        } else {
            throw new Exception("Erreur lors de l'ajout de comment: " . $conn->error);
        }
    } else {
        echo "<li>ℹ️ Colonne comment existe déjà</li>";
    }
    
    // 6. Créer la table student_attendance pour les élèves
    $create_student_attendance = "
    CREATE TABLE IF NOT EXISTS student_attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(20) NOT NULL,
        course_id INT NOT NULL,
        class_id VARCHAR(20) NOT NULL,
        datetime DATETIME NOT NULL,
        status ENUM('present', 'absent', 'late', 'excused') NOT NULL DEFAULT 'present',
        comment TEXT NULL,
        created_by VARCHAR(50) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_student (student_id),
        INDEX idx_course (course_id),
        INDEX idx_class (class_id),
        INDEX idx_datetime (datetime),
        INDEX idx_student_course_date (student_id, course_id, datetime),
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES course(id) ON DELETE CASCADE,
        FOREIGN KEY (class_id) REFERENCES class(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($create_student_attendance)) {
        echo "<li>✅ Table student_attendance créée avec succès</li>";
    } else {
        // Si la table existe déjà, vérifier les colonnes
        if (strpos($conn->error, 'already exists') === false) {
            throw new Exception("Erreur lors de la création de student_attendance: " . $conn->error);
        } else {
            echo "<li>ℹ️ Table student_attendance existe déjà</li>";
        }
    }
    
    // 7. Ajouter un index unique pour éviter les doublons (personne + cours + date/heure)
    // Note: On ne peut pas créer un index unique si course_id peut être NULL
    // On crée plutôt un index composite sans contrainte unique
    $check_index = "SHOW INDEX FROM attendance WHERE Key_name = 'idx_person_course_datetime'";
    $result = $conn->query($check_index);
    
    if (!$result || $result->num_rows == 0) {
        $add_index = "ALTER TABLE attendance ADD INDEX idx_person_course_datetime (attendedid, course_id, datetime)";
        if ($conn->query($add_index)) {
            echo "<li>✅ Index ajouté pour améliorer les performances</li>";
        } else {
            echo "<li>⚠️ Index non ajouté: " . $conn->error . "</li>";
        }
    } else {
        echo "<li>ℹ️ Index existe déjà</li>";
    }
    
    echo "</ul>";
    echo "<p><strong>✅ Migration terminée avec succès !</strong></p>";
    echo "<p>Le système de présence est maintenant prêt à être utilisé avec les cours et horaires.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>❌ Erreur:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Ne pas fermer la connexion si elle est partagée
if (!isset($link) || $link === null) {
    $conn->close();
}
?>

