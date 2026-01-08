<?php
include_once('main.php');
include_once('../../service/db_utils.php');
include_once('../../service/course_filters.php');

// Vérification de la session
if (!isset($_SESSION['login_id'])) {
    header("Location: ../../index.php");
    exit();
}

$teacher_id = $_SESSION['login_id'];
$course_id = isset($_GET['course_id']) ? $_GET['course_id'] : '4'; // Par défaut, cours ID 4 (anglais)

echo "<h1>Débogage des droits d'accès</h1>";
echo "<p>Teacher ID: " . htmlspecialchars($teacher_id) . "</p>";
echo "<p>Course ID: " . htmlspecialchars($course_id) . "</p>";

// Vérifier les assignations directes
echo "<h2>Assignations directes</h2>";
$direct_assignments = db_fetch_all(
    "SELECT c.*, t.name as teacher_name, cl.name as class_name 
     FROM course c 
     JOIN teachers t ON CONVERT(c.teacherid USING utf8mb4) = CONVERT(t.id USING utf8mb4)
     JOIN class cl ON CONVERT(c.classid USING utf8mb4) = CONVERT(cl.id USING utf8mb4)
     WHERE CONVERT(c.id USING utf8mb4) = CONVERT(? USING utf8mb4)",
    [$course_id],
    's'
);

if ($direct_assignments) {
    echo "<table border='1'>";
    echo "<tr><th>Course ID</th><th>Course Name</th><th>Teacher ID</th><th>Teacher Name</th><th>Class ID</th><th>Class Name</th></tr>";
    foreach ($direct_assignments as $assignment) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($assignment['id']) . "</td>";
        echo "<td>" . htmlspecialchars($assignment['name']) . "</td>";
        echo "<td>" . htmlspecialchars($assignment['teacherid']) . "</td>";
        echo "<td>" . htmlspecialchars($assignment['teacher_name']) . "</td>";
        echo "<td>" . htmlspecialchars($assignment['classid']) . "</td>";
        echo "<td>" . htmlspecialchars($assignment['class_name']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Aucune assignation directe trouvée.</p>";
}

// Vérifier les assignations via student_teacher_course
echo "<h2>Assignations via student_teacher_course</h2>";
$stc_assignments = db_fetch_all(
    "SELECT stc.*, s.name as student_name, t.name as teacher_name, c.name as course_name, cl.name as class_name 
     FROM student_teacher_course stc 
     LEFT JOIN students s ON CONVERT(stc.student_id USING utf8mb4) = CONVERT(s.id USING utf8mb4)
     LEFT JOIN teachers t ON CONVERT(stc.teacher_id USING utf8mb4) = CONVERT(t.id USING utf8mb4)
     LEFT JOIN course c ON CONVERT(stc.course_id USING utf8mb4) = CONVERT(c.id USING utf8mb4)
     LEFT JOIN class cl ON CONVERT(stc.class_id USING utf8mb4) = CONVERT(cl.id USING utf8mb4)
     WHERE CONVERT(stc.course_id USING utf8mb4) = CONVERT(? USING utf8mb4)",
    [$course_id],
    's'
);

if ($stc_assignments) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Student ID</th><th>Student Name</th><th>Teacher ID</th><th>Teacher Name</th><th>Course ID</th><th>Course Name</th><th>Class ID</th><th>Class Name</th><th>Grade</th></tr>";
    foreach ($stc_assignments as $assignment) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($assignment['id']) . "</td>";
        echo "<td>" . htmlspecialchars($assignment['student_id']) . "</td>";
        echo "<td>" . htmlspecialchars($assignment['student_name']) . "</td>";
        echo "<td>" . htmlspecialchars($assignment['teacher_id']) . "</td>";
        echo "<td>" . htmlspecialchars($assignment['teacher_name']) . "</td>";
        echo "<td>" . htmlspecialchars($assignment['course_id']) . "</td>";
        echo "<td>" . htmlspecialchars($assignment['course_name']) . "</td>";
        echo "<td>" . htmlspecialchars($assignment['class_id']) . "</td>";
        echo "<td>" . htmlspecialchars($assignment['class_name']) . "</td>";
        echo "<td>" . htmlspecialchars($assignment['grade']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Aucune assignation via student_teacher_course trouvée.</p>";
}

// Tester la fonction can_teacher_access_course_details
echo "<h2>Test de la fonction can_teacher_access_course_details</h2>";
$access = can_teacher_access_course_details($teacher_id, $course_id);
echo "<p>Résultat: " . ($access ? "Accès autorisé" : "Accès refusé") . "</p>";

// Afficher tous les cours
echo "<h2>Tous les cours disponibles</h2>";
$all_courses = db_fetch_all(
    "SELECT c.*, t.name as teacher_name, cl.name as class_name 
     FROM course c 
     LEFT JOIN teachers t ON CONVERT(c.teacherid USING utf8mb4) = CONVERT(t.id USING utf8mb4)
     LEFT JOIN class cl ON CONVERT(c.classid USING utf8mb4) = CONVERT(cl.id USING utf8mb4)
     ORDER BY c.name",
    [],
    ''
);

if ($all_courses) {
    echo "<table border='1'>";
    echo "<tr><th>Course ID</th><th>Course Name</th><th>Teacher ID</th><th>Teacher Name</th><th>Class ID</th><th>Class Name</th><th>Accès</th></tr>";
    foreach ($all_courses as $course) {
        $course_access = can_teacher_access_course_details($teacher_id, $course['id']);
        echo "<tr>";
        echo "<td>" . htmlspecialchars($course['id']) . "</td>";
        echo "<td>" . htmlspecialchars($course['name']) . "</td>";
        echo "<td>" . htmlspecialchars($course['teacherid']) . "</td>";
        echo "<td>" . htmlspecialchars($course['teacher_name']) . "</td>";
        echo "<td>" . htmlspecialchars($course['classid']) . "</td>";
        echo "<td>" . htmlspecialchars($course['class_name']) . "</td>";
        echo "<td>" . ($course_access ? "Autorisé" : "Refusé") . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Aucun cours trouvé.</p>";
}
?>
