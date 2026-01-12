<?php  
include_once('main.php');
include_once('../../service/mysqlcon.php');

// Utiliser la nouvelle table attendance avec datetime
$attendmon = "SELECT DISTINCT DATE(datetime) as date 
              FROM attendance 
              WHERE CAST(attendedid AS CHAR) = CAST(? AS CHAR)
              AND person_type = 'teacher'
              AND MONTH(datetime) = MONTH(CURRENT_DATE) 
              AND YEAR(datetime) = YEAR(CURRENT_DATE)
              ORDER BY datetime DESC";
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
