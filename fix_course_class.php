<?php
// Configuration de la base de données
require_once('db/config.php');

// Connexion à la base de données
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Afficher les informations actuelles du cours
$course_id = 15;
$result = $conn->query("SELECT id, name, classid FROM course WHERE id = $course_id");
$course = $result->fetch_assoc();

echo "<h2>Informations actuelles du cours ID: $course_id</h2>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Nom</th><th>Classe ID</th></tr>";
echo "<tr>";
echo "<td>" . $course['id'] . "</td>";
echo "<td>" . $course['name'] . "</td>";
echo "<td>" . $course['classid'] . "</td>";
echo "</tr>";
echo "</table>";

// Mettre à jour le cours pour l'associer à la classe CI
$target_class_id = 'CLS-CI-A-809';
$stmt = $conn->prepare("UPDATE course SET classid = ? WHERE id = ?");
$stmt->bind_param("si", $target_class_id, $course_id);

if ($stmt->execute()) {
    echo "<div style='color: green; margin-top: 20px;'>Le cours a été mis à jour avec succès!</div>";
    
    // Afficher les nouvelles informations
    $result = $conn->query("SELECT c.id, c.name, c.classid, cl.name as class_name 
                            FROM course c 
                            JOIN class cl ON c.classid = cl.id 
                            WHERE c.id = $course_id");
    $updated_course = $result->fetch_assoc();
    
    echo "<h2>Nouvelles informations du cours</h2>";
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Nom</th><th>Classe ID</th><th>Nom de la classe</th></tr>";
    echo "<tr>";
    echo "<td>" . $updated_course['id'] . "</td>";
    echo "<td>" . $updated_course['name'] . "</td>";
    echo "<td>" . $updated_course['classid'] . "</td>";
    echo "<td>" . $updated_course['class_name'] . "</td>";
    echo "</tr>";
    echo "</table>";
    
    echo "<p>Vous pouvez maintenant retourner à la page <a href='module/teacher/view_exam.php?course_id=15'>view_exam.php</a> pour voir les changements.</p>";
} else {
    echo "<div style='color: red; margin-top: 20px;'>Erreur lors de la mise à jour du cours: " . $stmt->error . "</div>";
}

// Fermer la connexion
$stmt->close();
$conn->close();
?>
