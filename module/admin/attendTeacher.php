<?php
include_once('main.php');
include_once('../../service/mysqlcon.php');

// Traitement en mode AJAX pour éviter les redirections
if (isset($_POST['ajax']) && $_POST['ajax'] == 1) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    if (isset($_POST['teacher_id']) && isset($_POST['status'])) {
        $teacher_id = $_POST['teacher_id'];
        $admin_id = $_SESSION['login_id'];
        $status = $_POST['status']; // "present" ou "absent"
        
        // Récupérer les paramètres optionnels
        $course_id = isset($_POST['course_id']) && !empty($_POST['course_id']) ? (int)$_POST['course_id'] : null;
        $time_slot_id = isset($_POST['time_slot_id']) && !empty($_POST['time_slot_id']) ? (int)$_POST['time_slot_id'] : null;
        $datetime = isset($_POST['datetime']) ? $_POST['datetime'] : date('Y-m-d H:i:s');
        $date_only = date('Y-m-d', strtotime($datetime));
        
        // Vérifier que l'enseignant appartient à cet admin
        $verify_sql = "SELECT id FROM teachers WHERE id = ? AND CAST(created_by AS CHAR) = CAST(? AS CHAR)";
        $verify_stmt = $link->prepare($verify_sql);
        $verify_stmt->bind_param("ss", $teacher_id, $admin_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        $is_authorized = $verify_result->num_rows > 0;
        $verify_result->free();
        $verify_stmt->close();
        
        if (!$is_authorized) {
            $response['message'] = "Accès non autorisé";
            echo json_encode($response);
            exit;
        }
        
        if ($status === "present") {
            // Vérifier si la présence existe déjà pour ce cours et cette date/heure
            if ($course_id) {
                $check_sql = "SELECT id FROM attendance 
                              WHERE CAST(attendedid AS CHAR) = CAST(? AS CHAR)
                              AND course_id = ?
                              AND DATE(datetime) = DATE(?)
                              AND TIME(datetime) = TIME(?)";
                $check_stmt = $link->prepare($check_sql);
                $check_stmt->bind_param("siss", $teacher_id, $course_id, $datetime, $datetime);
            } else {
                $check_sql = "SELECT id FROM attendance 
                              WHERE CAST(attendedid AS CHAR) = CAST(? AS CHAR)
                              AND DATE(datetime) = DATE(?)";
                $check_stmt = $link->prepare($check_sql);
                $check_stmt->bind_param("ss", $teacher_id, $datetime);
            }
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $response['message'] = "La présence a déjà été enregistrée pour ce cours et cette heure";
                echo json_encode($response);
                exit;
            }
            $check_stmt->close();
            
            // Insérer la nouvelle présence
            if ($course_id && $time_slot_id) {
                $sql = "INSERT INTO attendance (datetime, attendedid, person_type, course_id, time_slot_id, status, created_by) 
                        VALUES (?, ?, 'teacher', ?, ?, 'present', ?)";
                $stmt = $link->prepare($sql);
                $stmt->bind_param("ssiis", $datetime, $teacher_id, $course_id, $time_slot_id, $admin_id);
            } elseif ($course_id) {
                $sql = "INSERT INTO attendance (datetime, attendedid, person_type, course_id, time_slot_id, status, created_by) 
                        VALUES (?, ?, 'teacher', ?, NULL, 'present', ?)";
                $stmt = $link->prepare($sql);
                $stmt->bind_param("ssis", $datetime, $teacher_id, $course_id, $admin_id);
            } else {
                $sql = "INSERT INTO attendance (datetime, attendedid, person_type, course_id, time_slot_id, status, created_by) 
                        VALUES (?, ?, 'teacher', NULL, NULL, 'present', ?)";
                $stmt = $link->prepare($sql);
                $stmt->bind_param("sss", $datetime, $teacher_id, $admin_id);
            }
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = "Présence enregistrée avec succès";
            } else {
                $response['message'] = "Erreur lors de l'enregistrement: " . $stmt->error;
            }
            $stmt->close();
        } else if ($status === "absent") {
            // Vérifier si l'absence existe déjà
            $check_sql = "SELECT id FROM teacher_absences WHERE teacher_id = ? AND date = ?";
            $check_stmt = $link->prepare($check_sql);
            $check_stmt->bind_param("ss", $teacher_id, $date_only);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $response['message'] = "L'absence a déjà été enregistrée pour aujourd'hui";
                echo json_encode($response);
                exit;
            }
            $check_stmt->close();
            
            // Insérer la nouvelle absence
            $sql = "INSERT INTO teacher_absences (date, teacher_id, created_by) VALUES (?, ?, ?)";
            $stmt = $link->prepare($sql);
            $stmt->bind_param("sss", $date_only, $teacher_id, $admin_id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = "Absence enregistrée avec succès";
            } else {
                $response['message'] = "Erreur lors de l'enregistrement: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $response['message'] = "Statut non valide";
        }
    } else {
        $response['message'] = "Paramètres manquants";
    }
    
    echo json_encode($response);
    exit;
}

