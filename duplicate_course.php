<?php
// Configuration de la base de données
require_once('db/config.php');

// Connexion à la base de données
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Informations du cours à dupliquer
$original_course_id = 15; // ID du cours français
$target_class_id = 'CLS-MAT-A-834'; // ID de la classe maternelle

// Récupérer les informations du cours original
$stmt = $conn->prepare("SELECT * FROM course WHERE id = ?");
$stmt->bind_param("i", $original_course_id);
$stmt->execute();
$result = $stmt->get_result();
$original_course = $result->fetch_assoc();

if (!$original_course) {
    die("Le cours original n'a pas été trouvé.");
}

echo "<h2>Informations du cours original</h2>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Nom</th><th>Enseignant ID</th><th>Classe ID</th></tr>";
echo "<tr>";
echo "<td>" . $original_course['id'] . "</td>";
echo "<td>" . $original_course['name'] . "</td>";
echo "<td>" . $original_course['teacherid'] . "</td>";
echo "<td>" . $original_course['classid'] . "</td>";
echo "</tr>";
echo "</table>";

// Générer un nouvel ID pour le cours dupliqué
$new_course_id = $conn->query("SELECT MAX(id) as max_id FROM course")->fetch_assoc()['max_id'] + 1;

// Dupliquer le cours pour la classe maternelle
$stmt = $conn->prepare("INSERT INTO course (id, name, teacherid, classid, coefficient) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("isssd", 
    $new_course_id, 
    $original_course['name'], 
    $original_course['teacherid'], 
    $target_class_id, 
    $original_course['coefficient']
);

if ($stmt->execute()) {
    echo "<div style='color: green; margin-top: 20px;'>Le cours a été dupliqué avec succès!</div>";
    
    // Afficher les informations du nouveau cours
    $result = $conn->query("SELECT c.id, c.name, c.teacherid, c.classid, cl.name as class_name 
                            FROM course c 
                            JOIN class cl ON c.classid = cl.id 
                            WHERE c.id = $new_course_id");
    $new_course = $result->fetch_assoc();
    
    echo "<h2>Informations du nouveau cours</h2>";
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Nom</th><th>Enseignant ID</th><th>Classe ID</th><th>Nom de la classe</th></tr>";
    echo "<tr>";
    echo "<td>" . $new_course['id'] . "</td>";
    echo "<td>" . $new_course['name'] . "</td>";
    echo "<td>" . $new_course['teacherid'] . "</td>";
    echo "<td>" . $new_course['classid'] . "</td>";
    echo "<td>" . $new_course['class_name'] . "</td>";
    echo "</tr>";
    echo "</table>";
    
    // Créer un examen pour le nouveau cours (copie de l'examen original s'il existe)
    $stmt = $conn->prepare("SELECT * FROM examschedule WHERE courseid = ?");
    $stmt->bind_param("i", $original_course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $exams = [];
    while ($exam = $result->fetch_assoc()) {
        $exams[] = $exam;
    }
    
    if (count($exams) > 0) {
        echo "<h2>Duplication des examens</h2>";
        echo "<table border='1'>";
        echo "<tr><th>ID Original</th><th>Nouvel ID</th><th>Titre</th><th>Date</th></tr>";
        
        foreach ($exams as $exam) {
            // Générer un nouvel ID pour l'examen
            $new_exam_id = 'EX-' . uniqid();
            
            // Dupliquer l'examen pour le nouveau cours
            $stmt = $conn->prepare("INSERT INTO examschedule (id, courseid, title, description, examdate, time, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sisssss", 
                $new_exam_id, 
                $new_course_id, 
                $exam['title'], 
                $exam['description'], 
                $exam['examdate'], 
                $exam['time'], 
                $exam['created_by']
            );
            
            if ($stmt->execute()) {
                echo "<tr>";
                echo "<td>" . $exam['id'] . "</td>";
                echo "<td>" . $new_exam_id . "</td>";
                echo "<td>" . $exam['title'] . "</td>";
                echo "<td>" . $exam['examdate'] . "</td>";
                echo "</tr>";
            }
        }
        
        echo "</table>";
    }
    
    echo "<p>Vous pouvez maintenant accéder aux pages suivantes :</p>";
    echo "<ul>";
    echo "<li><a href='module/teacher/exam.php?class_id=CLS-CI-A-809'>Examens pour la classe CI</a></li>";
    echo "<li><a href='module/teacher/exam.php?class_id=CLS-MAT-A-834'>Examens pour la classe Maternelle</a></li>";
    echo "</ul>";
} else {
    echo "<div style='color: red; margin-top: 20px;'>Erreur lors de la duplication du cours: " . $stmt->error . "</div>";
}

// Fermer la connexion
$stmt->close();
$conn->close();
?>
