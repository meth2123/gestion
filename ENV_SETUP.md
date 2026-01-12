# Configuration des variables d'environnement

## 🔧 Configuration locale (WAMP/Windows)

Pour que Resend fonctionne en local, vous devez créer un fichier `.env` à la racine du projet.

### Étapes :

1. **Créer le fichier `.env`** à la racine du projet (même niveau que `index.php`)

2. **Ajouter votre clé API Resend** :
   ```
   RESEND_API_KEY=re_xxxxxxxxxxxxxxxxxxxxxxxxxxxxx
   ```

3. **Optionnel - Configurer l'email expéditeur** :
   ```
   RESEND_FROM_EMAIL=noreply@votre-domaine.com
   RESEND_FROM_NAME=SchoolManager
   ```

### Exemple de fichier `.env` complet :

```env
# Configuration Resend (Obligatoire)
RESEND_API_KEY=re_xxxxxxxxxxxxxxxxxxxxxxxxxxxxx

# Email expéditeur (optionnel)
RESEND_FROM_EMAIL=noreply@votre-domaine.com
RESEND_FROM_NAME=SchoolManager

# Base de données (optionnel - valeurs par défaut pour WAMP)
# DB_HOST=localhost
# DB_USER=root
# DB_PASSWORD=
# DB_NAME=gestion
```

## 🚀 Configuration sur Render.com

Sur Render.com, configurez les variables d'environnement dans le dashboard :

1. Allez dans votre service sur Render.com
2. Cliquez sur **Environment**
3. Ajoutez les variables :
   - `RESEND_API_KEY` = votre clé API Resend
   - `RESEND_FROM_EMAIL` = votre email expéditeur (optionnel)
   - `RESEND_FROM_NAME` = nom expéditeur (optionnel)

## 📝 Obtenir votre clé API Resend

1. Allez sur [https://resend.com](https://resend.com)
2. Créez un compte gratuit (100 emails/jour)
3. Allez dans **API Keys**
4. Cliquez sur **Create API Key**
5. Copiez la clé API (elle commence par `re_`)

## ⚠️ Important

- Le fichier `.env` est ignoré par Git (dans `.gitignore`)
- Ne commitez JAMAIS votre clé API dans le code
- Sur Render.com, utilisez les variables d'environnement du dashboard
- En local, utilisez le fichier `.env`

## 🔍 Vérification

Pour vérifier que la configuration fonctionne, consultez les logs PHP. Vous devriez voir :
- `✅ Using Resend API for email to: ...` si la configuration est correcte
- `❌ ERREUR CRITIQUE: RESEND_API_KEY non configurée...` si la clé est manquante

