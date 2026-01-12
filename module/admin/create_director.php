<?php
require_once dirname(dirname(dirname(__FILE__))) . '/config.php';
require_once MYSQLCON_PATH;
require_once DB_CONFIG_PATH;
require_once dirname(dirname(dirname(__FILE__))) . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Messages d'erreur
$error_messages = [
    'login_required' => 'Vous devez être connecté pour accéder à cette page.',
    'unauthorized' => 'Accès non autorisé. Vous n\'avez pas les permissions nécessaires.',
    'invalid_page' => 'Page non trouvée ou non autorisée.',
    'invalid_credentials' => 'Identifiants invalides. Veuillez réessayer.',
    'director_exists' => 'Le compte directeur existe déjà. Veuillez vous connecter avec vos identifiants existants.',
    'director_created' => 'Le compte directeur a été créé avec succès !',
    'firstname_required' => 'Le prénom est requis',
    'lastname_required' => 'Le nom est requis',
    'empty_email' => 'L\'email est requis',
    'email_exists' => 'Cet email est déjà utilisé',
    'password_required' => 'Le mot de passe est requis',
    'password_mismatch' => 'Les mots de passe ne correspondent pas'
];

// Vérifier si l'utilisateur est connecté et est admin
if (!isLoggedIn()) {
    header("Location: ../../login.php?error=unauthorized");
    exit();
}

// Vérifier le type d'utilisateur
// S'assurer que la session est démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Récupérer l'id de l'admin connecté
$created_by = $_SESSION['admin_id'] ?? null;
if (!$created_by || $created_by === '0') {
    error_log("Erreur: admin non connecté ou session non initialisée lors de la création du directeur.");
    die("Erreur: admin non connecté.");
}
$admin_id = $created_by; // chaque admin ne crée que pour lui-même

// Utiliser la connexion $link créée par mysqlcon.php
global $link;
$conn = $link;
if ($conn === null || !$conn) {
    die('Erreur de connexion à la base de données. Vérifiez les variables d\'environnement Railway.');
}

// Vérifier si le directeur existe déjà pour cet admin
$check_director = $conn->prepare("SELECT * FROM director WHERE created_by = ?");
$check_director->bind_param("s", $admin_id);
$check_director->execute();
$result = $check_director->get_result();

