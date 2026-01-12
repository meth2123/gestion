<?php
include_once('main.php');
include_once('../../service/mysqlcon.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_id = $_SESSION['login_id'];
    $class_id = $_POST['class_id'] ?? '';
    $course_id = $_POST['course_id'] ?? '';
    $date = $_POST['date'] ?? date('Y-m-d');
    $students = $_POST['students'] ?? [];
    $statuses = $_POST['status'] ?? [];
    
    if (empty($class_id) || empty($course_id) || empty($students)) {
        header("Location: studentAttendance.php?error=" . urlencode("Paramètres manquants"));
        exit;
    }
    
    $default_time = '08:00:00';
    $datetime = $date . ' ' . $default_time;
    $success_count = 0;
    $error_count = 0;
    
    // Vérifier que le cours appartient à cet admin
    $verify_sql = "SELECT id FROM course WHERE id = ? AND CAST(created_by AS CHAR) = CAST(? AS CHAR)";
    $verify_stmt = $link->prepare($verify_sql);
    $verify_stmt->bind_param("is", $course_id, $admin_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows === 0) {
        header("Location: studentAttendance.php?error=" . urlencode("Accès non autorisé à ce cours"));
        exit;
    }
    $verify_stmt->close();
    
    // Enregistrer les présences
    foreach ($students as $student_id) {
        // Vérifier que l'élève appartient à cet admin
        $check_student_sql = "SELECT id FROM students 
                             WHERE id = ? 
                             AND CAST(created_by AS CHAR) = CAST(? AS CHAR)
                             AND CAST(classid AS CHAR) = CAST(? AS CHAR)";
        $check_stmt = $link->prepare($check_student_sql);
        $check_stmt->bind_param("sss", $student_id, $admin_id, $class_id);
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
            $error_count++;
            continue;
        }
        $check_stmt->close();
        
        // Récupérer le statut (par défaut 'present')
        $status = isset($statuses[$student_id]) ? $statuses[$student_id] : 'present';
        
        // Insérer la présence
        $insert_sql = "INSERT INTO student_attendance 
                      (student_id, course_id, class_id, datetime, status, created_by) 
                      VALUES (?, ?, ?, ?, ?, ?)";
        $insert_stmt = $link->prepare($insert_sql);
        $insert_stmt->bind_param("sissis", $student_id, $course_id, $class_id, $datetime, $status, $admin_id);
        
        if ($insert_stmt->execute()) {
            $success_count++;
        } else {
            $error_count++;
        }
        $insert_stmt->close();
    }
    
    // Redirection avec message
    $redirect_url = "studentAttendance.php?date=" . urlencode($date) . 
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
}

// Si on arrive ici sans POST, rediriger
header("Location: studentAttendance.php");
exit;
?>

