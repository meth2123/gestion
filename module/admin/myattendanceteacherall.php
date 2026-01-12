<?php  
include_once('main.php');
include_once('../../service/mysqlcon.php');
$sid = $_REQUEST['id'] ?? '';

if (empty($sid)) {
    echo "<tr><td>Erreur: ID enseignant manquant</td></tr>";
    exit();
}

// Utiliser la nouvelle table attendance avec datetime
$attendmon = "SELECT DISTINCT DATE(datetime) as date 
              FROM attendance 
              WHERE CAST(attendedid AS CHAR) = CAST(? AS CHAR)
              AND person_type = 'teacher'
              ORDER BY datetime DESC";
$stmt = $link->prepare($attendmon);
$stmt->bind_param("s", $sid);
$stmt->execute();
$resmon = $stmt->get_result();

echo "<tr><th>Dates de présence (toutes):</th></tr>";
while($r = $resmon->fetch_assoc()) {
    echo "<tr><td>" . htmlspecialchars($r['date']) . "</td></tr>";
}
$stmt->close();
?>