// Pour les requêtes non-AJAX (compatibilité avec l'ancien code)
if (isset($_POST['teacher_id']) && isset($_POST['status'])) {
    $teacher_id = $_POST['teacher_id'];
    $admin_id = $_SESSION['login_id'];
    $status = $_POST['status'];
    
    // Récupérer les paramètres optionnels
    $course_id = isset($_POST['course_id']) && !empty($_POST['course_id']) ? (int)$_POST['course_id'] : null;
    $time_slot_id = isset($_POST['time_slot_id']) && !empty($_POST['time_slot_id']) ? (int)$_POST['time_slot_id'] : null;
    $datetime = isset($_POST['datetime']) ? $_POST['datetime'] : date('Y-m-d H:i:s');
    $date_only = date('Y-m-d', strtotime($datetime));
    
    // Vérifier que l'enseignant appartient à cet admin
    $verify_sql = "SELECT id FROM teachers WHERE id = ? AND CAST(created_by AS CHAR) = CAST(? AS CHAR)";
    $verify_stmt = $link->prepare($verify_sql);
    $verify_stmt->bind_param("ss", $teacher_id, $admin_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    $is_authorized = $verify_result->num_rows > 0;
    $verify_result->free();
    $verify_stmt->close();
    
    if (!$is_authorized) {
        header("Location: teacherAttendance.php?error=" . urlencode("Accès non autorisé"));
        exit;
    }
    
    if ($status === "present") {
        // Vérifier si la présence existe déjà
        if ($course_id) {
            $check_sql = "SELECT id FROM attendance 
                          WHERE CAST(attendedid AS CHAR) = CAST(? AS CHAR)
                          AND course_id = ?
                          AND DATE(datetime) = DATE(?)
                          AND TIME(datetime) = TIME(?)";
            $check_stmt = $link->prepare($check_sql);
            $check_stmt->bind_param("siss", $teacher_id, $course_id, $datetime, $datetime);
        } else {
            $check_sql = "SELECT id FROM attendance 
                          WHERE CAST(attendedid AS CHAR) = CAST(? AS CHAR)
                          AND DATE(datetime) = DATE(?)";
            $check_stmt = $link->prepare($check_sql);
            $check_stmt->bind_param("ss", $teacher_id, $datetime);
        }
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            header("Location: teacherAttendance.php?error=" . urlencode("La présence a déjà été enregistrée pour ce cours et cette heure"));
            exit;
        }
        $check_stmt->close();
        
        // Insérer la nouvelle présence
        if ($course_id && $time_slot_id) {
            $sql = "INSERT INTO attendance (datetime, attendedid, person_type, course_id, time_slot_id, status, created_by) 
                    VALUES (?, ?, 'teacher', ?, ?, 'present', ?)";
            $stmt = $link->prepare($sql);
            $stmt->bind_param("ssiis", $datetime, $teacher_id, $course_id, $time_slot_id, $admin_id);
        } elseif ($course_id) {
            $sql = "INSERT INTO attendance (datetime, attendedid, person_type, course_id, time_slot_id, status, created_by) 
                    VALUES (?, ?, 'teacher', ?, NULL, 'present', ?)";
            $stmt = $link->prepare($sql);
            $stmt->bind_param("ssis", $datetime, $teacher_id, $course_id, $admin_id);
        } else {
            $sql = "INSERT INTO attendance (datetime, attendedid, person_type, course_id, time_slot_id, status, created_by) 
                    VALUES (?, ?, 'teacher', NULL, NULL, 'present', ?)";
            $stmt = $link->prepare($sql);
            $stmt->bind_param("sss", $datetime, $teacher_id, $admin_id);
        }
        
        if ($stmt->execute()) {
            $redirect_date = date('Y-m-d', strtotime($datetime));
            header("Location: teacherAttendance.php?date=" . $redirect_date . "&success=" . urlencode("Présence enregistrée avec succès"));
        } else {
            header("Location: teacherAttendance.php?error=" . urlencode("Erreur lors de l'enregistrement: " . $stmt->error));
        }
        $stmt->close();
    } else if ($status === "absent") {
        // Vérifier si l'absence existe déjà
        $check_sql = "SELECT id FROM teacher_absences WHERE teacher_id = ? AND date = ?";
        $check_stmt = $link->prepare($check_sql);
        $check_stmt->bind_param("ss", $teacher_id, $date_only);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            header("Location: teacherAttendance.php?error=" . urlencode("L'absence a déjà été enregistrée pour aujourd'hui"));
            exit;
        }
        $check_stmt->close();
        
        // Insérer la nouvelle absence
        $sql = "INSERT INTO teacher_absences (date, teacher_id, created_by) VALUES (?, ?, ?)";
        $stmt = $link->prepare($sql);
        $stmt->bind_param("sss", $date_only, $teacher_id, $admin_id);
        
        if ($stmt->execute()) {
            $redirect_date = date('Y-m-d', strtotime($datetime));
            header("Location: teacherAttendance.php?date=" . $redirect_date . "&success=" . urlencode("Absence enregistrée avec succès"));
        } else {
            header("Location: teacherAttendance.php?error=" . urlencode("Erreur lors de l'enregistrement: " . $stmt->error));
        }
        $stmt->close();
    }
    exit;
}

// Si on arrive ici sans POST, rediriger vers la page de présence
header("Location: teacherAttendance.php");
exit;
?>
