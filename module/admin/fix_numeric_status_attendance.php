<?php
/**
 * Script pour corriger les statuts numériques (1, 2) vers les valeurs ENUM correctes ('present', 'absent')
 */

include_once('main.php');
include_once('includes/auth_check.php');
include_once('../../service/db_utils.php');

global $link;
$conn = $link;

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Correction des Statuts Numériques</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .btn { padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer; text-decoration: none; display: inline-block; margin: 10px 5px; }
        .btn:hover { background: #0056b3; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
    </style>
</head>
<body>
    <h1>Correction des Statuts Numériques dans student_attendance</h1>";

// 1. Compter les enregistrements avec statut numérique
$count_query = "
SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN status = '1' OR status = 1 THEN 1 END) as status_1,
    COUNT(CASE WHEN status = '2' OR status = 2 THEN 1 END) as status_2,
    COUNT(CASE WHEN status = '0' OR status = 0 THEN 1 END) as status_0,
    COUNT(CASE WHEN status IN ('present', 'absent', 'late', 'excused') THEN 1 END) as status_valid
FROM student_attendance";
$count_result = $conn->query($count_query);
$count_data = $count_result->fetch_assoc();

echo "<h2>1. Analyse</h2>";
echo "<table>";
echo "<tr><th>Type</th><th>Nombre</th></tr>";
echo "<tr><td><strong>Total enregistrements</strong></td><td><strong>" . $count_data['total'] . "</strong></td></tr>";
echo "<tr><td>Statut = 1 (à convertir en 'present')</td><td>" . $count_data['status_1'] . "</td></tr>";
echo "<tr><td>Statut = 2 (à convertir en 'absent')</td><td>" . $count_data['status_2'] . "</td></tr>";
echo "<tr><td>Statut = 0 (à convertir en 'present')</td><td>" . $count_data['status_0'] . "</td></tr>";
echo "<tr><td>Statuts valides (déjà corrects)</td><td class='success'>" . $count_data['status_valid'] . "</td></tr>";
echo "</table>";

$total_to_fix = $count_data['status_1'] + $count_data['status_2'] + $count_data['status_0'];

if ($total_to_fix == 0) {
    echo "<p class='success'>✅ Aucun enregistrement avec statut numérique trouvé. Tout est correct!</p>";
    echo "<p><a href='debug_absences_bulletin.php' class='btn'>Retour au diagnostic</a></p>";
    exit;
}

// 2. Afficher un échantillon
echo "<h2>2. Échantillon des enregistrements à corriger</h2>";
$sample_query = "
SELECT 
    id,
    student_id,
    course_id,
    class_id,
    datetime,
    status,
    created_at
FROM student_attendance
WHERE status = '1' OR status = 1 OR status = '2' OR status = 2 OR status = '0' OR status = 0
ORDER BY created_at DESC
LIMIT 20";
$sample_result = $conn->query($sample_query);

if ($sample_result->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Student ID</th><th>Course ID</th><th>Class ID</th><th>Date/Time</th><th>Status (actuel)</th><th>Status (sera)</th><th>Créé le</th></tr>";
    while ($row = $sample_result->fetch_assoc()) {
        $current_status = $row['status'];
        $new_status = '';
        if ($current_status == '1' || $current_status == 1) {
            $new_status = 'present';
        } elseif ($current_status == '2' || $current_status == 2) {
            $new_status = 'absent';
        } elseif ($current_status == '0' || $current_status == 0) {
            $new_status = 'present';
        }
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['student_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['course_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['class_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['datetime']) . "</td>";
        echo "<td class='error'><strong>" . htmlspecialchars($current_status) . "</strong></td>";
        echo "<td class='success'><strong>" . htmlspecialchars($new_status) . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 3. Corriger les enregistrements
if (isset($_POST['fix']) && $_POST['fix'] == 'yes') {
    echo "<h2>3. Correction en cours...</h2>";
    
    // Mettre à jour les statuts numériques vers les valeurs ENUM correctes
    // Note: MySQL ENUM stocke les valeurs comme 1, 2, 3... mais on doit utiliser les chaînes
    $fix_queries = [
        "UPDATE student_attendance SET status = 'present', updated_at = NOW() WHERE status = '1' OR status = 1",
        "UPDATE student_attendance SET status = 'absent', updated_at = NOW() WHERE status = '2' OR status = 2",
        "UPDATE student_attendance SET status = 'present', updated_at = NOW() WHERE status = '0' OR status = 0"
    ];
    
    $total_fixed = 0;
    foreach ($fix_queries as $fix_query) {
        if ($conn->query($fix_query)) {
            $fixed = $conn->affected_rows;
            $total_fixed += $fixed;
            if ($fixed > 0) {
                echo "<p class='success'>✅ " . $fixed . " enregistrement(s) corrigé(s)</p>";
            }
        } else {
            echo "<p class='error'>❌ Erreur: " . $conn->error . "</p>";
        }
    }
    
    if ($total_fixed > 0) {
        echo "<p class='success'><strong>✅ Correction terminée!</strong></p>";
        echo "<p><strong>Nombre total d'enregistrements corrigés:</strong> $total_fixed</p>";
    } else {
        echo "<p class='warning'>Aucun enregistrement n'a été corrigé. Peut-être qu'ils ont déjà été corrigés.</p>";
    }
} else {
    echo "<h2>3. Lancer la correction</h2>";
    echo "<p class='warning'><strong>⚠️ Attention:</strong> Cette opération va convertir tous les statuts numériques vers les valeurs ENUM correctes:</p>";
    echo "<ul>";
    echo "<li>1 → 'present'</li>";
    echo "<li>2 → 'absent'</li>";
    echo "<li>0 → 'present'</li>";
    echo "</ul>";
    echo "<p><strong>Nombre d'enregistrements à corriger:</strong> $total_to_fix</p>";
    echo "<form method='POST'>";
    echo "<input type='hidden' name='fix' value='yes'>";
    echo "<button type='submit' class='btn btn-danger' onclick='return confirm(\"Êtes-vous sûr de vouloir corriger " . $total_to_fix . " enregistrements?\")'>Corriger les statuts numériques</button>";
    echo "</form>";
}

echo "<hr>";
echo "<p><a href='debug_absences_bulletin.php' class='btn'>Retour au diagnostic</a></p>";
echo "<p><a href='fix_empty_status_attendance.php' class='btn'>Corriger les statuts vides</a></p>";
echo "<p><a href='manageBulletins.php' class='btn'>Gérer les bulletins</a></p>";
echo "</body></html>";
?>

