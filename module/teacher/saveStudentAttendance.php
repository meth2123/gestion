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
        // Le statut est déjà validé au début de la boucle
        
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
        // Le statut est déjà validé au début de la boucle
        
        error_log("Insertion présence - Élève: $student_id, Cours: $course_id, Statut: '$status', DateTime: $datetime, Admin: $admin_id");
        
        $insert_sql = "INSERT INTO student_attendance 
                      (student_id, course_id, class_id, datetime, status, comment, created_by) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = $link->prepare($insert_sql);
        // Format: s (student_id), i (course_id), s (class_id), s (datetime), s (status), s (comment), s (admin_id)
        $insert_stmt->bind_param("sississ", $student_id, $course_id, $class_id, $datetime, $status, $comment, $admin_id);
        
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

