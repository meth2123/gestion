<?php  
include_once('main.php');
include_once('../../service/mysqlcon.php');

// Utiliser la nouvelle table attendance avec status='absent' pour les enseignants
$attendmon = "SELECT DISTINCT DATE(datetime) as date 
              FROM attendance 
              WHERE CAST(attendedid AS CHAR) = CAST(? AS CHAR)
              AND person_type = 'teacher'
              AND status IN ('absent', 'late')
              AND MONTH(datetime) = MONTH(CURRENT_DATE) 
              AND YEAR(datetime) = YEAR(CURRENT_DATE)
              ORDER BY datetime DESC";
$stmt = $link->prepare($attendmon);
$stmt->bind_param("s", $check);
$stmt->execute();
$resmon = $stmt->get_result();

echo "<tr><th>Dates d'absence ce mois:</th></tr>";
if ($resmon->num_rows > 0) {
    while($r = $resmon->fetch_assoc()) {
        echo "<tr><td>" . htmlspecialchars($r['date']) . "</td></tr>";
    }
} else {
    echo "<tr><td>Aucune absence enregistrée ce mois</td></tr>";
}
$stmt->close();
?>
