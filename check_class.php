<?php
// Configuration de la base de données
require_once('db/config.php');

// Connexion à la base de données
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Vérifier la classe spécifique
$class_id = 'CLS-CI-A-809';
$stmt = $conn->prepare("SELECT id, name FROM class WHERE id = ?");
$stmt->bind_param("s", $class_id);
$stmt->execute();
$result = $stmt->get_result();

echo "<h2>Informations sur la classe $class_id</h2>";
if ($result->num_rows > 0) {
    $class = $result->fetch_assoc();
    echo "ID: " . $class['id'] . "<br>";
    echo "Nom: " . $class['name'] . "<br>";
} else {
    echo "Classe non trouvée.<br>";
}

// Vérifier les cours associés à cette classe
echo "<h2>Cours associés à la classe $class_id</h2>";
$stmt = $conn->prepare("SELECT id, name, teacherid FROM course WHERE classid = ?");
$stmt->bind_param("s", $class_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Nom</th><th>Enseignant</th></tr>";
    while ($course = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $course['id'] . "</td>";
        echo "<td>" . $course['name'] . "</td>";
        echo "<td>" . $course['teacherid'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Aucun cours associé à cette classe.<br>";
}

// Vérifier les examens associés à cette classe
echo "<h2>Examens associés à la classe $class_id</h2>";
$stmt = $conn->prepare("
    SELECT e.id, e.courseid, c.name as course_name, e.examdate, e.time, cl.id as class_id, cl.name as class_name
    FROM examschedule e
    JOIN course c ON e.courseid = c.id
    JOIN class cl ON c.classid = cl.id
    WHERE cl.id = ?
");
$stmt->bind_param("s", $class_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Cours</th><th>Date</th><th>Heure</th><th>Classe ID</th><th>Classe Nom</th></tr>";
    while ($exam = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $exam['id'] . "</td>";
        echo "<td>" . $exam['course_name'] . "</td>";
        echo "<td>" . $exam['examdate'] . "</td>";
        echo "<td>" . $exam['time'] . "</td>";
        echo "<td>" . $exam['class_id'] . "</td>";
        echo "<td>" . $exam['class_name'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Aucun examen associé à cette classe.<br>";
}

// Fermer la connexion
$conn->close();
?>
