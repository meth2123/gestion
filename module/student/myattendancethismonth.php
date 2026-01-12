<?php  
include_once('main.php');
include_once('../../service/mysqlcon.php');

// Utiliser la nouvelle table student_attendance
$attendmon = "SELECT DISTINCT DATE(datetime) as date 
              FROM student_attendance 
              WHERE CAST(student_id AS CHAR) = CAST(? AS CHAR) 
              AND MONTH(datetime) = MONTH(CURRENT_DATE) 
              AND YEAR(datetime) = YEAR(CURRENT_DATE)
              AND status = 'present'";
$stmt = $link->prepare($attendmon);
$stmt->bind_param("s", $check);
$stmt->execute();
$resmon = $stmt->get_result();

echo "<tr><th>Dates de présence ce mois:</th></tr>";
while($r = $resmon->fetch_assoc()) {
    echo "<tr><td>" . htmlspecialchars($r['date']) . "</td></tr>";
}
$stmt->close();
?>
