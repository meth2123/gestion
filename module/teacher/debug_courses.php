<?php
include_once('main.php');
include_once('../../service/db_utils.php');

// Vérification de la session
if (!isset($_SESSION['login_id'])) {
    header("Location: ../../index.php");
    exit();
}

$teacher_id = $_SESSION['login_id'];
$check = $teacher_id;

// Récupérer les informations du professeur
$teacher_info = db_fetch_row(
    "SELECT * FROM teachers WHERE id = ?",
    [$teacher_id],
    's'
);

if (!$teacher_info) {
    header("Location: index.php?error=teacher_not_found");
    exit();
}

// Récupérer la classe CP
$cp_class = db_fetch_row(
    "SELECT * FROM class WHERE name = 'cp'",
    [],
    ''
);

if (!$cp_class) {
    echo "<h1>Classe CP non trouvée</h1>";
    exit();
}

$class_id = $cp_class['id'];

echo "<h1>Débogage des cours pour la classe CP</h1>";
echo "<p>ID de la classe CP: " . htmlspecialchars($class_id) . "</p>";
echo "<p>ID de l'enseignant: " . htmlspecialchars($teacher_id) . "</p>";

// Vérifier si l'enseignant est assigné directement à des cours dans cette classe
echo "<h2>Cours assignés directement à l'enseignant dans la classe CP</h2>";
$direct_courses = db_fetch_all(
    "SELECT * FROM course WHERE classid = ? AND teacherid = ?",
    [$class_id, $teacher_id],
    'ss'
);

if ($direct_courses) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Nom</th><th>ClassID</th><th>TeacherID</th></tr>";
    foreach ($direct_courses as $course) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($course['id']) . "</td>";
        echo "<td>" . htmlspecialchars($course['name']) . "</td>";
        echo "<td>" . htmlspecialchars($course['classid']) . "</td>";
        echo "<td>" . htmlspecialchars($course['teacherid']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Aucun cours assigné directement à l'enseignant dans cette classe.</p>";
}

// Vérifier si l'enseignant est assigné à des cours via student_teacher_course
echo "<h2>Cours assignés via student_teacher_course</h2>";
$stc_courses = db_fetch_all(
    "SELECT c.*, stc.class_id, stc.teacher_id 
     FROM course c
     JOIN student_teacher_course stc ON c.id = stc.course_id
     WHERE stc.class_id = ? AND stc.teacher_id = ?",
    [$class_id, $teacher_id],
    'ss'
);

if ($stc_courses) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Nom</th><th>ClassID (course)</th><th>TeacherID (course)</th><th>ClassID (stc)</th><th>TeacherID (stc)</th></tr>";
    foreach ($stc_courses as $course) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($course['id']) . "</td>";
        echo "<td>" . htmlspecialchars($course['name']) . "</td>";
        echo "<td>" . htmlspecialchars($course['classid']) . "</td>";
        echo "<td>" . htmlspecialchars($course['teacherid']) . "</td>";
        echo "<td>" . htmlspecialchars($course['class_id']) . "</td>";
        echo "<td>" . htmlspecialchars($course['teacher_id']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Aucun cours assigné via student_teacher_course.</p>";
}

// Vérifier tous les cours disponibles pour cette classe
echo "<h2>Tous les cours disponibles pour la classe CP</h2>";
$all_courses = db_fetch_all(
    "SELECT * FROM course WHERE classid = ?",
    [$class_id],
    's'
);

if ($all_courses) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Nom</th><th>ClassID</th><th>TeacherID</th></tr>";
    foreach ($all_courses as $course) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($course['id']) . "</td>";
        echo "<td>" . htmlspecialchars($course['name']) . "</td>";
        echo "<td>" . htmlspecialchars($course['classid']) . "</td>";
        echo "<td>" . htmlspecialchars($course['teacherid']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Aucun cours disponible pour cette classe.</p>";
}

// Afficher la requête SQL utilisée dans courses.php
echo "<h2>Requête SQL utilisée dans courses.php</h2>";
$sql = "SELECT DISTINCT c.*, 
        (SELECT COUNT(DISTINCT stc.student_id) FROM student_teacher_course stc WHERE stc.course_id = c.id) as student_count,
        (SELECT COUNT(DISTINCT stc.student_id) FROM student_teacher_course stc WHERE stc.course_id = c.id AND stc.grade IS NOT NULL) as graded_count
 FROM course c 
 LEFT JOIN student_teacher_course stc ON c.id = stc.course_id
 WHERE c.classid = ? 
 AND (
     c.teacherid = ? 
     OR EXISTS (
         SELECT 1 FROM student_teacher_course stc2 
         WHERE stc2.course_id = c.id 
         AND stc2.teacher_id = ?
         AND stc2.class_id = ?
     )
 )
 ORDER BY c.name";

echo "<pre>" . htmlspecialchars($sql) . "</pre>";

// Exécuter la requête avec les paramètres réels
$debug_courses = db_fetch_all(
    $sql,
    [$class_id, $teacher_id, $teacher_id, $class_id],
    'ssss'
);

echo "<h2>Résultat de la requête SQL</h2>";
if ($debug_courses) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Nom</th><th>ClassID</th><th>TeacherID</th><th>Student Count</th><th>Graded Count</th></tr>";
    foreach ($debug_courses as $course) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($course['id']) . "</td>";
        echo "<td>" . htmlspecialchars($course['name']) . "</td>";
        echo "<td>" . htmlspecialchars($course['classid']) . "</td>";
        echo "<td>" . htmlspecialchars($course['teacherid']) . "</td>";
        echo "<td>" . htmlspecialchars($course['student_count']) . "</td>";
        echo "<td>" . htmlspecialchars($course['graded_count']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>La requête ne retourne aucun résultat.</p>";
}

// Vérifier les entrées dans la table student_teacher_course
echo "<h2>Entrées dans la table student_teacher_course</h2>";
$stc_entries = db_fetch_all(
    "SELECT * FROM student_teacher_course WHERE class_id = ? OR teacher_id = ?",
    [$class_id, $teacher_id],
    'ss'
);

if ($stc_entries) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Student ID</th><th>Teacher ID</th><th>Course ID</th><th>Class ID</th><th>Grade</th></tr>";
    foreach ($stc_entries as $entry) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($entry['id'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($entry['student_id'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($entry['teacher_id'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($entry['course_id'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($entry['class_id'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($entry['grade'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Aucune entrée trouvée dans la table student_teacher_course.</p>";
}
?>
