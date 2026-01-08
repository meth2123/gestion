<?php
/**
 * Script de test pour diagnostiquer les problèmes SMTP
 * Utilisez ce script pour tester la configuration SMTP
 */

require_once __DIR__ . '/service/smtp_config.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "<h2>Test de configuration SMTP</h2>";

$smtp_config = get_smtp_config();
$password_clean = get_clean_smtp_password();

echo "<h3>Configuration actuelle :</h3>";
echo "<pre>";
echo "Host: " . $smtp_config['host'] . "\n";
echo "Port: " . $smtp_config['port'] . "\n";
echo "Username: " . $smtp_config['username'] . "\n";
echo "Password (avec espaces): " . $smtp_config['password'] . "\n";
echo "Password (sans espaces): " . $password_clean . "\n";
echo "</pre>";

// Demander l'email de test
$test_email = isset($_GET['email']) ? $_GET['email'] : (isset($_POST['email']) ? $_POST['email'] : '');

if (empty($test_email)) {
    echo "<form method='POST'>";
    echo "<label>Email de test : <input type='email' name='email' required></label><br><br>";
    echo "<label>Essayer avec mot de passe nettoyé (sans espaces) : <input type='checkbox' name='clean_password' checked></label><br><br>";
    echo "<button type='submit'>Tester l'envoi</button>";
    echo "</form>";
    exit;
}

$use_clean_password = isset($_POST['clean_password']);

echo "<h3>Test d'envoi d'email...</h3>";

try {
    $mail = new PHPMailer(true);
    
    // Activer le mode debug pour voir les détails
    $mail->SMTPDebug = 2; // 0 = off, 1 = client, 2 = client and server
    $mail->Debugoutput = function($str, $level) {
        echo "<pre style='background: #f0f0f0; padding: 10px; border-left: 3px solid #007bff;'>$str</pre>";
    };
    
    // Configuration SMTP
    $mail->isSMTP();
    $mail->Host = $smtp_config['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $smtp_config['username'];
    $mail->Password = $use_clean_password ? $password_clean : $smtp_config['password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $smtp_config['port'];
    $mail->CharSet = 'UTF-8';
    
    echo "<p><strong>Mot de passe utilisé :</strong> " . ($use_clean_password ? "Nettoyé (sans espaces)" : "Original (avec espaces)") . "</p>";
    
    // Destinataires
    $mail->setFrom($smtp_config['from_email'], $smtp_config['from_name']);
    $mail->addAddress($test_email);
    
    // Contenu
    $mail->isHTML(true);
    $mail->Subject = 'Test SMTP - SchoolManager';
    $mail->Body = '<h1>Test d\'envoi d\'email</h1><p>Si vous recevez cet email, la configuration SMTP fonctionne correctement !</p>';
    $mail->AltBody = 'Test d\'envoi d\'email - Si vous recevez cet email, la configuration SMTP fonctionne correctement !';
    
    $mail->send();
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin-top: 20px;'>";
    echo "<strong>✅ Succès !</strong> L'email a été envoyé avec succès à " . htmlspecialchars($test_email);
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin-top: 20px;'>";
    echo "<strong>❌ Erreur :</strong> " . htmlspecialchars($e->getMessage());
    echo "<br><br><strong>ErrorInfo :</strong> " . htmlspecialchars($mail->ErrorInfo);
    echo "</div>";
    
    echo "<h3>Solutions possibles :</h3>";
    echo "<ol>";
    echo "<li><strong>Vérifier le mot de passe d'application Gmail :</strong>";
    echo "<ul>";
    echo "<li>Allez sur <a href='https://myaccount.google.com/apppasswords' target='_blank'>https://myaccount.google.com/apppasswords</a></li>";
    echo "<li>Créez un nouveau mot de passe d'application (16 caractères)</li>";
    echo "<li>Copiez le mot de passe et mettez-le dans <code>service/smtp_config.php</code></li>";
    echo "</ul></li>";
    echo "<li><strong>Vérifier que l'authentification à deux facteurs est activée</strong> (nécessaire pour les mots de passe d'application)</li>";
    echo "<li><strong>Essayer avec l'autre format de mot de passe</strong> (avec ou sans espaces)</li>";
    echo "<li><strong>Vérifier que le compte Gmail autorise les applications moins sécurisées</strong> (si nécessaire)</li>";
    echo "</ol>";
}

echo "<hr>";
echo "<p><a href='test_smtp_verification.php'>Nouveau test</a></p>";
?>