// if ($result->num_rows > 0) {
//     header("Location: ../index.php?error=director_exists&admin_id=" . $admin_id);
//     exit();
// }

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('DEBUG create_director.php : début du bloc POST');
    $firstname = $_POST['firstname'] ?? '';
    $lastname = $_POST['lastname'] ?? '';
    $email = $_POST['email'] ?? '';
        if (empty($firstname)) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=firstname_required");
        exit();
    } elseif (empty($lastname)) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=lastname_required");
        exit();
    } elseif (empty($email)) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=empty_email");
        exit();
    }

    // Vérifier si l'email existe déjà pour un directeur
    $stmt = $conn->prepare("SELECT * FROM director WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=email_exists");
        exit();
    }

    // Générer un mot de passe temporaire
    function generateRandomPassword($length = 10) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
        return substr(str_shuffle(str_repeat($chars, ceil($length/strlen($chars)))), 0, $length);
    }
    $plain_password = generateRandomPassword(10);
    $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);

    // Générer un identifiant unique pour le directeur (ex: DIR-xxxx)
    $prefix = 'DIR-';
    $unique = false;
    $userid = '';
    while (!$unique) {
        $rand = str_pad(strval(rand(0, 9999)), 4, '0', STR_PAD_LEFT);
        $userid = $prefix . $rand;
        $stmt = $conn->prepare("SELECT * FROM users WHERE userid = ?");
        $stmt->bind_param("s", $userid);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $unique = true;
        }
    }

    // Insérer dans la table users avec must_change_password=1
    $stmt = $conn->prepare("INSERT INTO users (userid, password, usertype, must_change_password) VALUES (?, ?, 'director', 1)");
    $stmt->bind_param("ss", $userid, $hashed_password);
    $stmt->execute();
    // user_id = $userid (clé primaire varchar)

    // Insérer dans la table director avec le champ created_by
    $stmt = $conn->prepare("INSERT INTO director (firstname, lastname, email, created_by) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $firstname, $lastname, $email, $created_by);
    error_log('DEBUG create_director.php : avant $stmt->execute()');
    if ($stmt->execute()) {
        // Envoi de l'email avec la fonction utilitaire
        require_once dirname(dirname(dirname(__FILE__))) . '/service/mailer_utils.php';
        // Config SMTP (tu peux la centraliser si besoin)
        $smtp_config = [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'username' => 'methndiaye43@gmail.com',
            'password' => 'elaf cmwo iahy gghs',
            'from_email' => 'methndiaye43@gmail.com',
            'from_name' => 'Système de Gestion Scolaire'
        ];
        $subject = 'Création de votre compte Directeur';
        $body =
            'Bonjour ' . htmlspecialchars($firstname) . ' ' . htmlspecialchars($lastname) . ',<br><br>' .
            'Votre compte Directeur a été créé.<br><br>' .
            '<strong>Identifiant :</strong> ' . htmlspecialchars($userid) . '<br>' .
            '<strong>Mot de passe :</strong> ' . htmlspecialchars($plain_password) . '<br><br>' .
            'Merci de vous connecter et de changer votre mot de passe dès votre première connexion.<br>' .
            '<a href="http://localhost/gestion/login.php">Se connecter</a>';
        $resultat_mail = envoyer_email_smtp($email, $firstname . ' ' . $lastname, $subject, $body, $smtp_config);
        if ($resultat_mail === true) {
            error_log('Mail directeur envoyé avec succès !');
            header("Location: ../director_created.php?email=" . urlencode($email));
            exit();
        } else {
            error_log('Erreur envoi email directeur : ' . $resultat_mail);
            // Tu peux afficher une erreur à l'admin ou rediriger avec un message
            header("Location: create_director_form.php?error=mail_error&msg=" . urlencode($resultat_mail));
            exit();
        }
    } else {
        // Erreur SQL : afficher une erreur explicite
        $sqlmsg = urlencode($stmt->error);
        error_log('DEBUG create_director.php : ERREUR SQL = ' . $stmt->error);
        header("Location: create_director_form.php?error=sql_error&sqlmsg=$sqlmsg");
        exit();
    }

    // Insérer l'action dans director_actions
    $action_type = 'account_created';
    $details = "Création du compte directeur par l'admin " . $admin_id;
    $stmt = $conn->prepare("INSERT INTO director_actions (director_id, action_type, created_by, details) VALUES (?, ?, ?, ?)");
    $director_id = $conn->insert_id;
    $stmt->bind_param("isss", $director_id, $action_type, $admin_id, $details);
    $stmt->bind_param("isss", $director_id, $action_type, $created_by, $details);
    $stmt->execute();

    // Envoi de l'email avec PHPMailer
    require_once dirname(dirname(dirname(__FILE__))) . '/service/forgot_password.php'; // pour la config SMTP
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtp_config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_config['username'];
        $mail->Password = $smtp_config['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtp_config['port'];
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($smtp_config['from_email'], $smtp_config['from_name']);
        $mail->addAddress($email, $firstname . ' ' . $lastname);
        $mail->isHTML(true);
        $mail->Subject = 'Votre compte Directeur sur Gestion Scolaire';
        $mail->Body = '
            Bonjour ' . htmlspecialchars($firstname) . ' ' . htmlspecialchars($lastname) . ',<br><br>
            Votre compte Directeur a été créé.<br><br>
            <strong>Identifiant :</strong> ' . htmlspecialchars($userid) . '<br>
            <strong>Mot de passe :</strong> ' . htmlspecialchars($plain_password) . '<br><br>
            Merci de vous connecter et de changer votre mot de passe dès votre première connexion.<br>
            <a href="http://localhost/gestion/login.php">Se connecter</a>
        ';
        $mail->AltBody = "Bonjour $firstname $lastname,\nVotre compte Directeur a été créé.\nIdentifiant : $userid\nMot de passe : $plain_password\nMerci de vous connecter et de changer votre mot de passe dès votre première connexion.";
        $mail->send();
        error_log('PHPMailer : Email envoyé avec succès !');
    } catch (Exception $e) {
        error_log('PHPMailer Exception : ' . $mail->ErrorInfo . ' | Exception : ' . $e->getMessage());
    }
    error_log('PHPMailer : Fin du bloc envoi mail');
    // Redirection avec succès
    header("Location: ../director_created.php?email=" . urlencode($email));
    exit();
} else {
    // Redirection vers le formulaire
    header("Location: create_director_form.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Compte Directeur Créé</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 20px auto; padding: 20px; }
        .success { color: green; font-weight: bold; margin-bottom: 20px; }
        .credentials { background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0; }
        .important { color: #e74c3c; font-weight: bold; }
        .note { color: #3498db; margin-top: 20px; }
    </style>
</head>
<body>
    <h1>Compte Directeur Créé</h1>
    <div class="success">Votre compte de directeur a été créé avec succès !</div>
    
    <div class="credentials">
        <h2>Informations de connexion</h2>
        <p><strong>Identifiant :</strong> directeur</p>
        <p><strong>Mot de passe initial :</strong> director123</p>
        <p class="important">Veuillez changer ce mot de passe lors de votre première connexion !</p>
        <p>Vous ne pourrez pas accéder à ces pages depuis l'interface admin.</p>
    </div>
    
    <div class="note">
        <p>Important : Une fois connecté, vous pourrez accéder à la gestion des paiements et des salaires via votre tableau de bord.</p>
    </div>
    
    <p><a href="../login.php" class="btn">Se connecter</a></p>
</body>
</html>
<?php
exit();
?>
