<?php
session_start();
include_once('mysqlcon.php');
require_once __DIR__ . '/auto_fix_expired.php';
require_once __DIR__ . '/url_helper.php';

// Corriger automatiquement les abonnements expirés avant de vérifier l'accès
autoFixExpiredSubscriptions($link);

// Vérifier si les variables POST existent
if (!isset($_POST['myid']) || !isset($_POST['mypassword'])) {
    // Rediriger vers la page de connexion si accès direct au script
    header("Location:../login.php?error=direct_access");
    exit;
}

$myid = $_POST['myid'];
$mypassword = $_POST['mypassword'];
$myid = stripslashes($myid);
$mypassword = stripslashes($mypassword);

// Récupérer le mot de passe stocké et le type d'utilisateur
$sql = "SELECT usertype, password, userid FROM users WHERE userid=?";
$stmt = $link->prepare($sql);
$stmt->bind_param("s", $myid);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $stored_password = $row['password'];
    $control = $row['usertype'];
    $user_id = $row['userid'];

    error_log('[LOGIN][DEBUG] usertype: ' . $control . ', userid: ' . $user_id . ', input_password: ' . $mypassword . ', stored_password: ' . $stored_password);
    
    // Vérifier l'abonnement pour tous les types d'utilisateurs
    $school_id = null;
    $admin_email = null;
    
    // Récupérer l'ID de l'école ou l'email de l'administrateur selon le type d'utilisateur
    switch ($control) {
        case 'admin':
            // Pour les administrateurs, vérifier directement avec leur email
            $sql = "SELECT email FROM admin WHERE id = ?";
            $stmt = $link->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("s", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    $admin_data = $result->fetch_assoc();
                    $admin_email = $admin_data['email'];
                }
            }
            break;
        case 'director':
            // Pour les directeurs, récupérer l'admin créateur puis son email
            $sql = "SELECT created_by FROM director WHERE userid = ?";
            $stmt = $link->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("s", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    $director_data = $result->fetch_assoc();
                    $admin_id = $director_data['created_by'];
                    // Récupérer l'email de l'admin
                    $sql = "SELECT email FROM admin WHERE id = ?";
                    $stmt2 = $link->prepare($sql);
                    if ($stmt2) {
                        $stmt2->bind_param("s", $admin_id);
                        $stmt2->execute();
                        $result2 = $stmt2->get_result();
                        if ($result2 && $result2->num_rows > 0) {
                            $admin_data = $result2->fetch_assoc();
                            $admin_email = $admin_data['email'];
                        }
                    }
                }
            }
            break;
            
        case 'teacher':
            // Pour les enseignants, récupérer l'ID de l'école
            $sql = "SELECT created_by FROM teachers WHERE id = ?";
            $stmt = $link->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("s", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    $teacher_data = $result->fetch_assoc();
                    $admin_id = $teacher_data['created_by'];
                    
                    // Récupérer l'email de l'administrateur
                    $sql = "SELECT email FROM admin WHERE id = ?";
                    $stmt = $link->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("s", $admin_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($result && $result->num_rows > 0) {
                            $admin_data = $result->fetch_assoc();
                            $admin_email = $admin_data['email'];
                        }
                    }
                }
            }
            break;
            
        case 'student':
        case 'parent':
        case 'staff':
            // Pour les élèves, parents et staff, récupérer l'ID de l'école
            $table = $control . 's'; // students, parents, staff
            if ($control === 'staff') $table = 'staff'; // Exception pour staff qui est déjà au pluriel
            
            $sql = "SELECT created_by FROM $table WHERE id = ?";
            $stmt = $link->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("s", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    $user_data = $result->fetch_assoc();
                    $admin_id = $user_data['created_by'];
                    
                    // Récupérer l'email de l'administrateur
                    $sql = "SELECT email FROM admin WHERE id = ?";
                    $stmt = $link->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("s", $admin_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($result && $result->num_rows > 0) {
                            $admin_data = $result->fetch_assoc();
                            $admin_email = $admin_data['email'];
                        }
                    }
                }
            }
            break;
    }
    
    // Vérifier l'abonnement si nous avons récupéré l'email de l'administrateur
    if ($admin_email) {
        $sql_subscription = "SELECT payment_status FROM subscriptions 
                            WHERE admin_email COLLATE utf8mb4_unicode_ci = ? ";
        $stmt_subscription = $link->prepare($sql_subscription);
        
        if ($stmt_subscription) {
            $stmt_subscription->bind_param("s", $admin_email);
            $stmt_subscription->execute();
            $result_subscription = $stmt_subscription->get_result();
            
            if ($result_subscription && $result_subscription->num_rows > 0) {
                $subscription = $result_subscription->fetch_assoc();
                
                // Empêcher la connexion si l'abonnement est expiré, en attente ou échoué
                if (in_array($subscription['payment_status'], ['expired', 'pending', 'failed'])) {
                    header("Location:../login.php?error=account_inactive&status=" . $subscription['payment_status']);
                    exit;
                }
            }
        }
    }
    
    // Vérifier si c'est un mot de passe hashé avec password_hash
    if (password_verify($mypassword, $stored_password)) {
        $password_correct = true;
    } 
    // Vérifier si c'est un mot de passe hashé avec md5 (format utilisé dans paydunya_service.php)
    else if (md5($mypassword) === $stored_password) {
        $password_correct = true;
        
        // Mettre à jour le mot de passe vers le format sécurisé password_hash
        $hashed_password = password_hash($mypassword, PASSWORD_DEFAULT);
        $update_sql = "UPDATE users SET password = ? WHERE userid = ?";
        $update_stmt = $link->prepare($update_sql);
        if ($update_stmt) {
            $update_stmt->bind_param("ss", $hashed_password, $user_id);
            $update_stmt->execute();
        }
    } 
    // Vérification de l'ancien format (non hashé) - à conserver pour compatibilité
    else {
        $password_correct = ($mypassword === $stored_password);
        
        // Si le mot de passe est correct, mettre à jour vers le format sécurisé
        if ($password_correct) {
            $hashed_password = password_hash($mypassword, PASSWORD_DEFAULT);
            $update_sql = "UPDATE users SET password = ? WHERE userid = ?";
            $update_stmt = $link->prepare($update_sql);
            if ($update_stmt) {
                $update_stmt->bind_param("ss", $hashed_password, $user_id);
                $update_stmt->execute();
            }
        }
    }
    
    if ($password_correct) {
        // Régénérer l'ID de session pour prévenir la fixation de session
        session_regenerate_id(true);
        
        // Définir les variables de session importantes (harmonisé)
        $_SESSION['userid'] = $user_id;
        $_SESSION['usertype'] = $control;
        $_SESSION['name'] = $user_id; // Pour compatibilité avec les scripts existants
        $_SESSION['last_activity'] = time(); // Pour suivre l'activité de l'utilisateur
        // Harmonisation pour chaque module
        switch ($control) {
            case 'admin':
                $_SESSION['login_id'] = $user_id;
                $_SESSION['user_type'] = 'admin';
                $_SESSION['admin'] = true;
                $_SESSION['admin_id'] = $user_id;
                break;
            case 'teacher':
                // Récupérer le vrai ID de teachers.id (par id ou par email)
                $teacher_id = null;
                $sql = "SELECT id FROM teachers WHERE id = ?";
                $stmt = $link->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("s", $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result && $result->num_rows > 0) {
                        $teacher_row = $result->fetch_assoc();
                        $teacher_id = $teacher_row['id'];
                    }
                }
                if (!$teacher_id) {
                    $sql = "SELECT id FROM teachers WHERE email = ?";
                    $stmt = $link->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("s", $user_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($result && $result->num_rows > 0) {
                            $teacher_row = $result->fetch_assoc();
                            $teacher_id = $teacher_row['id'];
                        }
                    }
                }
                if ($teacher_id) {
                    $_SESSION['teacher_id'] = $teacher_id;
                } else {
                    $_SESSION['teacher_id'] = $user_id;
                }
                $_SESSION['user_type'] = 'teacher';
                break;
            case 'student':
                $_SESSION['student_id'] = $user_id;
                $_SESSION['user_type'] = 'student';
                break;
            case 'staff':
                $_SESSION['staff_id'] = $user_id;
                $_SESSION['user_type'] = 'staff';
                break;
            case 'parent':
                $_SESSION['parent_id'] = $user_id;
                $_SESSION['user_type'] = 'parent';
                break;
            case 'director':
                $_SESSION['director_id'] = $user_id;
                $_SESSION['user_type'] = 'director';
                break;
        }
        error_log('[LOGIN][DEBUG][SESSION] ' . print_r($_SESSION, true));
        // Redirection selon le type d'utilisateur
        switch ($control) {
            case "admin":
                header("Location:../module/admin");
                break;
            case "teacher":
                header("Location:../module/teacher");
                break;
            case "student":
                header("Location:../module/student");
                break;
            case "staff":
                header("Location:../module/staff");
                break;
            case "parent":
                header("Location:../module/parent");
                break;
            case "director":
                header("Location:../module/director");
                break;
            default:
                header("Location:../index.php?login=false");
        }
        exit;
    }
} else {
    // Si l'utilisateur n'est pas trouvé dans la table users, vérifier directement dans la table students
$sql = "SELECT id, password FROM students WHERE id = ?";
$stmt = $link->prepare($sql);
if ($stmt) {
    $stmt->bind_param("s", $myid);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $student_data = $result->fetch_assoc();
        $stored_password = $student_data['password'];
        // Vérifier le mot de passe - d'abord essayer en clair (pour compatibilité) ou hashé
        if ($mypassword === $stored_password || password_verify($mypassword, $stored_password)) {
            $_SESSION['userid'] = $myid;
            $_SESSION['usertype'] = 'student';
            $_SESSION['name'] = $myid;
            $_SESSION['last_activity'] = time();
            header("Location:../module/student");
            exit;
        }
    }
}
// Si rien n'a marché, retour à la page de login
header("Location:../index.php?login=false");
exit;
}

// Si on arrive ici, la connexion a échoué
header("Location:../login.php?login=false");
exit;
