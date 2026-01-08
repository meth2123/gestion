<?php
include_once('service/db_utils.php');

// Récupérer tous les étudiants avec leur genre
$students = db_fetch_all(
    "SELECT id, name, sex FROM students",
    [],
    ''
);

// Afficher les résultats
echo "<h2>Liste des étudiants et leur genre</h2>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Nom</th><th>Valeur du champ 'sex'</th></tr>";

foreach ($students as $student) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($student['id']) . "</td>";
    echo "<td>" . htmlspecialchars($student['name']) . "</td>";
    echo "<td>" . htmlspecialchars($student['sex']) . "</td>";
    echo "</tr>";
}

echo "</table>";
?>
