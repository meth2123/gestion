<?php
// Désactiver l'affichage des erreurs pour éviter les problèmes de redirection
error_reporting(0);

include_once('main.php');
include_once('includes/admin_utils.php');

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isset($_SESSION['login_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

$admin_id = $_SESSION['login_id'];

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: updateStudent.php?error=' . urlencode('Méthode non autorisée'));
    exit;
}

try {
    // Vérifier si les données du formulaire existent
    if (!isset($_POST['id']) || !isset($_POST['name']) || !isset($_POST['phone']) || 
        !isset($_POST['email']) || !isset($_POST['gender']) || !isset($_POST['dob']) || 
        !isset($_POST['addmissiondate']) || !isset($_POST['address']) || 
        !isset($_POST['parentid']) || !isset($_POST['classid'])) {
        throw new Exception("Données du formulaire incomplètes");
    }
    
    // Récupérer les données du formulaire
    $id = trim($_POST['id']);
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $gender = $_POST['gender'];
    $dob = $_POST['dob'];
    $addmissiondate = $_POST['addmissiondate'];
    $address = trim($_POST['address']);
    $parentid = trim($_POST['parentid']);
    $classid = trim($_POST['classid']);
    $created_by = isset($_POST['created_by']) ? $_POST['created_by'] : $admin_id;
    
    // Vérifier que l'ID existe et appartient à l'administrateur connecté
    $check_sql = "SELECT id FROM students WHERE id = ? AND created_by = ?";
    $check_stmt = $link->prepare($check_sql);
    $check_stmt->bind_param("ss", $id, $admin_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        throw new Exception("Étudiant non trouvé ou vous n'avez pas les droits pour le modifier");
    }
    
    // Validation des données
    if (empty($name)) throw new Exception("Le nom est requis");
    if (empty($phone)) throw new Exception("Le téléphone est requis");
    if (empty($email)) throw new Exception("L'email est requis");
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception("Format d'email invalide");
    if (empty($dob)) throw new Exception("La date de naissance est requise");
    if (empty($addmissiondate)) throw new Exception("La date d'admission est requise");
    if (empty($parentid)) throw new Exception("L'ID du parent est requis");
    if (empty($classid)) throw new Exception("L'ID de la classe est requis");
    
    // Vérifier si le parent existe
    $parent_check = "SELECT id FROM parents WHERE id = ?";
    $parent_stmt = $link->prepare($parent_check);
    $parent_stmt->bind_param("s", $parentid);
    $parent_stmt->execute();
    if ($parent_stmt->get_result()->num_rows === 0) {
        throw new Exception("Le parent avec l'ID $parentid n'existe pas");
    }
    
    // Vérifier si la classe existe
    $class_check = "SELECT id FROM class WHERE id = ?";
    $class_stmt = $link->prepare($class_check);
    $class_stmt->bind_param("s", $classid);
    $class_stmt->execute();
    if ($class_stmt->get_result()->num_rows === 0) {
        throw new Exception("La classe avec l'ID $classid n'existe pas");
    }
    
    // Commencer une transaction
    $link->begin_transaction();
    
    try {
        // Mise à jour des informations de l'étudiant
        $update_sql = "UPDATE students SET 
                        name = ?, 
                        phone = ?, 
                        email = ?, 
                        sex = ?, 
                        dob = ?, 
                        addmissiondate = ?, 
                        address = ?, 
                        parentid = ?, 
                        classid = ?";
        
        // Vérifier si un nouveau mot de passe a été fourni
        $password = trim($_POST['password'] ?? '');
        if (!empty($password)) {
            $update_sql .= ", password = ?";
        }
        
        $update_sql .= " WHERE id = ? AND created_by = ?";
        
        $update_stmt = $link->prepare($update_sql);
        
        if (!empty($password)) {
            $update_stmt->bind_param("sssssssssss", 
                $name, $phone, $email, $gender, $dob, $addmissiondate, 
                $address, $parentid, $classid, $password, $id, $admin_id);
        } else {
            $update_stmt->bind_param("ssssssssss", 
                $name, $phone, $email, $gender, $dob, $addmissiondate, 
                $address, $parentid, $classid, $id, $admin_id);
        }
        
        if (!$update_stmt->execute()) {
            throw new Exception("Erreur lors de la mise à jour : " . $update_stmt->error);
        }
        
        // Traitement de la photo si elle a été téléchargée
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $target_dir = "../images/";
            $file_extension = pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION);
            $target_file = $target_dir . $id . "." . $file_extension;
            
            // Vérifier le type de fichier
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array(strtolower($file_extension), $allowed_types)) {
                throw new Exception("Seuls les fichiers JPG, JPEG, PNG et GIF sont autorisés");
            }
            
            // Vérifier la taille du fichier (max 5MB)
            if ($_FILES["photo"]["size"] > 5000000) {
                throw new Exception("Le fichier est trop volumineux (max 5MB)");
            }
            
            // Déplacer le fichier téléchargé
            if (!move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
                throw new Exception("Erreur lors du téléchargement de la photo");
            }
        }
        
        // Mettre à jour la table users si un nouveau mot de passe a été fourni
        if (!empty($password)) {
            $user_update = "UPDATE users SET password = ? WHERE userid = ?";
            $user_stmt = $link->prepare($user_update);
            $user_stmt->bind_param("ss", $password, $id);
            $user_stmt->execute();
        }
        
        // Logger l'action de l'admin
        $details = json_encode([
            'student_id' => $id,
            'student_name' => $name,
            'admin_id' => $admin_id
        ]);
        
        $log_sql = "INSERT INTO admin_actions (admin_id, action_type, record_id, details, created_at) 
                    VALUES (?, 'UPDATE', ?, ?, NOW())";
        $log_stmt = $link->prepare($log_sql);
        $log_stmt->bind_param("sss", $admin_id, $id, $details);
        $log_stmt->execute();
        
        $link->commit();
        
        // Redirection avec message de succès
        header("Location: updateStudent.php?success=1");
        exit;
        
    } catch (Exception $e) {
        $link->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    // Redirection avec message d'erreur
    header("Location: updateStudent.php?error=" . urlencode($e->getMessage()));
    exit;
}
?>
