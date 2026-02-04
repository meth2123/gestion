<?php
include_once('main.php');
include_once('../../service/mysqlcon.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: markStudentAttendance.php?error=" . urlencode("Méthode non autorisée"));
    exit;
}

// Récupérer teacher_id depuis la session
$teacher_id = null;
if (isset($_SESSION['teacher_id']) && !empty($_SESSION['teacher_id'])) {
    $teacher_id = $_SESSION['teacher_id'];
} elseif (isset($_SESSION['login_id']) && !empty($_SESSION['login_id'])) {
    // Si login_id existe, vérifier si c'est un teacher
    $check_teacher = "SELECT id FROM teachers WHERE CAST(id AS CHAR) = CAST(? AS CHAR) LIMIT 1";
    $check_stmt = $link->prepare($check_teacher);
    if ($check_stmt) {
        $check_stmt->bind_param("s", $_SESSION['login_id']);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $teacher_id = $_SESSION['login_id'];
            $_SESSION['teacher_id'] = $teacher_id; // Mettre à jour la session
        }
        $check_stmt->close();
    }
}

if (!$teacher_id) {
    header("Location: ../../index.php?error=" . urlencode("Session expirée. Veuillez vous reconnecter."));
    exit;
}

$class_id = $_POST['class_id'] ?? '';
$course_id = $_POST['course_id'] ?? '';
$date = $_POST['date'] ?? date('Y-m-d');
$statuses = $_POST['status'] ?? [];
$comments = $_POST['comment'] ?? [];

// Valider et convertir course_id en entier
$course_id = filter_var($course_id, FILTER_VALIDATE_INT);
if ($course_id === false) {
    error_log("Erreur: course_id invalide reçu: " . ($_POST['course_id'] ?? 'vide'));
    header("Location: markStudentAttendance.php?error=" . urlencode("ID de cours invalide"));
    exit;
}

if (empty($class_id) || empty($course_id) || empty($statuses)) {
    error_log("Erreur: Paramètres manquants - class_id: " . ($class_id ?: 'vide') . ", course_id: " . ($course_id ?: 'vide') . ", statuses: " . (count($statuses) ?: 'vide'));
    header("Location: markStudentAttendance.php?error=" . urlencode("Paramètres manquants"));
    exit;
}

error_log("Enregistrement présences - Classe: $class_id, Cours: $course_id, Date: $date, Nombre d'élèves: " . count($statuses));

// Vérifier que le cours appartient bien à cet enseignant
$verify_sql = "SELECT 1 FROM student_teacher_course 
               WHERE CAST(teacher_id AS CHAR) = CAST(? AS CHAR)
               AND CAST(course_id AS CHAR) = CAST(? AS CHAR)
               AND CAST(class_id AS CHAR) = CAST(? AS CHAR)
               LIMIT 1";
$verify_stmt = $link->prepare($verify_sql);
$verify_stmt->bind_param("sss", $teacher_id, $course_id, $class_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    // Vérifier aussi dans la table course directement
    $verify_sql2 = "SELECT 1 FROM course 
                    WHERE id = ? 
                    AND CAST(teacherid AS CHAR) = CAST(? AS CHAR)
                    AND CAST(classid AS CHAR) = CAST(? AS CHAR)
                    LIMIT 1";
    $verify_stmt2 = $link->prepare($verify_sql2);
    $verify_stmt2->bind_param("iss", $course_id, $teacher_id, $class_id);
    $verify_stmt2->execute();
    $verify_result2 = $verify_stmt2->get_result();
    
    if ($verify_result2->num_rows === 0) {
        header("Location: markStudentAttendance.php?error=" . urlencode("Accès non autorisé à ce cours"));
        exit;
    }
    $verify_stmt2->close();
}
$verify_stmt->close();

