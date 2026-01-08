<?php
// Script pour hasher tous les mots de passe en clair dans la table students
// À exécuter une seule fois pour mettre à jour tous les mots de passe existants

// Inclure la connexion à la base de données
include_once('mysqlcon.php');

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Mise à jour des mots de passe des élèves</h1>";
echo "<p>Ce script va hasher tous les mots de passe en clair dans la table students.</p>";

// Récupérer tous les élèves
$sql = "SELECT id, password FROM students";
$result = $link->query($sql);

if (!$result) {
    die("Erreur lors de la récupération des élèves : " . $link->error);
}

$total_students = $result->num_rows;
$updated_count = 0;
$already_hashed = 0;
$errors = 0;

echo "<p>Nombre total d'élèves : $total_students</p>";
echo "<hr>";

while ($row = $result->fetch_assoc()) {
    $student_id = $row['id'];
    $current_password = $row['password'];
    
    // Vérifier si le mot de passe est déjà hashé
    if (password_get_info($current_password)['algo'] !== 0) {
        echo "<p>L'élève $student_id a déjà un mot de passe hashé.</p>";
        $already_hashed++;
        continue;
    }
    
    // Récupérer le mot de passe hashé depuis la table users
    $sql_user = "SELECT password FROM users WHERE userid = ?";
    $stmt_user = $link->prepare($sql_user);
    
    if (!$stmt_user) {
        echo "<p>Erreur lors de la préparation de la requête pour l'élève $student_id : " . $link->error . "</p>";
        $errors++;
        continue;
    }
    
    $stmt_user->bind_param("s", $student_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    
    if ($result_user->num_rows === 1) {
        // Utiliser le mot de passe hashé de la table users
        $user_row = $result_user->fetch_assoc();
        $hashed_password = $user_row['password'];
        
        // Mettre à jour le mot de passe dans la table students
        $update_sql = "UPDATE students SET password = ? WHERE id = ?";
        $update_stmt = $link->prepare($update_sql);
        
        if (!$update_stmt) {
            echo "<p>Erreur lors de la préparation de la mise à jour pour l'élève $student_id : " . $link->error . "</p>";
            $errors++;
            continue;
        }
        
        $update_stmt->bind_param("ss", $hashed_password, $student_id);
        
        if ($update_stmt->execute()) {
            echo "<p>Mot de passe mis à jour pour l'élève $student_id.</p>";
            $updated_count++;
        } else {
            echo "<p>Erreur lors de la mise à jour du mot de passe pour l'élève $student_id : " . $update_stmt->error . "</p>";
            $errors++;
        }
    } else {
        // Si l'élève n'existe pas dans la table users, hasher son mot de passe actuel
        $hashed_password = password_hash($current_password, PASSWORD_DEFAULT);
        
        // Mettre à jour le mot de passe dans la table students
        $update_sql = "UPDATE students SET password = ? WHERE id = ?";
        $update_stmt = $link->prepare($update_sql);
        
        if (!$update_stmt) {
            echo "<p>Erreur lors de la préparation de la mise à jour pour l'élève $student_id : " . $link->error . "</p>";
            $errors++;
            continue;
        }
        
        $update_stmt->bind_param("ss", $hashed_password, $student_id);
        
        if ($update_stmt->execute()) {
            echo "<p>Mot de passe hashé pour l'élève $student_id (non trouvé dans users).</p>";
            $updated_count++;
        } else {
            echo "<p>Erreur lors de la mise à jour du mot de passe pour l'élève $student_id : " . $update_stmt->error . "</p>";
            $errors++;
        }
    }
}

echo "<hr>";
echo "<h2>Résumé</h2>";
echo "<p>Nombre total d'élèves : $total_students</p>";
echo "<p>Mots de passe mis à jour : $updated_count</p>";
echo "<p>Mots de passe déjà hashés : $already_hashed</p>";
echo "<p>Erreurs : $errors</p>";

if ($errors === 0 && $updated_count + $already_hashed === $total_students) {
    echo "<p style='color: green;'>Tous les mots de passe ont été correctement mis à jour !</p>";
} else {
    echo "<p style='color: red;'>Certains mots de passe n'ont pas pu être mis à jour. Veuillez vérifier les erreurs ci-dessus.</p>";
}
?>
