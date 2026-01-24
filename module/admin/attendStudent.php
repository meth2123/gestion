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
    
    // Debug: Afficher les données reçues
    error_log("Admin - Statuts reçus: " . print_r($statuses, true));
    error_log("Admin - Élèves reçus: " . print_r($students, true));
    
    // Traiter TOUS les élèves envoyés dans le tableau students[]
    foreach ($students as $student_id) {
        error_log("Admin - Traitement élève: $student_id");
        
        // Récupérer le statut pour cet élève
        $status = isset($statuses[$student_id]) ? trim(strtolower($statuses[$student_id])) : 'present';
        
        // Valider le statut
        $valid_statuses = ['present', 'absent', 'late', 'excused'];
        if (empty($status) || !in_array($status, $valid_statuses)) {
            error_log("⚠️ Statut invalide '$status' pour l'élève $student_id, utilisation de 'present' par défaut");
            $status = 'present';
        }
        
        error_log("✅ Statut final pour l'élève $student_id: '$status'");
        
        // Vérifier que l'élève appartient à cet admin
        $check_student_sql = "SELECT id FROM students 
                             WHERE CAST(id AS CHAR) = CAST(? AS CHAR)
                             AND CAST(created_by AS CHAR) = CAST(? AS CHAR)
                             AND CAST(classid AS CHAR) = CAST(? AS CHAR)";
        $check_stmt = $link->prepare($check_student_sql);
        $check_stmt->bind_param("sss", $student_id, $admin_id, $class_id);
        $check_stmt->execute();
        $student_result = $check_stmt->get_result();
        
        if ($student_result->num_rows === 0) {
            error_log("❌ Élève $student_id non autorisé ou non trouvé");
            $error_count++;
            $check_stmt->close();
            continue;
        }
        $check_stmt->close();
        
        // Vérifier si la présence existe déjà
        $check_attendance_sql = "SELECT id, status FROM student_attendance 
                                WHERE CAST(student_id AS CHAR) = CAST(? AS CHAR)
                                AND course_id = ?
                                AND DATE(datetime) = DATE(?)";
        $check_stmt = $link->prepare($check_attendance_sql);
        $check_stmt->bind_param("sis", $student_id, $course_id, $datetime);
        $check_stmt->execute();
        $attendance_result = $check_stmt->get_result();
        
        if ($attendance_result->num_rows > 0) {
            // Mettre à jour la présence existante
            $existing = $attendance_result->fetch_assoc();
            
            $update_sql = "UPDATE student_attendance 
                          SET status = ?, updated_at = NOW()
                          WHERE id = ?";
            $update_stmt = $link->prepare($update_sql);
            $update_stmt->bind_param("si", $status, $existing['id']);
            
            error_log("Admin - Mise à jour présence - Élève: $student_id, Ancien statut: '{$existing['status']}', Nouveau statut: '$status'");
            
            if ($update_stmt->execute()) {
                $success_count++;
                error_log("✅ Présence mise à jour avec succès pour l'élève $student_id avec le statut '$status'");
            } else {
                $error_count++;
                error_log("❌ Erreur lors de la mise à jour de la présence pour l'élève $student_id: " . $update_stmt->error);
            }
            $update_stmt->close();
            $check_stmt->close();
            continue;
        }
        $check_stmt->close();
        
        error_log("Admin - Insertion présence - Élève: $student_id, Cours: $course_id, Statut: '$status', DateTime: $datetime");
        
        // Insérer la nouvelle présence
        $insert_sql = "INSERT INTO student_attendance 
                      (student_id, course_id, class_id, datetime, status, created_by) 
                      VALUES (?, ?, ?, ?, ?, ?)";
        $insert_stmt = $link->prepare($insert_sql);
        $insert_stmt->bind_param("sissss", $student_id, $course_id, $class_id, $datetime, $status, $admin_id);
        
        if ($insert_stmt->execute()) {
            $success_count++;
            error_log("✅ Présence insérée avec succès pour l'élève $student_id avec le statut '$status'");
        } else {
            $error_count++;
            error_log("❌ Erreur lors de l'insertion de la présence pour l'élève $student_id: " . $insert_stmt->error);
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