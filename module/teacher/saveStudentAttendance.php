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

if (empty($class_id) || empty($course_id) || empty($statuses)) {
    header("Location: markStudentAttendance.php?error=" . urlencode("Paramètres manquants"));
    exit;
}

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

$default_time = date('H:i:s');
$datetime = $date . ' ' . $default_time;
$success_count = 0;
$error_count = 0;

// Debug: Afficher les statuts reçus
error_log("Statuts reçus: " . print_r($statuses, true));

// Enregistrer les présences
foreach ($statuses as $student_id => $status) {
    // Nettoyer et valider le statut
    $original_status = $status;
    $status = trim(strtolower($status));
    $valid_statuses = ['present', 'absent', 'late', 'excused'];
    if (!in_array($status, $valid_statuses)) {
        error_log("Statut invalide reçu: '$original_status' (nettoyé: '$status') pour l'élève $student_id, utilisation de 'present' par défaut");
        $status = 'present';
    } else {
        error_log("Statut valide pour l'élève $student_id: '$status'");
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
        continue;
    }
    $check_stmt->close();
    
    // Vérifier si la présence existe déjà
    $check_attendance_sql = "SELECT id FROM student_attendance 
                            WHERE CAST(student_id AS CHAR) = CAST(? AS CHAR)
                            AND course_id = ?
                            AND DATE(datetime) = DATE(?)
                            AND TIME(datetime) = TIME(?)";
    $check_stmt = $link->prepare($check_attendance_sql);
    $check_stmt->bind_param("siss", $student_id, $course_id, $datetime, $datetime);
    $check_stmt->execute();
    $attendance_result = $check_stmt->get_result();
    
    if ($attendance_result->num_rows > 0) {
        // Mettre à jour la présence existante
        $comment = isset($comments[$student_id]) ? trim($comments[$student_id]) : null;
        if (empty($comment)) {
            $comment = null;
        }
        // Valider le statut
        $valid_statuses = ['present', 'absent', 'late', 'excused'];
        if (!in_array($status, $valid_statuses)) {
            $status = 'present'; // Valeur par défaut si invalide
        }
        
        $update_sql = "UPDATE student_attendance 
                       SET status = ?, comment = ?, updated_at = NOW()
                       WHERE CAST(student_id AS CHAR) = CAST(? AS CHAR)
                       AND course_id = ?
                       AND DATE(datetime) = DATE(?)
                       AND TIME(datetime) = TIME(?)";
        $update_stmt = $link->prepare($update_sql);
        $update_stmt->bind_param("sssiss", $status, $comment, $student_id, $course_id, $datetime, $datetime);
        
        error_log("Mise à jour présence - Élève: $student_id, Cours: $course_id, Statut: $status, DateTime: $datetime");
        
        if ($update_stmt->execute()) {
            $success_count++;
            error_log("Présence mise à jour avec succès pour l'élève $student_id avec le statut '$status'");
        } else {
            $error_count++;
            error_log("Erreur lors de la mise à jour de la présence pour l'élève $student_id: " . $update_stmt->error);
        }
        $update_stmt->close();
    } else {
        // Insérer la nouvelle présence
        $comment = isset($comments[$student_id]) ? trim($comments[$student_id]) : null;
        if (empty($comment)) {
            $comment = null;
        }
        // Valider le statut
        $valid_statuses = ['present', 'absent', 'late', 'excused'];
        if (!in_array($status, $valid_statuses)) {
            $status = 'present'; // Valeur par défaut si invalide
        }
        
        $insert_sql = "INSERT INTO student_attendance 
                      (student_id, course_id, class_id, datetime, status, comment, created_by) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = $link->prepare($insert_sql);
        // Format: s (student_id), i (course_id), s (class_id), s (datetime), s (status), s (comment), s (teacher_id)
        $insert_stmt->bind_param("sississ", $student_id, $course_id, $class_id, $datetime, $status, $comment, $teacher_id);
        
        error_log("Insertion présence - Élève: $student_id, Cours: $course_id, Statut: $status, DateTime: $datetime");
        
        if ($insert_stmt->execute()) {
            $success_count++;
            error_log("Présence insérée avec succès pour l'élève $student_id avec le statut '$status'");
        } else {
            $error_count++;
            error_log("Erreur lors de l'insertion de la présence pour l'élève $student_id: " . $insert_stmt->error);
        }
        $insert_stmt->close();
    }
}

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

