<?php
/**
 * Script de diagnostic pour vérifier pourquoi les absences ne s'affichent pas dans les bulletins
 * 
 * Usage: Accéder via navigateur avec les paramètres:
 * ?student=STUDENT_ID&class=CLASS_ID&period=1
 */

include_once('main.php');
include_once('includes/auth_check.php');
include_once('../../service/db_utils.php');

// La vérification de la session admin est déjà faite dans auth_check.php
global $link;
$conn = $link;

$student_id = $_GET['student'] ?? '';
$class_id = $_GET['class'] ?? '';
$period = $_GET['period'] ?? '1';

// Si les paramètres ne sont pas fournis, afficher un formulaire de sélection
if (empty($student_id) || empty($class_id)) {
    // Récupérer toutes les classes
    $classes = db_fetch_all(
        "SELECT id, name FROM class WHERE created_by = ? OR created_by = '21' ORDER BY name",
        [$admin_id],
        's'
    );
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Diagnostic Absences - Sélection</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .form-group { margin: 15px 0; }
            label { display: block; margin-bottom: 5px; font-weight: bold; }
            select, input { padding: 8px; width: 300px; }
            button { padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer; }
            button:hover { background: #0056b3; }
        </style>
    </head>
    <body>
        <h1>Diagnostic des Absences pour le Bulletin</h1>
        <p>Veuillez sélectionner un élève et une classe pour effectuer le diagnostic.</p>
        
        <form method='GET' action=''>
            <div class='form-group'>
                <label for='class'>Classe:</label>
                <select name='class' id='class' required onchange='loadStudents(this.value)'>
                    <option value=''>-- Sélectionner une classe --</option>";
    
    foreach ($classes as $class) {
        $selected = ($class_id == $class['id']) ? 'selected' : '';
        echo "<option value='" . htmlspecialchars($class['id']) . "' $selected>" . htmlspecialchars($class['name']) . "</option>";
    }
    
    echo "</select>
            </div>
            
            <div class='form-group'>
                <label for='student'>Élève:</label>
                <select name='student' id='student' required>
                    <option value=''>-- Sélectionner d'abord une classe --</option>";
    
    // Si une classe est déjà sélectionnée, charger les élèves
    if (!empty($class_id)) {
        $students = db_fetch_all(
            "SELECT id, name FROM students WHERE classid = ? ORDER BY name",
            [$class_id],
            's'
        );
        foreach ($students as $student) {
            $selected = ($student_id == $student['id']) ? 'selected' : '';
            echo "<option value='" . htmlspecialchars($student['id']) . "' $selected>" . htmlspecialchars($student['name']) . "</option>";
        }
    }
    
    echo "</select>
            </div>
            
            <div class='form-group'>
                <label for='period'>Période:</label>
                <select name='period' id='period'>
                    <option value='1' " . ($period == '1' ? 'selected' : '') . ">Semestre 1</option>
                    <option value='2' " . ($period == '2' ? 'selected' : '') . ">Semestre 2</option>
                </select>
            </div>
            
            <button type='submit'>Lancer le diagnostic</button>
        </form>
        
        <script>
        function loadStudents(classId) {
            if (!classId) {
                document.getElementById('student').innerHTML = '<option value=\"\">-- Sélectionner d\'abord une classe --</option>';
                return;
            }
            
            // Charger les élèves via AJAX
            fetch('?class=' + classId + '&ajax=students')
                .then(response => response.text())
                .then(html => {
                    document.getElementById('student').innerHTML = html;
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    // Fallback: recharger la page avec la classe sélectionnée
                    window.location.href = '?class=' + classId;
                });
        }
        </script>
    </body>
    </html>";
    exit;
}

// Gestion AJAX pour charger les élèves
if (isset($_GET['ajax']) && $_GET['ajax'] == 'students' && !empty($class_id)) {
    $students = db_fetch_all(
        "SELECT id, name FROM students WHERE classid = ? ORDER BY name",
        [$class_id],
        's'
    );
    echo "<option value=''>-- Sélectionner un élève --</option>";
    foreach ($students as $student) {
        echo "<option value='" . htmlspecialchars($student['id']) . "'>" . htmlspecialchars($student['name']) . "</option>";
    }
    exit;
}

echo "<h1>Diagnostic des Absences pour le Bulletin</h1>";
echo "<p><strong>Élève:</strong> $student_id</p>";
echo "<p><strong>Classe:</strong> $class_id</p>";
echo "<p><strong>Période:</strong> $period</p>";
echo "<hr>";

// 1. Vérifier la structure de la table student_attendance
echo "<h2>1. Structure de la table student_attendance</h2>";
$structure = $conn->query("DESCRIBE student_attendance");
if ($structure) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Champ</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $structure->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 2. Vérifier toutes les absences pour cet élève (sans filtre)
echo "<h2>2. Toutes les absences pour cet élève (sans filtre de date)</h2>";
$all_query = "
SELECT 
    sa.id,
    sa.student_id,
    sa.class_id,
    sa.course_id,
    sa.datetime,
    sa.status,
    sa.comment,
    c.name as course_name,
    c.id as course_table_id
FROM student_attendance sa
LEFT JOIN course c ON CAST(sa.course_id AS CHAR) = CAST(c.id AS CHAR)
WHERE CAST(sa.student_id AS CHAR) = CAST(? AS CHAR)
AND CAST(sa.class_id AS CHAR) = CAST(? AS CHAR)
ORDER BY sa.datetime DESC
LIMIT 50";
$stmt = $conn->prepare($all_query);
$stmt->bind_param("ss", $student_id, $class_id);
$stmt->execute();
$all_results = $stmt->get_result();

echo "<p><strong>Total trouvé:</strong> " . $all_results->num_rows . " enregistrements</p>";
if ($all_results->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Student ID</th><th>Class ID</th><th>Course ID</th><th>Course Name</th><th>Date/Time</th><th>Status</th><th>Comment</th></tr>";
    while ($row = $all_results->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['student_id']) . " (type: " . gettype($row['student_id']) . ")</td>";
        echo "<td>" . htmlspecialchars($row['class_id']) . " (type: " . gettype($row['class_id']) . ")</td>";
        echo "<td>" . htmlspecialchars($row['course_id']) . " (type: " . gettype($row['course_id']) . ")</td>";
        echo "<td>" . htmlspecialchars($row['course_name'] ?? 'NULL') . " (course.id: " . htmlspecialchars($row['course_table_id'] ?? 'NULL') . ")</td>";
        echo "<td>" . htmlspecialchars($row['datetime']) . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['comment'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'><strong>AUCUNE DONNÉE TROUVÉE dans student_attendance pour cet élève et cette classe!</strong></p>";
}
$stmt->close();

// 3. Vérifier les absences avec filtre de statut
echo "<h2>3. Absences et retards uniquement (status IN ('absent', 'late'))</h2>";
$absent_query = "
SELECT COUNT(*) as count, sa.status
FROM student_attendance sa
WHERE CAST(sa.student_id AS CHAR) = CAST(? AS CHAR)
AND CAST(sa.class_id AS CHAR) = CAST(? AS CHAR)
AND sa.status IN ('absent', 'late')
GROUP BY sa.status";
$stmt = $conn->prepare($absent_query);
$stmt->bind_param("ss", $student_id, $class_id);
$stmt->execute();
$absent_results = $stmt->get_result();
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Status</th><th>Count</th></tr>";
$total_absent = 0;
while ($row = $absent_results->fetch_assoc()) {
    echo "<tr><td>" . htmlspecialchars($row['status']) . "</td><td>" . $row['count'] . "</td></tr>";
    $total_absent += $row['count'];
}
echo "<tr><td><strong>Total</strong></td><td><strong>$total_absent</strong></td></tr>";
echo "</table>";
$stmt->close();

// 4. Calculer les dates du semestre (même logique que viewBulletin.php)
echo "<h2>4. Dates du semestre calculées</h2>";
$semester_start = null;
$semester_end = null;
$current_year = date('Y');
$current_month = (int)date('m');

if ($period == '1') {
    if ($current_month >= 9) {
        $semester_start = $current_year . '-09-01';
        $semester_end = ($current_year + 1) . '-01-31';
    } else {
        $semester_start = ($current_year - 1) . '-09-01';
        $semester_end = $current_year . '-01-31';
    }
} elseif ($period == '2') {
    if ($current_month >= 2 && $current_month <= 6) {
        $semester_start = $current_year . '-02-01';
        $semester_end = $current_year . '-06-30';
    } elseif ($current_month == 1) {
        $semester_start = ($current_year - 1) . '-02-01';
        $semester_end = ($current_year - 1) . '-06-30';
    } else {
        $semester_start = $current_year . '-02-01';
        $semester_end = $current_year . '-06-30';
    }
} else {
    $semester_start = date('Y-m-d', strtotime('-30 days'));
    $semester_end = date('Y-m-d');
}

echo "<p><strong>Date début semestre:</strong> $semester_start</p>";
echo "<p><strong>Date fin semestre:</strong> $semester_end</p>";

// 5. Vérifier les absences dans la période du semestre
echo "<h2>5. Absences dans la période du semestre</h2>";
$period_query = "
SELECT 
    sa.id,
    DATE(sa.datetime) as date,
    sa.status,
    sa.comment
FROM student_attendance sa
WHERE CAST(sa.student_id AS CHAR) = CAST(? AS CHAR)
AND CAST(sa.class_id AS CHAR) = CAST(? AS CHAR)
AND sa.status IN ('absent', 'late')
AND DATE(sa.datetime) >= ?
AND DATE(sa.datetime) <= ?
ORDER BY sa.datetime DESC";
$stmt = $conn->prepare($period_query);
$stmt->bind_param("ssss", $student_id, $class_id, $semester_start, $semester_end);
$stmt->execute();
$period_results = $stmt->get_result();

echo "<p><strong>Total trouvé dans la période:</strong> " . $period_results->num_rows . " absences</p>";
if ($period_results->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Date</th><th>Status</th><th>Comment</th></tr>";
    while ($row = $period_results->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['date']) . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['comment'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: orange;'><strong>Aucune absence trouvée dans la période du semestre.</strong></p>";
    echo "<p>Vérifiez que les dates des absences enregistrées sont bien dans la plage: $semester_start à $semester_end</p>";
}
$stmt->close();

// 6. Vérifier les types de données dans les tables
echo "<h2>6. Types de données dans les tables</h2>";
$types_query = "
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    DATA_TYPE,
    CHARACTER_MAXIMUM_LENGTH
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME IN ('student_attendance', 'students', 'class', 'course')
AND COLUMN_NAME IN ('id', 'student_id', 'class_id', 'course_id')
ORDER BY TABLE_NAME, COLUMN_NAME";
$types_result = $conn->query($types_query);
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Table</th><th>Colonne</th><th>Type</th><th>Longueur</th></tr>";
while ($row = $types_result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['TABLE_NAME']) . "</td>";
    echo "<td>" . htmlspecialchars($row['COLUMN_NAME']) . "</td>";
    echo "<td>" . htmlspecialchars($row['DATA_TYPE']) . "</td>";
    echo "<td>" . htmlspecialchars($row['CHARACTER_MAXIMUM_LENGTH'] ?? 'N/A') . "</td>";
    echo "</tr>";
}
echo "</table>";

// 7. Test de la requête exacte utilisée dans viewBulletin.php
echo "<h2>7. Test de la requête exacte de viewBulletin.php</h2>";
$exact_query = "
SELECT 
    DATE_FORMAT(sa.datetime, '%d/%m/%Y') as date,
    TIME(sa.datetime) as course_time,
    COALESCE(c.name, 'Cours supprimé') as course_name,
    COALESCE(t.name, 'Professeur non assigné') as teacher_name,
    sa.status,
    sa.comment,
    sa.datetime as raw_datetime,
    DATE(sa.datetime) as absence_date
FROM student_attendance sa
LEFT JOIN course c ON CAST(sa.course_id AS CHAR) = CAST(c.id AS CHAR)
LEFT JOIN teachers t ON CAST(c.teacherid AS CHAR) = CAST(t.id AS CHAR)
WHERE CAST(sa.student_id AS CHAR) = CAST(? AS CHAR)
AND CAST(sa.class_id AS CHAR) = CAST(? AS CHAR)
AND sa.status IN ('absent', 'late')
AND DATE(sa.datetime) >= ?
AND DATE(sa.datetime) <= ?
ORDER BY sa.datetime DESC";
$stmt = $conn->prepare($exact_query);
$stmt->bind_param("ssss", $student_id, $class_id, $semester_start, $semester_end);
$stmt->execute();
$exact_results = $stmt->get_result();

echo "<p><strong>Résultat de la requête exacte:</strong> " . $exact_results->num_rows . " lignes</p>";
if ($exact_results->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Date</th><th>Heure</th><th>Cours</th><th>Professeur</th><th>Status</th><th>Commentaire</th></tr>";
    while ($row = $exact_results->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['date']) . "</td>";
        echo "<td>" . htmlspecialchars($row['course_time']) . "</td>";
        echo "<td>" . htmlspecialchars($row['course_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['teacher_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['comment'] ?? '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'><strong>La requête exacte ne retourne AUCUN résultat!</strong></p>";
}
$stmt->close();

// 8. Vérifier s'il y a des données dans l'ancienne table attendance
echo "<h2>8. Vérification de l'ancienne table 'attendance'</h2>";
$old_attendance_check = $conn->query("
SELECT COUNT(*) as total
FROM attendance
WHERE (attendedid LIKE 'st%' OR attendedid LIKE 'ST%')
    AND (person_type = 'student' OR person_type IS NULL)
    AND CAST(attendedid AS CHAR) = CAST(? AS CHAR)
");
$old_stmt = $conn->prepare("
SELECT COUNT(*) as total
FROM attendance
WHERE (attendedid LIKE 'st%' OR attendedid LIKE 'ST%')
    AND (person_type = 'student' OR person_type IS NULL)
    AND CAST(attendedid AS CHAR) = CAST(? AS CHAR)
");
$old_stmt->bind_param("s", $student_id);
$old_stmt->execute();
$old_result = $old_stmt->get_result()->fetch_assoc();
$old_stmt->close();

echo "<p><strong>Enregistrements dans l'ancienne table 'attendance' pour cet élève:</strong> " . ($old_result['total'] ?? 0) . "</p>";

if (($old_result['total'] ?? 0) > 0) {
    echo "<p class='warning'><strong>⚠️ ATTENTION:</strong> Il y a des données dans l'ancienne table 'attendance' qui ne sont pas dans 'student_attendance'!</p>";
    echo "<p><a href='migrate_attendance_to_student_attendance.php' class='btn' style='background: #dc3545;'>Migrer les données vers student_attendance</a></p>";
}

echo "<hr>";
echo "<p><a href='viewBulletin.php?student=$student_id&class=$class_id&period=$period'>Retour au bulletin</a></p>";
echo "<p><a href='migrate_attendance_to_student_attendance.php'>Voir toutes les données à migrer</a></p>";
?>

