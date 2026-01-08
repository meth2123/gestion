<?php
// Configuration de la base de données
require_once('db/config.php');

// Connexion à la base de données
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Vérifier le cours spécifique
$course_id = 15;
$stmt = $conn->prepare("SELECT c.id, c.name, c.teacherid, c.classid, cl.name as class_name 
                         FROM course c 
                         JOIN class cl ON c.classid = cl.id 
                         WHERE c.id = ?");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();

echo "<h2>Informations sur le cours ID: $course_id</h2>";
if ($result->num_rows > 0) {
    $course = $result->fetch_assoc();
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Nom</th><th>Enseignant ID</th><th>Classe ID</th><th>Nom de la classe</th></tr>";
    echo "<tr>";
    echo "<td>" . $course['id'] . "</td>";
    echo "<td>" . $course['name'] . "</td>";
    echo "<td>" . $course['teacherid'] . "</td>";
    echo "<td>" . $course['classid'] . "</td>";
    echo "<td>" . $course['class_name'] . "</td>";
    echo "</tr>";
    echo "</table>";
} else {
    echo "Cours non trouvé.<br>";
}

// Vérifier les examens associés à ce cours
echo "<h2>Examens associés au cours ID: $course_id</h2>";
$stmt = $conn->prepare("
    SELECT e.id, e.courseid, e.title, e.examdate, e.time, c.classid, cl.name as class_name
    FROM examschedule e
    JOIN course c ON e.courseid = c.id
    JOIN class cl ON c.classid = cl.id
    WHERE e.courseid = ?
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Titre</th><th>Date</th><th>Heure</th><th>Classe ID</th><th>Classe Nom</th></tr>";
    while ($exam = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $exam['id'] . "</td>";
        echo "<td>" . $exam['title'] . "</td>";
        echo "<td>" . $exam['examdate'] . "</td>";
        echo "<td>" . $exam['time'] . "</td>";
        echo "<td>" . $exam['classid'] . "</td>";
        echo "<td>" . $exam['class_name'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Aucun examen associé à ce cours.<br>";
}

// Vérifier les étudiants associés à ce cours
echo "<h2>Étudiants associés au cours ID: $course_id</h2>";
$stmt = $conn->prepare("
    SELECT s.id, s.name, s.email, s.phone, s.classid, cl.name as student_class_name, stc.class_id as stc_class_id, cl2.name as stc_class_name
    FROM students s
    JOIN student_teacher_course stc ON s.id = stc.student_id
    JOIN class cl ON s.classid = cl.id
    LEFT JOIN class cl2 ON stc.class_id = cl2.id
    WHERE stc.course_id = ?
    ORDER BY s.name
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Nom</th><th>Email</th><th>Téléphone</th><th>Classe ID (student)</th><th>Nom Classe (student)</th><th>Classe ID (stc)</th><th>Nom Classe (stc)</th></tr>";
    while ($student = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $student['id'] . "</td>";
        echo "<td>" . $student['name'] . "</td>";
        echo "<td>" . $student['email'] . "</td>";
        echo "<td>" . $student['phone'] . "</td>";
        echo "<td>" . $student['classid'] . "</td>";
        echo "<td>" . $student['student_class_name'] . "</td>";
        echo "<td>" . $student['stc_class_id'] . "</td>";
        echo "<td>" . $student['stc_class_name'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Aucun étudiant associé à ce cours.<br>";
}

// Fermer la connexion
$conn->close();
?>
