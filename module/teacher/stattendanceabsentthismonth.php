<?php  
include_once('../../service/mysqlcon.php');
$check = $_REQUEST['classid'] ?? '';

if (empty($check)) {
    echo "<tr><td>Erreur: ID de classe manquant</td></tr>";
    exit();
}

// Utiliser la nouvelle table student_attendance pour les absences
$attendmon = "SELECT DISTINCT DATE(datetime) as date 
              FROM student_attendance 
              WHERE CAST(class_id AS CHAR) = CAST(? AS CHAR)
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
