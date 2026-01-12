<?php
// Inclure les fichiers nécessaires
include_once('../service/mysqlcon.php');

// Vérifier si PHPMailer est installé
$phpmailer_path = __DIR__ . '/../vendor/autoload.php';
$phpmailer_installed = file_exists($phpmailer_path);

// Définir l'URL actuelle pour le formulaire
$current_url = $_SERVER['PHP_SELF'];

// Initialiser les variables
$success_message = '';
$error_message = '';
$name = $email = $subject = $message = $user_type = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les données du formulaire
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';
    $user_type = $_POST['user_type'] ?? '';
    
    // Validation des données
    if (empty($name) || empty($email) || empty($subject) || empty($message) || empty($user_type)) {
        $error_message = "Tous les champs sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "L'adresse email n'est pas valide.";
    } else {
        // Enregistrer le message dans la base de données
        require_once('../db/config.php');
        $conn = getDbConnection(); // Obtenir la connexion à la base de données
        
        // Vérifier si la connexion a réussi
        if (!$conn) {
            $error_message = "Impossible de se connecter à la base de données. Le message sera envoyé par email uniquement.";
            // Continuer quand même pour essayer d'envoyer l'email
        }
        
        // Créer la table help_messages si elle n'existe pas (seulement si la connexion est OK)
        if ($conn) {
            $sql_create_table = "CREATE TABLE IF NOT EXISTS help_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                user_type VARCHAR(20) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status ENUM('new', 'in_progress', 'resolved') DEFAULT 'new'
            )";
            
            if ($conn->query($sql_create_table) === TRUE) {
            // Insérer le message
            $sql = "INSERT INTO help_messages (name, email, user_type, subject, message) 
                    VALUES (?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssss", $name, $email, $user_type, $subject, $message);
            
            if ($stmt->execute()) {
                // Envoyer un email de notification
                $to = "methndiaye43@gmail.com"; // Adresse email du support
                $email_subject = "Nouveau message du centre d'aide: " . $subject;
                $email_message = "Nom: " . $name . "\n";
                $email_message .= "Email: " . $email . "\n";
                $email_message .= "Type d'utilisateur: " . $user_type . "\n";
                $email_message .= "Sujet: " . $subject . "\n\n";
                $email_message .= "Message:\n" . $message;
                $headers = "From: " . $email;
                
                // Utiliser la fonction unifiée (Resend ou SMTP)
                require_once(__DIR__ . '/../service/smtp_config.php');
                
                try {
                    // Convertir le message texte en HTML simple
                    $email_body_html = nl2br(htmlspecialchars($email_message));
                    
                    // Utiliser la fonction unifiée (Resend prioritaire, SMTP en fallback)
                    $result = send_email_unified($to, '', $email_subject, $email_body_html, $email_message);
                    
                    if ($result['success']) {
                        $success_message = "Votre message a été envoyé avec succès. Nous vous répondrons dans les plus brefs délais.";
                    } else {
                        // En cas d'erreur, on enregistre l'erreur mais on continue
                        error_log("Erreur d'envoi d'email depuis le centre d'aide: " . $result['message']);
                        $success_message = "Votre message a été enregistré avec succès. Nous vous répondrons dans les plus brefs délais.";
                    }
                } catch (\Exception $e) {
                    // En cas d'erreur, on enregistre l'erreur mais on continue
                    error_log("Erreur d'envoi d'email depuis le centre d'aide: " . $e->getMessage());
                    $success_message = "Votre message a été enregistré avec succès. Nous vous répondrons dans les plus brefs délais.";
                }
                
                // Fallback vers mail() si nécessaire
                if (false) {
                    // Si PHPMailer n'est pas installé, on utilise la fonction mail() native
                    $old_error_reporting = error_reporting();
                    error_reporting($old_error_reporting & ~E_WARNING);
                    
                    // Tentative d'envoi d'email avec suppression des avertissements
                    $mail_sent = @mail($to, $email_subject, $email_message, $headers);
                    
                    // Rétablir le niveau d'erreur précédent
                    error_reporting($old_error_reporting);
                    
                    // Message de succès
                    $success_message = "Votre message a été enregistré avec succès. Nous vous répondrons dans les plus brefs délais.";
                }
                
                // Réinitialiser les champs du formulaire
                $name = $email = $subject = $message = $user_type = '';
            } else {
                $error_message = "Une erreur est survenue lors de l'enregistrement de votre message. Veuillez réessayer.";
            }
            
                $stmt->close();
            } else {
                $error_message = "Une erreur est survenue lors de la création de la table. Le message sera envoyé par email uniquement.";
            }
            
            if ($conn) {
                $conn->close();
            }
        } else {
            // Si pas de connexion DB, on continue quand même pour envoyer l'email
            $error_message = "La base de données n'est pas accessible. Le message sera envoyé par email uniquement.";
        }
    }
}

