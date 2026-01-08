<?php
// Configuration de la base de données
require_once('db/config.php');

// Connexion à la base de données
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Vérifier toutes les classes
$result = $conn->query("SELECT id, name FROM class ORDER BY name");

echo "<h2>Liste de toutes les classes</h2>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Nom</th></tr>";
while ($class = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $class['id'] . "</td>";
    echo "<td>" . $class['name'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Vérifier les examens et leurs classes associées
echo "<h2>Liste des examens et leurs classes</h2>";
$result = $conn->query("
    SELECT e.id, e.courseid, c.name as course_name, e.examdate, e.time, c.classid, cl.name as class_name
    FROM examschedule e
    JOIN course c ON e.courseid = c.id
    JOIN class cl ON c.classid = cl.id
    ORDER BY cl.name
");

echo "<table border='1'>";
echo "<tr><th>Exam ID</th><th>Cours</th><th>Date</th><th>Classe ID</th><th>Classe Nom</th></tr>";
while ($exam = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $exam['id'] . "</td>";
    echo "<td>" . $exam['course_name'] . "</td>";
    echo "<td>" . $exam['examdate'] . "</td>";
    echo "<td>" . $exam['classid'] . "</td>";
    echo "<td>" . $exam['class_name'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Fermer la connexion
$conn->close();
?>
