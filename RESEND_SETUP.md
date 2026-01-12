# Configuration Resend pour l'envoi d'emails

## 🚀 Avantages de Resend

Resend est un service d'email moderne qui utilise une API REST au lieu de SMTP, ce qui le rend :
- ✅ **Plus fiable** sur les plateformes cloud comme Render.com
- ✅ **Plus rapide** (pas de timeout de connexion SMTP)
- ✅ **Plus simple** à configurer (juste une clé API)
- ✅ **Meilleur pour la délivrabilité** (infrastructure optimisée)

## 📋 Configuration

### 1. Créer un compte Resend

1. Allez sur [https://resend.com](https://resend.com)
2. Créez un compte gratuit (100 emails/jour en gratuit)
3. Vérifiez votre domaine ou utilisez le domaine par défaut `onboarding.resend.dev`

### 2. Obtenir votre clé API

1. Connectez-vous à votre dashboard Resend
2. Allez dans **API Keys**
3. Cliquez sur **Create API Key**
4. Donnez un nom à votre clé (ex: "SchoolManager Production")
5. Copiez la clé API (elle ne sera affichée qu'une seule fois)

### 3. Configurer les variables d'environnement sur Render.com

Ajoutez ces variables dans votre dashboard Render.com :

```
RESEND_API_KEY=re_xxxxxxxxxxxxxxxxxxxxxxxxxxxxx
RESEND_FROM_EMAIL=noreply@votre-domaine.com
RESEND_FROM_NAME=SchoolManager
```

**Note :** Si `RESEND_FROM_EMAIL` n'est pas défini, le système utilisera `SMTP_FROM_EMAIL` ou `noreply@resend.dev` par défaut.

### 4. Configuration minimale

Pour activer Resend, il suffit d'ajouter :

```
RESEND_API_KEY=re_xxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

Les autres variables sont optionnelles et utiliseront les valeurs SMTP par défaut si non définies.

## 🔄 Fonctionnement

Le système utilise automatiquement **Resend en priorité** si `RESEND_API_KEY` est configurée, sinon il utilise **SMTP** (Gmail, SendGrid, etc.) en fallback.

### Ordre de priorité :

1. **Resend** (si `RESEND_API_KEY` est configurée)
2. **SMTP** (Gmail, SendGrid, etc.) en fallback

## 📧 Utilisation dans le code

Tous les fichiers utilisent maintenant la fonction unifiée `send_email_unified()` qui gère automatiquement Resend ou SMTP :

```php
require_once(__DIR__ . '/smtp_config.php');

$result = send_email_unified(
    $to_email,      // Email du destinataire
    $to_name,       // Nom du destinataire
    $subject,       // Sujet
    $html_body      // Corps HTML
);

if ($result['success']) {
    echo "Email envoyé avec succès !";
} else {
    echo "Erreur : " . $result['message'];
}
```

## 🧪 Test

Pour tester l'envoi d'email avec Resend :

1. Configurez `RESEND_API_KEY` sur Render.com
2. Redéployez l'application
3. Testez l'envoi d'un email (réinitialisation de mot de passe, etc.)
4. Vérifiez les logs : vous devriez voir `"Using Resend API for email to: ..."`

## 📊 Monitoring

- **Dashboard Resend** : Consultez les statistiques d'envoi, taux de délivrabilité, etc.
- **Logs Render.com** : Les logs indiquent si Resend ou SMTP est utilisé

## ⚠️ Important

- **Domaine vérifié** : Pour une meilleure délivrabilité, vérifiez votre domaine dans Resend
- **Limites** : Le plan gratuit offre 100 emails/jour, 3000 emails/mois
- **Fallback automatique** : Si Resend échoue, le système bascule automatiquement sur SMTP

## 🔗 Documentation

- [Resend Documentation](https://resend.com/docs)
- [Resend API Reference](https://resend.com/docs/api-reference/emails/send-email)