// La connexion à la base de données est gérée par le fichier config.php
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centre d'Aide - Système de Gestion Scolaire</title>
    <!-- Favicon -->
    <link rel="icon" type="image/jpeg" href="../source/logo.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="../source/logo.jpg">
    <link rel="apple-touch-icon" href="../source/logo.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .help-container {
            max-width: 800px;
            margin: 50px auto;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        .help-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .help-header h1 {
            color: #3c4858;
            font-weight: 600;
        }
        .help-header p {
            color: #6c757d;
        }
        .form-label {
            font-weight: 500;
        }
        .btn-primary {
            background-color: #4e73df;
            border-color: #4e73df;
            padding: 10px 20px;
        }
        .btn-primary:hover {
            background-color: #3a5ccc;
            border-color: #3a5ccc;
        }
        .help-footer {
            text-align: center;
            margin-top: 30px;
            color: #6c757d;
        }
        .faq-section {
            margin-top: 40px;
        }
        .faq-item {
            margin-bottom: 20px;
        }
        .faq-question {
            font-weight: 600;
            color: #3c4858;
            margin-bottom: 10px;
        }
        .faq-answer {
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="help-container">
            <div class="help-header">
                <h1><i class="fas fa-headset me-2"></i>Centre d'Aide</h1>
                <p>Besoin d'assistance ? Envoyez-nous un message et nous vous répondrons dans les plus brefs délais.</p>
            </div>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="<?php echo htmlspecialchars($current_url); ?>">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Nom complet</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Adresse email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="user_type" class="form-label">Type d'utilisateur</label>
                    <select class="form-select" id="user_type" name="user_type" required>
                        <option value="" disabled selected>Sélectionnez votre profil</option>
                        <option value="student" <?php echo isset($user_type) && $user_type === 'student' ? 'selected' : ''; ?>>Étudiant</option>
                        <option value="parent" <?php echo isset($user_type) && $user_type === 'parent' ? 'selected' : ''; ?>>Parent</option>
                        <option value="teacher" <?php echo isset($user_type) && $user_type === 'teacher' ? 'selected' : ''; ?>>Enseignant</option>
                        <option value="staff" <?php echo isset($user_type) && $user_type === 'staff' ? 'selected' : ''; ?>>Personnel</option>
                        <option value="admin" <?php echo isset($user_type) && $user_type === 'admin' ? 'selected' : ''; ?>>Administrateur</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="subject" class="form-label">Sujet</label>
                    <input type="text" class="form-control" id="subject" name="subject" value="<?php echo htmlspecialchars($subject ?? ''); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="message" class="form-label">Message</label>
                    <textarea class="form-control" id="message" name="message" rows="5" required><?php echo htmlspecialchars($message ?? ''); ?></textarea>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>Envoyer le message
                    </button>
                </div>
            </form>
            
            <div class="faq-section">
                <h3 class="mb-4">Questions fréquemment posées</h3>
                
                <div class="faq-item">
                    <div class="faq-question">Comment puis-je réinitialiser mon mot de passe ?</div>
                    <div class="faq-answer">
                        Vous pouvez réinitialiser votre mot de passe en cliquant sur le lien "Mot de passe oublié" sur la page de connexion. 
                        Un email contenant les instructions pour réinitialiser votre mot de passe vous sera envoyé.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">Comment puis-je consulter les notes de mon enfant ?</div>
                    <div class="faq-answer">
                        En tant que parent, connectez-vous à votre compte et accédez à la section "Mes enfants". 
                        Sélectionnez l'enfant dont vous souhaitez consulter les notes, puis cliquez sur "Voir les notes".
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">Comment puis-je voir l'emploi du temps de mon enfant ?</div>
                    <div class="faq-answer">
                        En tant que parent, vous pouvez consulter l'emploi du temps de votre enfant dans la section "Emploi du temps" 
                        de votre espace parent après avoir sélectionné l'enfant concerné.
                    </div>
                </div>
            </div>
            
            <div class="help-footer">
                <p>Pour toute assistance urgente, veuillez nous contacter au <strong>+221 77 807 25 70</strong></p>
                <div class="mt-3">
                    <a href="../index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Retour à l'accueil
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
