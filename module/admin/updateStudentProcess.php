<?php
// Désactiver l'affichage des erreurs pour éviter les problèmes de redirection
ini_set('display_errors', 0);
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

// Fonction pour journaliser les erreurs
function logError($message) {
    error_log("Erreur dans updateStudentProcess.php: " . $message);
}

try {
    // Récupérer l'ID de l'étudiant (seul champ obligatoire)
    $id = isset($_POST['id']) ? trim($_POST['id']) : '';
    
    // Validation de l'ID
    if (empty($id)) {
        throw new Exception("L'ID de l'étudiant est requis");
    }
    
    // Vérifier que l'ID existe et appartient à l'administrateur connecté
    $check_sql = "SELECT * FROM students WHERE id = ? AND created_by = ?";
    $check_stmt = $link->prepare($check_sql);
    
    if (!$check_stmt) {
        logError("Erreur de préparation de la requête de vérification: " . $link->error);
        throw new Exception("Erreur de base de données lors de la vérification de l'étudiant");
    }
    
    $check_stmt->bind_param("ss", $id, $admin_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        throw new Exception("Étudiant non trouvé ou vous n'avez pas les droits pour le modifier");
    }
    
    // Récupérer les données actuelles de l'étudiant
    $student_data = $check_result->fetch_assoc();
    
    // Récupérer les données du formulaire avec vérification
    // Si un champ n'est pas fourni, utiliser la valeur existante
    $name = isset($_POST['name']) && !empty(trim($_POST['name'])) ? trim($_POST['name']) : $student_data['name'];
    $phone = isset($_POST['phone']) && !empty(trim($_POST['phone'])) ? trim($_POST['phone']) : $student_data['phone'];
    $email = isset($_POST['email']) && !empty(trim($_POST['email'])) ? trim($_POST['email']) : $student_data['email'];
    $gender = isset($_POST['gender']) && !empty($_POST['gender']) ? $_POST['gender'] : $student_data['sex'];
    $dob = isset($_POST['dob']) && !empty($_POST['dob']) ? $_POST['dob'] : $student_data['dob'];
    $addmissiondate = isset($_POST['addmissiondate']) && !empty($_POST['addmissiondate']) ? $_POST['addmissiondate'] : $student_data['addmissiondate'];
    $address = isset($_POST['address']) && !empty(trim($_POST['address'])) ? trim($_POST['address']) : $student_data['address'];
    $parentid = isset($_POST['parentid']) && !empty(trim($_POST['parentid'])) ? trim($_POST['parentid']) : $student_data['parentid'];
    $classid = isset($_POST['classid']) && !empty(trim($_POST['classid'])) ? trim($_POST['classid']) : $student_data['classid'];
    
    // Validation des données modifiées
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Format d'email invalide");
    }
    
    // Vérifier que l'ID existe et appartient à l'administrateur connecté
    $check_sql = "SELECT id FROM students WHERE id = ? AND created_by = ?";
    $check_stmt = $link->prepare($check_sql);
    
    if (!$check_stmt) {
        logError("Erreur de préparation de la requête de vérification: " . $link->error);
        throw new Exception("Erreur de base de données lors de la vérification de l'étudiant");
    }
    
    $check_stmt->bind_param("ss", $id, $admin_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        throw new Exception("Étudiant non trouvé ou vous n'avez pas les droits pour le modifier");
    }
    
    // Vérifier si le parent a été modifié et s'il existe
    if ($parentid != $student_data['parentid']) {
        $parent_check = "SELECT id FROM parents WHERE id = ?";
        $parent_stmt = $link->prepare($parent_check);
        
        if (!$parent_stmt) {
            logError("Erreur de préparation de la requête de vérification du parent: " . $link->error);
            throw new Exception("Erreur de base de données lors de la vérification du parent");
        }
        
        $parent_stmt->bind_param("s", $parentid);
        $parent_stmt->execute();
        if ($parent_stmt->get_result()->num_rows === 0) {
            throw new Exception("Le parent avec l'ID $parentid n'existe pas");
        }
    }
    
    // Vérifier si la classe a été modifiée et si elle existe
    if ($classid != $student_data['classid']) {
        $class_check = "SELECT id FROM class WHERE id = ?";
        $class_stmt = $link->prepare($class_check);
        
        if (!$class_stmt) {
            logError("Erreur de préparation de la requête de vérification de la classe: " . $link->error);
            throw new Exception("Erreur de base de données lors de la vérification de la classe");
        }
        
        $class_stmt->bind_param("s", $classid);
        $class_stmt->execute();
        if ($class_stmt->get_result()->num_rows === 0) {
            throw new Exception("La classe avec l'ID $classid n'existe pas");
        }
    }
    
    // Commencer une transaction
    $link->begin_transaction();
    
    try {
        // Vérifier si un nouveau mot de passe a été fourni
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $password_update = !empty($password);
        
        // Construire la requête de mise à jour
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
        
        if ($password_update) {
            $update_sql .= ", password = ?";
        }
        
        $update_sql .= " WHERE id = ? AND created_by = ?";
        
        $update_stmt = $link->prepare($update_sql);
        
        if (!$update_stmt) {
            logError("Erreur de préparation de la requête de mise à jour: " . $link->error);
            throw new Exception("Erreur de base de données lors de la mise à jour de l'étudiant");
        }
        
        if ($password_update) {
            $update_stmt->bind_param("ssssssssssss", 
                $name, $phone, $email, $gender, $dob, $addmissiondate, 
                $address, $parentid, $classid, $password, $id, $admin_id);
        } else {
            $update_stmt->bind_param("sssssssssss", 
                $name, $phone, $email, $gender, $dob, $addmissiondate, 
                $address, $parentid, $classid, $id, $admin_id);
        }
        
        if (!$update_stmt->execute()) {
            logError("Erreur d'exécution de la requête de mise à jour: " . $update_stmt->error);
            throw new Exception("Erreur lors de la mise à jour: " . $update_stmt->error);
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
                logError("Erreur lors du téléchargement de la photo");
                throw new Exception("Erreur lors du téléchargement de la photo");
            }
        }
        
        // Mettre à jour la table users si un nouveau mot de passe a été fourni
        if ($password_update) {
            $user_update = "UPDATE users SET password = ? WHERE userid = ?";
            $user_stmt = $link->prepare($user_update);
            
            if (!$user_stmt) {
                logError("Erreur de préparation de la requête de mise à jour du mot de passe: " . $link->error);
                throw new Exception("Erreur de base de données lors de la mise à jour du mot de passe");
            }
            
            $user_stmt->bind_param("ss", $password, $id);
            if (!$user_stmt->execute()) {
                logError("Erreur d'exécution de la requête de mise à jour du mot de passe: " . $user_stmt->error);
                throw new Exception("Erreur lors de la mise à jour du mot de passe: " . $user_stmt->error);
            }
        }
        
        // Logger l'action de l'admin dans la table admin_actions
        // Structure de la table: id, admin_id, action_type, affected_table, affected_id, action_details, action_date
        $action_details = json_encode([
            'student_id' => $id,
            'student_name' => $name,
            'changes' => 'Mise à jour des informations de l\'étudiant'
        ]);
        
        $log_sql = "INSERT INTO admin_actions (admin_id, action_type, affected_table, affected_id, action_details, action_date) 
                    VALUES (?, 'UPDATE', 'students', ?, ?, NOW())";
        $log_stmt = $link->prepare($log_sql);
        
        if (!$log_stmt) {
            logError("Erreur de préparation de la requête de journalisation: " . $link->error);
            // On continue même si la journalisation échoue
        } else {
            $log_stmt->bind_param("sss", $admin_id, $id, $action_details);
            $log_stmt->execute();
        }
        
        $link->commit();
        
        // Redirection avec message de succès
        header("Location: updateStudent.php?success=1");
        exit;
        
    } catch (Exception $e) {
        $link->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    logError($e->getMessage());
    // Redirection avec message d'erreur
    header("Location: updateStudent.php?error=" . urlencode($e->getMessage()));
    exit;
}
?>
