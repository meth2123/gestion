<?php  
include_once('main.php');
include_once('../../service/mysqlcon.php');
$sid = $_REQUEST['id'] ?? '';

if (empty($sid)) {
    echo "<tr><td>Erreur: ID membre du personnel manquant</td></tr>";
    exit();
}

// Utiliser la nouvelle table attendance avec status='absent'
$attendmon = "SELECT DISTINCT DATE(datetime) as date 
              FROM attendance 
              WHERE CAST(attendedid AS CHAR) = CAST(? AS CHAR)
              AND person_type = 'staff'
              AND status IN ('absent', 'late')
              ORDER BY datetime DESC";
$stmt = $link->prepare($attendmon);
$stmt->bind_param("s", $sid);
$stmt->execute();
$resmon = $stmt->get_result();

echo "<tr><th>Dates d'absence (toutes):</th></tr>";
if ($resmon->num_rows > 0) {
    while($r = $resmon->fetch_assoc()) {
        echo "<tr><td>" . htmlspecialchars($r['date']) . "</td></tr>";
    }
} else {
    echo "<tr><td>Aucune absence enregistrée</td></tr>";
}
$stmt->close();
?>
