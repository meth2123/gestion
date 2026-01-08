<?php
// Configuration de la base de données
require_once('db/config.php');
require_once('service/db_utils.php');

// Connexion à la base de données
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ID de l'enseignant et de la classe à vérifier
$teacher_id = 'ad-123-1'; // Remplacez par l'ID de l'enseignant connecté
$class_id = 'CLS-MAT-A-834'; // Classe maternelle

echo "<h2>Cours disponibles pour la classe: $class_id</h2>";

// 1. Cours directement assignés à l'enseignant pour cette classe
$courses_from_direct = db_fetch_all(
    "SELECT id, name, teacherid, classid FROM course 
     WHERE classid = ? AND teacherid = ?",
    [$class_id, $teacher_id],
    'ss'
);

echo "<h3>1. Cours directement assignés à l'enseignant pour cette classe</h3>";
if ($courses_from_direct) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Nom</th><th>Enseignant ID</th><th>Classe ID</th></tr>";
    foreach ($courses_from_direct as $course) {
        echo "<tr>";
        echo "<td>" . $course['id'] . "</td>";
        echo "<td>" . $course['name'] . "</td>";
        echo "<td>" . $course['teacherid'] . "</td>";
        echo "<td>" . $course['classid'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Aucun cours directement assigné.</p>";
}

// 2. Cours assignés via student_teacher_course
$courses_from_stc = db_fetch_all(
    "SELECT DISTINCT c.id, c.name, c.teacherid, c.classid, stc.class_id as stc_class_id
     FROM course c
     JOIN student_teacher_course stc ON c.id = stc.course_id
     WHERE stc.class_id = ? AND stc.teacher_id = ?",
    [$class_id, $teacher_id],
    'ss'
);

echo "<h3>2. Cours assignés via student_teacher_course</h3>";
if ($courses_from_stc) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Nom</th><th>Enseignant ID</th><th>Classe ID (course)</th><th>Classe ID (stc)</th></tr>";
    foreach ($courses_from_stc as $course) {
        echo "<tr>";
        echo "<td>" . $course['id'] . "</td>";
        echo "<td>" . $course['name'] . "</td>";
        echo "<td>" . $course['teacherid'] . "</td>";
        echo "<td>" . $course['classid'] . "</td>";
        echo "<td>" . $course['stc_class_id'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Aucun cours assigné via student_teacher_course.</p>";
}

// 3. Tous les cours pour cette classe (pour vérification)
$all_courses = db_fetch_all(
    "SELECT id, name, teacherid, classid FROM course WHERE classid = ?",
    [$class_id],
    's'
);

echo "<h3>3. Tous les cours pour cette classe (indépendamment de l'enseignant)</h3>";
if ($all_courses) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Nom</th><th>Enseignant ID</th><th>Classe ID</th></tr>";
    foreach ($all_courses as $course) {
        echo "<tr>";
        echo "<td>" . $course['id'] . "</td>";
        echo "<td>" . $course['name'] . "</td>";
        echo "<td>" . $course['teacherid'] . "</td>";
        echo "<td>" . $course['classid'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Aucun cours pour cette classe.</p>";
}

// 4. Vérifier les étudiants de cette classe qui sont inscrits à des cours
$students_courses = db_fetch_all(
    "SELECT DISTINCT s.id as student_id, s.name as student_name, c.id as course_id, c.name as course_name, stc.teacher_id, stc.class_id
     FROM students s
     JOIN student_teacher_course stc ON BINARY s.id = BINARY stc.student_id
     JOIN course c ON BINARY stc.course_id = BINARY c.id
     WHERE BINARY s.classid = BINARY ? OR BINARY stc.class_id = BINARY ?
     ORDER BY s.name, c.name",
    [$class_id, $class_id],
    'ss'
);

echo "<h3>4. Étudiants de cette classe et leurs cours</h3>";
if ($students_courses) {
    echo "<table border='1'>";
    echo "<tr><th>Étudiant ID</th><th>Étudiant Nom</th><th>Cours ID</th><th>Cours Nom</th><th>Enseignant ID</th><th>Classe ID (stc)</th></tr>";
    foreach ($students_courses as $sc) {
        echo "<tr>";
        echo "<td>" . $sc['student_id'] . "</td>";
        echo "<td>" . $sc['student_name'] . "</td>";
        echo "<td>" . $sc['course_id'] . "</td>";
        echo "<td>" . $sc['course_name'] . "</td>";
        echo "<td>" . $sc['teacher_id'] . "</td>";
        echo "<td>" . $sc['class_id'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Aucun étudiant inscrit à des cours pour cette classe.</p>";
}

// Fermer la connexion
$conn->close();
?>
