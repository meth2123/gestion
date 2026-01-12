<?php  
include_once('../../service/mysqlcon.php');
$check = $_REQUEST['classid'] ?? '';

if (empty($check)) {
    echo "<tr><td>Erreur: ID de classe manquant</td></tr>";
    exit();
}

// Utiliser la nouvelle table student_attendance pour les élèves
$attendmon = "SELECT DISTINCT DATE(datetime) as date 
              FROM student_attendance 
              WHERE CAST(class_id AS CHAR) = CAST(? AS CHAR)
              AND status = 'present'
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