// Récupérer l'admin_id depuis le cours
$admin_id = null;
$get_admin_sql = "SELECT created_by FROM course WHERE id = ? LIMIT 1";
$get_admin_stmt = $link->prepare($get_admin_sql);
if ($get_admin_stmt) {
    $get_admin_stmt->bind_param("i", $course_id);
    $get_admin_stmt->execute();
    $admin_result = $get_admin_stmt->get_result();
    if ($admin_result && $admin_result->num_rows > 0) {
        $admin_row = $admin_result->fetch_assoc();
        $admin_id = $admin_row['created_by'];
    }
    $get_admin_stmt->close();
}

// Si l'admin_id n'est pas trouvé depuis le cours, essayer depuis la classe
if (!$admin_id) {
    $get_admin_sql2 = "SELECT created_by FROM class WHERE CAST(id AS CHAR) = CAST(? AS CHAR) LIMIT 1";
    $get_admin_stmt2 = $link->prepare($get_admin_sql2);
    if ($get_admin_stmt2) {
        $get_admin_stmt2->bind_param("s", $class_id);
        $get_admin_stmt2->execute();
        $admin_result2 = $get_admin_stmt2->get_result();
        if ($admin_result2 && $admin_result2->num_rows > 0) {
            $admin_row2 = $admin_result2->fetch_assoc();
            $admin_id = $admin_row2['created_by'];
        }
        $get_admin_stmt2->close();
    }
}

if (!$admin_id) {
    error_log("Erreur: Impossible de trouver l'admin_id pour le cours $course_id et la classe $class_id");
    header("Location: markStudentAttendance.php?error=" . urlencode("Erreur: Impossible de déterminer l'administrateur"));
    exit;
}

// R�cup�rer l'heure du cours depuis l'emploi du temps
$day_number = (int)date('N', strtotime($date)); // 1=Lundi, 7=Dimanche
$course_time = null;

// V�rifier si la colonne day_of_week existe
$has_day_of_week = false;
$col_check = $link->query("SHOW COLUMNS FROM class_schedule LIKE 'day_of_week'");
if ($col_check && $col_check->num_rows > 0) {
    $has_day_of_week = true;
}

if ($has_day_of_week) {
    $day_name_en = date('l', strtotime($date));
    $day_name_fr_map = [
        'Monday' => 'Lundi',
        'Tuesday' => 'Mardi',
        'Wednesday' => 'Mercredi',
        'Thursday' => 'Jeudi',
        'Friday' => 'Vendredi',
        'Saturday' => 'Samedi',
        'Sunday' => 'Dimanche'
    ];
    $day_name_fr = $day_name_fr_map[$day_name_en] ?? $day_name_en;

    $schedule_sql = "SELECT ts.start_time
                     FROM class_schedule cs
                     JOIN time_slots ts ON cs.slot_id = ts.slot_id
                     WHERE CAST(cs.class_id AS CHAR) = CAST(? AS CHAR)
                     AND cs.subject_id = ?
                     AND CAST(cs.teacher_id AS CHAR) = CAST(? AS CHAR)
                     AND (cs.day_of_week = ? OR cs.day_of_week = ?)
                     LIMIT 1";
    $schedule_stmt = $link->prepare($schedule_sql);
    if ($schedule_stmt) {
        $schedule_stmt->bind_param("sisss", $class_id, $course_id, $teacher_id, $day_name_en, $day_name_fr);
        $schedule_stmt->execute();
        $schedule_result = $schedule_stmt->get_result();
        if ($schedule_result && $schedule_result->num_rows > 0) {
            $schedule_row = $schedule_result->fetch_assoc();
            $course_time = $schedule_row['start_time'];
        }
        $schedule_stmt->close();
    }
} else {
    $schedule_sql = "SELECT ts.start_time
                     FROM class_schedule cs
                     JOIN time_slots ts ON cs.slot_id = ts.slot_id
                     WHERE CAST(cs.class_id AS CHAR) = CAST(? AS CHAR)
                     AND cs.subject_id = ?
                     AND CAST(cs.teacher_id AS CHAR) = CAST(? AS CHAR)
                     AND ts.day_number = ?
                     LIMIT 1";
    $schedule_stmt = $link->prepare($schedule_sql);
    if ($schedule_stmt) {
        $schedule_stmt->bind_param("sisi", $class_id, $course_id, $teacher_id, $day_number);
        $schedule_stmt->execute();
        $schedule_result = $schedule_stmt->get_result();
        if ($schedule_result && $schedule_result->num_rows > 0) {
            $schedule_row = $schedule_result->fetch_assoc();
            $course_time = $schedule_row['start_time'];
        }
        $schedule_stmt->close();
    }
}

