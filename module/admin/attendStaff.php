<?php
include_once('main.php');
include_once('../../service/mysqlcon.php');

if (isset($_POST['id']) && isset($_POST['submit'])) {
    $id = $_POST['id'];
    $admin_id = $_SESSION['login_id'];
    $status = $_POST['submit']; // "present" ou "absent"
    $datetime = isset($_POST['datetime']) ? $_POST['datetime'] : date('Y-m-d H:i:s');
    $date_only = date('Y-m-d', strtotime($datetime));

    // Vérifier si l'entrée n'existe pas déjà
    $check_sql = "SELECT id FROM attendance 
                  WHERE CAST(attendedid AS CHAR) = CAST(? AS CHAR)
                  AND person_type = 'staff'
                  AND DATE(datetime) = DATE(?)";
    $check_stmt = $link->prepare($check_sql);
    $check_stmt->bind_param("ss", $id, $datetime);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        header("Location: staffAttendance.php?error=" . urlencode("La présence a déjà été enregistrée pour aujourd'hui"));
        exit;
    }
    $check_stmt->close();

    // Vérifier que le membre du personnel appartient à cet admin
    $verify_sql = "SELECT id FROM staff WHERE id = ? AND CAST(created_by AS CHAR) = CAST(? AS CHAR)";
    $verify_stmt = $link->prepare($verify_sql);
    $verify_stmt->bind_param("ss", $id, $admin_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    $is_authorized = $verify_result->num_rows > 0;
    $verify_result->free();
    $verify_stmt->close();
    
    if (!$is_authorized) {
        header("Location: staffAttendance.php?error=" . urlencode("Accès non autorisé"));
        exit;
    }

    if ($status === "present") {
        // Insérer la nouvelle présence
        $sql = "INSERT INTO attendance (datetime, attendedid, person_type, status, created_by) 
                VALUES (?, ?, 'staff', 'present', ?)";
        $stmt = $link->prepare($sql);
        $stmt->bind_param("sss", $datetime, $id, $admin_id);
        
        if ($stmt->execute()) {
            header("Location: staffAttendance.php?success=" . urlencode("Présence enregistrée avec succès"));
        } else {
            header("Location: staffAttendance.php?error=" . urlencode("Erreur lors de l'enregistrement de la présence"));
        }
        $stmt->close();
    } else if ($status === "absent") {
        // Pour les absences, on peut utiliser une table séparée ou la même table avec status='absent'
        $sql = "INSERT INTO attendance (datetime, attendedid, person_type, status, created_by) 
                VALUES (?, ?, 'staff', 'absent', ?)";
        $stmt = $link->prepare($sql);
        $stmt->bind_param("sss", $datetime, $id, $admin_id);
        
        if ($stmt->execute()) {
            header("Location: staffAttendance.php?success=" . urlencode("Absence enregistrée avec succès"));
        } else {
            header("Location: staffAttendance.php?error=" . urlencode("Erreur lors de l'enregistrement de l'absence"));
        }
        $stmt->close();
    }
    exit;
}

// Si on arrive ici sans POST, rediriger vers la page de présence
header("Location: staffAttendance.php");
exit;
?>
