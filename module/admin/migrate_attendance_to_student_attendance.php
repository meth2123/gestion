<?php
/**
 * Script de migration des absences de la table attendance vers student_attendance
 * 
 * Ce script :
 * 1. Vérifie s'il y a des données dans l'ancienne table attendance
 * 2. Migre les données vers student_attendance
 * 3. Affiche un rapport détaillé
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
    <title>Migration des Absences</title>
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
    <h1>Migration des Absences vers student_attendance</h1>";

// Vérifier si la table attendance existe
$check_attendance = $conn->query("SHOW TABLES LIKE 'attendance'");
if ($check_attendance->num_rows == 0) {
    echo "<p class='error'>La table 'attendance' n'existe pas.</p>";
    echo "<p><a href='debug_absences_bulletin.php' class='btn'>Retour au diagnostic</a></p>";
    exit;
}

// Vérifier si la table student_attendance existe
$check_student_attendance = $conn->query("SHOW TABLES LIKE 'student_attendance'");
if ($check_student_attendance->num_rows == 0) {
    echo "<p class='error'>La table 'student_attendance' n'existe pas. Veuillez d'abord créer cette table.</p>";
    echo "<p><a href='debug_absences_bulletin.php' class='btn'>Retour au diagnostic</a></p>";
    exit;
}

// 1. Compter les enregistrements dans attendance pour les élèves
echo "<h2>1. Analyse des données dans la table 'attendance'</h2>";

$count_query = "
SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN person_type = 'student' THEN 1 END) as students,
    COUNT(CASE WHEN person_type = 'teacher' THEN 1 END) as teachers,
    COUNT(CASE WHEN person_type = 'staff' THEN 1 END) as staff,
    COUNT(CASE WHEN person_type IS NULL THEN 1 END) as null_type,
    MIN(datetime) as min_date,
    MAX(datetime) as max_date
FROM attendance";
$count_result = $conn->query($count_query);
$count_data = $count_result->fetch_assoc();

echo "<table>";
echo "<tr><th>Type</th><th>Nombre</th></tr>";
echo "<tr><td><strong>Total enregistrements</strong></td><td><strong>" . $count_data['total'] . "</strong></td></tr>";
echo "<tr><td>Élèves (person_type='student')</td><td>" . $count_data['students'] . "</td></tr>";
echo "<tr><td>Enseignants (person_type='teacher')</td><td>" . $count_data['teachers'] . "</td></tr>";
echo "<tr><td>Personnel (person_type='staff')</td><td>" . $count_data['staff'] . "</td></tr>";
echo "<tr><td>Type NULL (à vérifier)</td><td>" . $count_data['null_type'] . "</td></tr>";
echo "<tr><td>Date minimum</td><td>" . ($count_data['min_date'] ?? 'N/A') . "</td></tr>";
echo "<tr><td>Date maximum</td><td>" . ($count_data['max_date'] ?? 'N/A') . "</td></tr>";
echo "</table>";

// 2. Vérifier les élèves dans attendance qui commencent par 'st' (format probable des IDs d'élèves)
echo "<h2>2. Élèves potentiels dans 'attendance' (attendedid LIKE 'st%')</h2>";

$students_query = "
SELECT 
    COUNT(*) as total,
    COUNT(DISTINCT attendedid) as unique_students
FROM attendance
WHERE attendedid LIKE 'st%' OR attendedid LIKE 'ST%'";
$students_result = $conn->query($students_query);
$students_data = $students_result->fetch_assoc();

echo "<p><strong>Total enregistrements avec ID commençant par 'st':</strong> " . $students_data['total'] . "</p>";
echo "<p><strong>Élèves uniques:</strong> " . $students_data['unique_students'] . "</p>";

// 3. Vérifier combien sont déjà dans student_attendance
echo "<h2>3. Vérification des doublons</h2>";

$duplicate_check = "
SELECT COUNT(*) as already_migrated
FROM attendance a
INNER JOIN student_attendance sa 
    ON CAST(a.attendedid AS CHAR) = CAST(sa.student_id AS CHAR)
    AND DATE(a.datetime) = DATE(sa.datetime)
WHERE (a.attendedid LIKE 'st%' OR a.attendedid LIKE 'ST%')
    AND (a.person_type = 'student' OR a.person_type IS NULL)";
$duplicate_result = $conn->query($duplicate_check);
$duplicate_data = $duplicate_result->fetch_assoc();

echo "<p><strong>Enregistrements déjà migrés (doublons):</strong> " . $duplicate_data['already_migrated'] . "</p>";

// 4. Afficher un échantillon des données à migrer
echo "<h2>4. Échantillon des données à migrer</h2>";

$sample_query = "
SELECT 
    a.id,
    a.attendedid as student_id,
    a.datetime,
    a.status,
    a.comment,
    a.course_id,
    s.classid as class_id,
    CASE 
        WHEN a.course_id IS NULL THEN NULL
        ELSE a.course_id
    END as course_id_final
FROM attendance a
LEFT JOIN students s ON CAST(a.attendedid AS CHAR) = CAST(s.id AS CHAR)
WHERE (a.attendedid LIKE 'st%' OR a.attendedid LIKE 'ST%')
    AND (a.person_type = 'student' OR a.person_type IS NULL)
    AND NOT EXISTS (
        SELECT 1 FROM student_attendance sa 
        WHERE CAST(sa.student_id AS CHAR) = CAST(a.attendedid AS CHAR)
        AND DATE(sa.datetime) = DATE(a.datetime)
        AND (sa.course_id = a.course_id OR (sa.course_id IS NULL AND a.course_id IS NULL))
    )
ORDER BY a.datetime DESC
LIMIT 20";
$sample_result = $conn->query($sample_query);

if ($sample_result->num_rows > 0) {
    echo "<p><strong>Échantillon de " . $sample_result->num_rows . " enregistrements à migrer:</strong></p>";
    echo "<table>";
    echo "<tr><th>ID attendance</th><th>Student ID</th><th>Class ID</th><th>Course ID</th><th>Date/Time</th><th>Status</th><th>Comment</th></tr>";
    while ($row = $sample_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['student_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['class_id'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['course_id_final'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['datetime']) . "</td>";
        echo "<td>" . htmlspecialchars($row['status'] ?? 'present') . "</td>";
        echo "<td>" . htmlspecialchars($row['comment'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='success'>Aucune donnée à migrer trouvée (soit déjà migrée, soit aucune donnée d'élève dans attendance).</p>";
}

// 5. Bouton pour lancer la migration
if (isset($_POST['migrate']) && $_POST['migrate'] == 'yes') {
    echo "<h2>5. Migration en cours...</h2>";
    
    $migrate_query = "
    INSERT INTO student_attendance 
        (student_id, course_id, class_id, datetime, status, comment, created_by)
    SELECT 
        a.attendedid as student_id,
        COALESCE(a.course_id, 0) as course_id,
        COALESCE(s.classid, '') as class_id,
        a.datetime,
        COALESCE(a.status, 'present') as status,
        a.comment,
        a.created_by
    FROM attendance a
    LEFT JOIN students s ON CAST(a.attendedid AS CHAR) = CAST(s.id AS CHAR)
    WHERE (a.attendedid LIKE 'st%' OR a.attendedid LIKE 'ST%')
        AND (a.person_type = 'student' OR a.person_type IS NULL)
        AND s.id IS NOT NULL  -- S'assurer que l'élève existe dans la table students
        AND s.classid IS NOT NULL  -- S'assurer que l'élève a une classe
        AND NOT EXISTS (
            SELECT 1 FROM student_attendance sa 
            WHERE CAST(sa.student_id AS CHAR) = CAST(a.attendedid AS CHAR)
            AND DATE(sa.datetime) = DATE(a.datetime)
            AND (sa.course_id = COALESCE(a.course_id, 0) OR (sa.course_id IS NULL AND a.course_id IS NULL))
        )";
    
    if ($conn->query($migrate_query)) {
        $migrated_count = $conn->affected_rows;
        echo "<p class='success'><strong>✅ Migration réussie!</strong></p>";
        echo "<p><strong>Nombre d'enregistrements migrés:</strong> $migrated_count</p>";
    } else {
        echo "<p class='error'><strong>❌ Erreur lors de la migration:</strong> " . $conn->error . "</p>";
    }
} else {
    // Compter combien seront migrés
    $count_migrate = "
    SELECT COUNT(*) as count
    FROM attendance a
    INNER JOIN students s ON CAST(a.attendedid AS CHAR) = CAST(s.id AS CHAR)
    WHERE (a.attendedid LIKE 'st%' OR a.attendedid LIKE 'ST%')
        AND (a.person_type = 'student' OR a.person_type IS NULL)
        AND s.classid IS NOT NULL
        AND NOT EXISTS (
            SELECT 1 FROM student_attendance sa 
            WHERE CAST(sa.student_id AS CHAR) = CAST(a.attendedid AS CHAR)
            AND DATE(sa.datetime) = DATE(a.datetime)
            AND (sa.course_id = COALESCE(a.course_id, 0) OR (sa.course_id IS NULL AND a.course_id IS NULL))
        )";
    $count_result = $conn->query($count_migrate);
    $count_migrate_data = $count_result->fetch_assoc();
    
    if ($count_migrate_data['count'] > 0) {
        echo "<h2>5. Lancer la migration</h2>";
        echo "<p><strong>Nombre d'enregistrements à migrer:</strong> " . $count_migrate_data['count'] . "</p>";
        echo "<form method='POST'>";
        echo "<input type='hidden' name='migrate' value='yes'>";
        echo "<button type='submit' class='btn btn-danger' onclick='return confirm(\"Êtes-vous sûr de vouloir migrer " . $count_migrate_data['count'] . " enregistrements?\")'>Lancer la migration</button>";
        echo "</form>";
    } else {
        echo "<h2>5. Migration</h2>";
        echo "<p class='success'>Aucune donnée à migrer. Toutes les données sont déjà dans student_attendance.</p>";
    }
}

echo "<hr>";
echo "<p><a href='debug_absences_bulletin.php' class='btn'>Retour au diagnostic</a></p>";
echo "<p><a href='manageBulletins.php' class='btn'>Gérer les bulletins</a></p>";
echo "</body></html>";
?>