if (!$course_time) {
    header("Location: markStudentAttendance.php?error=" . urlencode("Aucun emploi du temps trouv� pour ce cours � cette date"));
    exit;
}

$datetime = $date . ' ' . $course_time;
$success_count = 0;
$error_count = 0;

// Debug: Afficher les statuts reçus
error_log("=== DÉBUT ENREGISTREMENT PRÉSENCES ===");
error_log("Classe: $class_id, Cours: $course_id, Date: $date");
error_log("Nombre d'élèves à traiter: " . count($statuses));
error_log("Statuts reçus: " . print_r($statuses, true));
error_log("Commentaires reçus: " . print_r($comments, true));

// Enregistrer les présences
foreach ($statuses as $student_id => $status) {
    error_log("--- Traitement élève: $student_id, Statut BRUT: '$status' (type: " . gettype($status) . ") ---");
    
    // Vérifier que le statut n'est pas vide
    if (empty($status) || $status === null || $status === '') {
        error_log("⚠️ Statut vide ou NULL pour l'élève $student_id, utilisation de 'present' par défaut");
        $status = 'present';
    }
    
    // Nettoyer et valider le statut
    $original_status = $status;
    
    // Convertir les nombres en chaînes correspondantes (1 = present, 2 = absent, etc.)
    if (is_numeric($status)) {
        $status_num = (int)$status;
        $status_map = [0 => 'present', 1 => 'present', 2 => 'absent', 3 => 'late', 4 => 'excused'];
        if (isset($status_map[$status_num])) {
            $status = $status_map[$status_num];
            error_log("⚠️ Statut numérique '$original_status' converti en '$status' pour l'élève $student_id");
        } else {
            error_log("⚠️ Statut numérique inconnu '$original_status' pour l'élève $student_id, utilisation de 'present' par défaut");
            $status = 'present';
        }
    } else {
        $status = trim(strtolower((string)$status));
    }
    
    // Vérifier à nouveau après trim
    if (empty($status)) {
        error_log("⚠️ Statut vide après trim pour l'élève $student_id, utilisation de 'present' par défaut");
        $status = 'present';
    }
    
    $valid_statuses = ['present', 'absent', 'late', 'excused'];
    if (!in_array($status, $valid_statuses)) {
        error_log("❌ Statut invalide reçu: '$original_status' (nettoyé: '$status') pour l'élève $student_id, utilisation de 'present' par défaut");
        $status = 'present';
    } else {
        error_log("✅ Statut valide pour l'élève $student_id: '$status'");
    }
    
    // S'assurer que le statut n'est jamais vide avant l'insertion
    if (empty($status)) {
        $status = 'present';
        error_log("⚠️ DERNIÈRE VÉRIFICATION: Statut était vide, forcé à 'present'");
    }
    // Vérifier que l'élève appartient bien à ce cours et cette classe
    $check_student_sql = "SELECT 1 FROM student_teacher_course 
                         WHERE CAST(student_id AS CHAR) = CAST(? AS CHAR)
                         AND CAST(course_id AS CHAR) = CAST(? AS CHAR)
                         AND CAST(teacher_id AS CHAR) = CAST(? AS CHAR)
                         AND CAST(class_id AS CHAR) = CAST(? AS CHAR)
                         LIMIT 1";
    $check_stmt = $link->prepare($check_student_sql);
    $check_stmt->bind_param("ssss", $student_id, $course_id, $teacher_id, $class_id);
    $check_stmt->execute();
    $student_result = $check_stmt->get_result();
    
    if ($student_result->num_rows === 0) {
        $error_count++;
        error_log("⚠️ Élève $student_id n'appartient pas à ce cours ($course_id) ou cette classe ($class_id)");
        continue;
    }
    $check_stmt->close();
    error_log("✅ Élève $student_id vérifié - appartient bien au cours $course_id et classe $class_id");
    
    // Vérifier si la présence existe déjà pour ce jour et ce cours
    // IMPORTANT: Un élève peut avoir plusieurs cours par jour (Math, Français, Anglais, etc.)
    // Chaque cours doit être enregistré séparément. On vérifie donc par: student_id + course_id + date
    // Exemple: Un élève peut être présent en Math mais absent en Français le même jour
    $check_attendance_sql = "SELECT id, datetime, status FROM student_attendance 
                            WHERE CAST(student_id AS CHAR) = CAST(? AS CHAR)
                            AND course_id = ?
                            AND DATE(datetime) = DATE(?)";
    $check_stmt = $link->prepare($check_attendance_sql);
    if (!$check_stmt) {
        error_log("❌ Erreur de préparation CHECK pour l'élève $student_id: " . $link->error);
        $error_count++;
        continue;
    }
    $check_stmt->bind_param("sis", $student_id, $course_id, $date);
    if (!$check_stmt->execute()) {
        error_log("❌ Erreur d'exécution CHECK pour l'élève $student_id: " . $check_stmt->error);
        $error_count++;
        $check_stmt->close();
        continue;
    }
    $attendance_result = $check_stmt->get_result();
    
    if ($attendance_result->num_rows > 0) {
        // Mettre à jour la présence existante
        $existing_row = $attendance_result->fetch_assoc();
        $existing_id = $existing_row['id'];
        
        $comment = isset($comments[$student_id]) ? trim($comments[$student_id]) : null;
        if (empty($comment)) {
            $comment = null;
        }
        // S'assurer que le statut n'est jamais vide avant la mise à jour
        if (empty($status) || $status === null || $status === '') {
            error_log("⚠️ CRITIQUE: Statut vide avant UPDATE pour l'élève $student_id, forcé à 'present'");
            $status = 'present';
        }
        
        $comment_value = ($comment === null || $comment === '') ? null : $comment;
        
        // Mettre à jour avec le nouveau datetime pour garder l'heure d'enregistrement actuelle
        $update_sql = "UPDATE student_attendance 
                       SET status = ?, 
                           comment = ?, 
                           datetime = ?,
                           updated_at = NOW()
                       WHERE id = ?";
        $update_stmt = $link->prepare($update_sql);
        $update_stmt->bind_param("sssi", $status, $comment_value, $datetime, $existing_id);
        
        error_log("Mise à jour présence - ID: $existing_id, Élève: $student_id, Cours: $course_id, Statut: '$status', DateTime: $datetime");
        
        if ($update_stmt->execute()) {
            $success_count++;
            error_log("✅ Présence mise à jour avec succès - ID: $existing_id, Élève: $student_id, Statut: '$status'");
        } else {
            $error_count++;
            error_log("❌ Erreur lors de la mise à jour de la présence pour l'élève $student_id: " . $update_stmt->error);
            error_log("   SQL: $update_sql");
        }
        $update_stmt->close();
    } else {
        // Insérer la nouvelle présence
        $comment = isset($comments[$student_id]) ? trim($comments[$student_id]) : null;
        if (empty($comment)) {
            $comment = null;
        }
        // Le statut est déjà validé au début de la boucle
        
        error_log("Insertion présence - Élève: $student_id, Cours: $course_id, Classe: $class_id, Statut: '$status', DateTime: $datetime, Admin: $admin_id");
        
        $insert_sql = "INSERT INTO student_attendance 
                      (student_id, course_id, class_id, datetime, status, comment, created_by) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = $link->prepare($insert_sql);
        
        if (!$insert_stmt) {
            $error_count++;
            error_log("Erreur de préparation INSERT pour l'élève $student_id: " . $link->error);
            continue;
        }
        
        // S'assurer que le statut n'est jamais vide avant le bind
        if (empty($status) || $status === null || $status === '') {
            error_log("⚠️ CRITIQUE: Statut vide juste avant bind_param pour l'élève $student_id, forcé à 'present'");
            $status = 'present';
        }
        
        // Gérer le commentaire NULL correctement
        $comment_value = ($comment === null || $comment === '') ? null : $comment;
        
        error_log("Valeurs avant bind_param - student_id: '$student_id', course_id: $course_id, class_id: '$class_id', datetime: '$datetime', status: '$status', comment: " . ($comment_value ?? 'NULL') . ", admin_id: '$admin_id'");
        
        // Format: s (student_id), i (course_id), s (class_id), s (datetime), s (status), s (comment), s (admin_id)
        $bind_result = $insert_stmt->bind_param("sississ", $student_id, $course_id, $class_id, $datetime, $status, $comment_value, $admin_id);
        
        if (!$bind_result) {
            $error_count++;
            error_log("❌ Erreur de bind_param pour l'élève $student_id: " . $insert_stmt->error);
            $insert_stmt->close();
            continue;
        }
        
        if ($insert_stmt->execute()) {
            $inserted_id = $insert_stmt->insert_id;
            $success_count++;
            error_log("✅ Présence insérée avec succès - ID: $inserted_id, Élève: $student_id, Cours: $course_id, Classe: $class_id, Statut: '$status', DateTime: $datetime");
            
            // Vérifier que l'enregistrement existe bien dans la base
            $verify_sql = "SELECT id, status FROM student_attendance WHERE id = ?";
            $verify_stmt = $link->prepare($verify_sql);
            if ($verify_stmt) {
                $verify_stmt->bind_param("i", $inserted_id);
                $verify_stmt->execute();
                $verify_result = $verify_stmt->get_result();
                if ($verify_result->num_rows > 0) {
                    $verify_row = $verify_result->fetch_assoc();
                    error_log("✅ Vérification: Enregistrement confirmé dans la base - ID: $inserted_id, Statut: " . $verify_row['status']);
                } else {
                    error_log("⚠️ ATTENTION: L'enregistrement $inserted_id n'a pas été trouvé dans la base après insertion!");
                }
                $verify_stmt->close();
            }
        } else {
            $error_count++;
            error_log("❌ Erreur lors de l'insertion de la présence pour l'élève $student_id: " . $insert_stmt->error);
            error_log("   SQL: $insert_sql");
            error_log("   Paramètres: student_id='$student_id' (type: " . gettype($student_id) . "), course_id=$course_id (type: " . gettype($course_id) . "), class_id='$class_id' (type: " . gettype($class_id) . "), datetime='$datetime', status='$status', comment=" . ($comment ? "'$comment'" : 'NULL') . ", admin_id='$admin_id'");
            error_log("   Erreur MySQL: " . $link->error);
        }
        $insert_stmt->close();
    }
    error_log("--- Fin traitement élève: $student_id ---");
}

error_log("=== FIN ENREGISTREMENT PRÉSENCES ===");
error_log("Résultat: $success_count succès, $error_count erreurs");

// Redirection avec message
$redirect_url = "markStudentAttendance.php?date=" . urlencode($date) . 
               "&class_id=" . urlencode($class_id) . 
               "&course_id=" . urlencode($course_id);

if ($success_count > 0) {
    $message = "$success_count présence(s) enregistrée(s) avec succès";
    if ($error_count > 0) {
        $message .= " ($error_count erreur(s))";
    }
    header("Location: $redirect_url&success=" . urlencode($message));
} else {
    header("Location: $redirect_url&error=" . urlencode("Aucune présence n'a pu être enregistrée"));
}
exit;
?>

