<?php  
include_once('main.php');
include_once('../../service/mysqlcon.php');
$sid = $_REQUEST['id'] ?? '';

if (empty($sid)) {
    echo "<tr><td>Erreur: ID étudiant manquant</td></tr>";
    exit();
}

// Utiliser la nouvelle table student_attendance
$attendmon = "SELECT DISTINCT DATE(datetime) as date 
              FROM student_attendance 
              WHERE CAST(student_id AS CHAR) = CAST(? AS CHAR)
              AND status = 'present'
              AND MONTH(datetime) = MONTH(CURRENT_DATE) 
              AND YEAR(datetime) = YEAR(CURRENT_DATE)
              ORDER BY datetime DESC";
$stmt = $link->prepare($attendmon);
$stmt->bind_param("s", $sid);
$stmt->execute();
$resmon = $stmt->get_result();

echo "<tr><th>Dates de présence ce mois:</th></tr>";
while($r = $resmon->fetch_assoc()) {
    echo "<tr><td>" . htmlspecialchars($r['date']) . "</td></tr>";
}
$stmt->close();
?>
