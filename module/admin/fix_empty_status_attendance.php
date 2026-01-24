<?php
/**
 * Script pour corriger les enregistrements dans student_attendance qui ont un statut vide
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
    <title>Correction des Statuts Vides</title>
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
    <h1>Correction des Statuts Vides dans student_attendance</h1>";

// 1. Compter les enregistrements avec statut vide
$count_query = "
SELECT COUNT(*) as total
FROM student_attendance
WHERE status IS NULL OR status = '' OR TRIM(status) = ''";
$count_result = $conn->query($count_query);
$count_data = $count_result->fetch_assoc();

echo "<h2>1. Analyse</h2>";
echo "<p><strong>Nombre d'enregistrements avec statut vide:</strong> " . $count_data['total'] . "</p>";

if ($count_data['total'] == 0) {
    echo "<p class='success'>✅ Aucun enregistrement avec statut vide trouvé. Tout est correct!</p>";
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
    comment,
    created_at
FROM student_attendance
WHERE status IS NULL OR status = '' OR TRIM(status) = ''
ORDER BY created_at DESC
LIMIT 20";
$sample_result = $conn->query($sample_query);

if ($sample_result->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Student ID</th><th>Course ID</th><th>Class ID</th><th>Date/Time</th><th>Status (actuel)</th><th>Comment</th><th>Créé le</th></tr>";
    while ($row = $sample_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['student_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['course_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['class_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['datetime']) . "</td>";
        echo "<td class='error'><strong>" . (empty($row['status']) ? 'VIDE' : htmlspecialchars($row['status'])) . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['comment'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 3. Corriger les enregistrements
if (isset($_POST['fix']) && $_POST['fix'] == 'yes') {
    echo "<h2>3. Correction en cours...</h2>";
    
    // Mettre à jour tous les enregistrements avec statut vide à 'present' par défaut
    $fix_query = "
    UPDATE student_attendance
    SET status = 'present',
        updated_at = NOW()
    WHERE status IS NULL OR status = '' OR TRIM(status) = ''";
    
    if ($conn->query($fix_query)) {
        $fixed_count = $conn->affected_rows;
        echo "<p class='success'><strong>✅ Correction réussie!</strong></p>";
        echo "<p><strong>Nombre d'enregistrements corrigés:</strong> $fixed_count</p>";
        echo "<p class='warning'>⚠️ Note: Tous les statuts vides ont été mis à 'present' par défaut. Vous devrez peut-être les corriger manuellement si certains étaient des absences.</p>";
    } else {
        echo "<p class='error'><strong>❌ Erreur lors de la correction:</strong> " . $conn->error . "</p>";
    }
} else {
    echo "<h2>3. Lancer la correction</h2>";
    echo "<p class='warning'><strong>⚠️ Attention:</strong> Cette opération va mettre tous les statuts vides à 'present' par défaut.</p>";
    echo "<p>Si certains enregistrements étaient des absences, vous devrez les corriger manuellement après.</p>";
    echo "<form method='POST'>";
    echo "<input type='hidden' name='fix' value='yes'>";
    echo "<button type='submit' class='btn btn-danger' onclick='return confirm(\"Êtes-vous sûr de vouloir corriger " . $count_data['total'] . " enregistrements? Tous les statuts vides seront mis à \\'present\\'.\")'>Corriger les statuts vides</button>";
    echo "</form>";
}

echo "<hr>";
echo "<p><a href='debug_absences_bulletin.php' class='btn'>Retour au diagnostic</a></p>";
echo "<p><a href='manageBulletins.php' class='btn'>Gérer les bulletins</a></p>";
echo "</body></html>";
?>

