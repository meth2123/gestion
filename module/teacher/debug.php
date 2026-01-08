<?php
include_once('main.php');
include_once('../../service/db_utils.php');

// Récupération des informations de l'enseignant
$teacher_id = $_SESSION['login_id'];
$teacher_info = db_fetch_row(
    "SELECT * FROM teachers WHERE id = ?",
    [$teacher_id],
    's'
);

echo "<h1>Informations de débogage pour l'enseignant: " . htmlspecialchars($teacher_info['name']) . " (ID: " . htmlspecialchars($teacher_id) . ")</h1>";

// Afficher les cours assignés directement à l'enseignant via la table course
echo "<h2>Cours assignés directement (table course)</h2>";
$direct_courses = db_fetch_all(
    "SELECT c.id, c.name, c.teacherid, cl.id as classid, cl.name as class_name
     FROM course c
     JOIN class cl ON c.classid = cl.id
     WHERE c.teacherid = ?",
    [$teacher_id],
    's'
);

if ($direct_courses) {
    echo "<table border='1'>";
    echo "<tr><th>ID Cours</th><th>Nom Cours</th><th>ID Classe</th><th>Nom Classe</th></tr>";
    foreach ($direct_courses as $course) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($course['id']) . "</td>";
        echo "<td>" . htmlspecialchars($course['name']) . "</td>";
        echo "<td>" . htmlspecialchars($course['classid']) . "</td>";
        echo "<td>" . htmlspecialchars($course['class_name']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Aucun cours assigné directement.</p>";
}

// Afficher les cours assignés via la table student_teacher_course
echo "<h2>Cours assignés via student_teacher_course</h2>";
$stc_courses = db_fetch_all(
    "SELECT stc.id, stc.course_id, c.name as course_name, stc.class_id, cl.name as class_name
     FROM student_teacher_course stc
     JOIN course c ON stc.course_id = c.id
     JOIN class cl ON stc.class_id = cl.id
     WHERE stc.teacher_id = ?
     GROUP BY stc.course_id, stc.class_id",
    [$teacher_id],
    's'
);

if ($stc_courses) {
    echo "<table border='1'>";
    echo "<tr><th>ID STC</th><th>ID Cours</th><th>Nom Cours</th><th>ID Classe</th><th>Nom Classe</th></tr>";
    foreach ($stc_courses as $course) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($course['id']) . "</td>";
        echo "<td>" . htmlspecialchars($course['course_id']) . "</td>";
        echo "<td>" . htmlspecialchars($course['course_name']) . "</td>";
        echo "<td>" . htmlspecialchars($course['class_id']) . "</td>";
        echo "<td>" . htmlspecialchars($course['class_name']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Aucun cours assigné via student_teacher_course.</p>";
}

// Afficher tous les cours avec le même nom pour voir les doublons potentiels
echo "<h2>Tous les cours 'anglais' dans le système</h2>";
$all_courses = db_fetch_all(
    "SELECT c.id, c.name, c.teacherid, t.name as teacher_name, cl.id as classid, cl.name as class_name
     FROM course c
     JOIN teachers t ON c.teacherid = t.id
     JOIN class cl ON c.classid = cl.id
     WHERE c.name = 'anglais'",
    [],
    ''
);

if ($all_courses) {
    echo "<table border='1'>";
    echo "<tr><th>ID Cours</th><th>Nom Cours</th><th>ID Enseignant</th><th>Nom Enseignant</th><th>ID Classe</th><th>Nom Classe</th></tr>";
    foreach ($all_courses as $course) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($course['id']) . "</td>";
        echo "<td>" . htmlspecialchars($course['name']) . "</td>";
        echo "<td>" . htmlspecialchars($course['teacherid']) . "</td>";
        echo "<td>" . htmlspecialchars($course['teacher_name']) . "</td>";
        echo "<td>" . htmlspecialchars($course['classid']) . "</td>";
        echo "<td>" . htmlspecialchars($course['class_name']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Aucun cours 'anglais' trouvé.</p>";
}
?>
