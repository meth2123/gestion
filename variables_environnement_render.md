# Variables d'environnement pour Render.com

Ce document liste **toutes les variables d'environnement réellement utilisées dans le code** à configurer dans le dashboard Render.com pour l'application School Manager.

## 📊 Résumé rapide

### Variables OBLIGATOIRES pour Render.com :

1. **Détection d'environnement :**
   - `RENDER=true` ou `IS_RENDER=true`

2. **Base de données (si base externe) :**
   - `EXTERNAL_DATABASE_HOST`
   - `EXTERNAL_DATABASE_USER`
   - `EXTERNAL_DATABASE_PASSWORD`
   - `EXTERNAL_DATABASE_NAME`

3. **Application :**
   - `APP_URL`

4. **PayDunya :**
   - `PAYDUNYA_MASTER_KEY`
   - `PAYDUNYA_PUBLIC_KEY`
   - `PAYDUNYA_PRIVATE_KEY`
   - `PAYDUNYA_TOKEN`

### Variables OPTIONNELLES :
- `EXTERNAL_DATABASE_PORT` (défaut: 3306)
- `WHATSAPP_API_KEY` (mode simulation si absent)
- `WHATSAPP_PHONE_NUMBER_ID` (mode simulation si absent)
- `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_FROM_EMAIL`, `SMTP_FROM_NAME` (valeurs par défaut si absentes)

### Variables NON UTILISÉES dans le code PHP :
- `APP_ENV` (défini dans render.yaml mais non utilisé)
- `APP_DEBUG` (défini dans render.yaml mais non utilisé)

## 📋 Table des matières

1. [Variables de détection d'environnement](#variables-de-détection-denvironnement)
2. [Variables de base de données](#variables-de-base-de-données)
3. [Variables de l'application](#variables-de-lapplication)
4. [Variables PayDunya (Paiement)](#variables-paydunya-paiement)
5. [Variables WhatsApp (Optionnel)](#variables-whatsapp-optionnel)
6. [Variables SMTP (Email)](#variables-smtp-email)

---

## 🔍 Variables de détection d'environnement

Ces variables permettent à l'application de détecter qu'elle s'exécute sur Render.com.

**Fichiers utilisant ces variables :** `service/db_config.php` (ligne 7), `service/mysqlcon.php` (ligne 57)

| Variable | Valeur | Description | Obligatoire | Utilisé dans |
|----------|--------|-------------|-------------|--------------|
| `RENDER` | `true` | Indique que l'application s'exécute sur Render.com | ✅ Oui | `db_config.php`, `mysqlcon.php` |
| `IS_RENDER` | `true` | Alternative pour la détection de Render.com | ✅ Oui (recommandé) | `db_config.php` |
| `RENDER_SERVICE_ID` | Auto | ID du service Render (généré automatiquement, optionnel) | ❌ Non | `db_config.php` |

**Note :** Au moins une des variables `RENDER` ou `IS_RENDER` doit être définie à `true` pour que l'application détecte l'environnement Render.com.

---

## 🗄️ Variables de base de données

### Option 1 : Base de données externe (recommandé pour Render.com)

Si vous utilisez une base de données MySQL externe (PlanetScale, Railway, AWS RDS, etc.) :

**Fichiers utilisant ces variables :** `service/db_config.php` (lignes 19-24)

| Variable | Exemple | Description | Obligatoire | Utilisé dans |
|----------|---------|-------------|-------------|--------------|
| `EXTERNAL_DATABASE_HOST` | `mysql.example.com` | Adresse du serveur MySQL externe | ✅ Oui | `db_config.php` |
| `EXTERNAL_DATABASE_PORT` | `3306` | Port de connexion MySQL (par défaut: 3306) | ⚠️ Optionnel | `db_config.php` |
| `EXTERNAL_DATABASE_USER` | `mon_utilisateur` | Nom d'utilisateur MySQL | ✅ Oui | `db_config.php` |
| `EXTERNAL_DATABASE_PASSWORD` | `mon_mot_de_passe` | Mot de passe MySQL | ✅ Oui | `db_config.php` |
| `EXTERNAL_DATABASE_NAME` | `gestion` | Nom de la base de données | ✅ Oui | `db_config.php` |

**⚠️ Important :** Ces variables sont **obligatoires** si `RENDER=true` ou `IS_RENDER=true`. L'application vérifie leur présence et affichera une erreur si elles sont manquantes.

### Option 2 : Base de données Render.com ou Docker

Si vous utilisez le service MySQL de Render.com (défini dans `render.yaml`) ou un environnement Docker :

**Fichiers utilisant ces variables :** `service/db_config.php` (lignes 40-45), `db/config.php` (lignes 6-9), `service/mysqlcon.php` (ligne 57)

| Variable | Description | Obligatoire | Utilisé dans |
|----------|-------------|-------------|--------------|
| `DB_HOST` | Hôte de la base de données | ✅ Oui | `db_config.php`, `db/config.php`, `mysqlcon.php` |
| `DB_USER` | Utilisateur de la base de données | ✅ Oui | `db_config.php`, `db/config.php` |
| `DB_PASSWORD` | Mot de passe de la base de données | ✅ Oui | `db_config.php`, `db/config.php` |
| `DB_NAME` | Nom de la base de données | ✅ Oui | `db_config.php`, `db/config.php` |
| `DB_PORT` | Port MySQL (par défaut: 3306) | ⚠️ Optionnel | `db_config.php` |
| `DB_SOCKET` | Socket MySQL (généralement vide) | ❌ Non | `db_config.php` |

**Note :** 
- Si vous utilisez `render.yaml`, les variables `DB_HOST`, `DB_USER`, `DB_PASSWORD`, `DB_NAME` sont automatiquement configurées via `fromDatabase`
- Si vous n'utilisez pas `render.yaml`, vous devez les configurer manuellement
- Ces variables sont utilisées quand `RENDER` n'est pas détecté mais que `DB_HOST` est défini (environnement Docker/production)

---

## 🌐 Variables de l'application

| Variable | Exemple | Description | Obligatoire | Utilisé dans |
|----------|---------|-------------|-------------|--------------|
| `APP_URL` | `https://schoolmanager.sn` | URL complète de l'application (avec https://) | ✅ Oui | `service/paydunya_env.php` (ligne 7) |
| `APP_ENV` | `production` | Environnement de l'application (défini dans `render.yaml` mais non utilisé dans le code PHP) | ❌ Non | `render.yaml` uniquement |
| `APP_DEBUG` | `false` | Mode debug (défini dans `render.yaml` mais non utilisé dans le code PHP) | ❌ Non | `render.yaml` uniquement |

**Note :** 
- L'`APP_URL` est **obligatoire** et utilisée dans `service/paydunya_env.php` pour générer les URLs de callback PayDunya (`callback_url`, `cancel_url`, `return_url`)
- Elle doit être en HTTPS pour PayDunya
- `APP_ENV` et `APP_DEBUG` sont définis dans `render.yaml` mais ne sont pas actuellement utilisés dans le code PHP de l'application

---

## 💳 Variables PayDunya (Paiement)

Ces variables sont nécessaires pour le système de paiement PayDunya.

**Fichiers utilisant ces variables :** `service/paydunya_env.php` (lignes 8-11)

| Variable | Exemple | Description | Obligatoire | Utilisé dans |
|----------|---------|-------------|-------------|--------------|
| `PAYDUNYA_MASTER_KEY` | `J8Bk1t8t-AWZp-kVD1-WbjB-CndDy4hrVS7J` | Clé maître PayDunya | ✅ Oui | `paydunya_env.php` |
| `PAYDUNYA_PUBLIC_KEY` | `test_public_9zzBrzEfagNrSYsVi3I3nreNKXV` | Clé publique PayDunya | ✅ Oui | `paydunya_env.php` |
| `PAYDUNYA_PRIVATE_KEY` | `test_private_0WuP5er1GGbqeJggPclXAyWcKad` | Clé privée PayDunya | ✅ Oui | `paydunya_env.php` |
| `PAYDUNYA_TOKEN` | `IeXty0flMeb4AfmTtkR7` | Token PayDunya | ✅ Oui | `paydunya_env.php` |

**Note :** 
- ⚠️ **Important :** Remplacez les valeurs d'exemple par vos **vraies clés PayDunya de production**
- Ces clés sont sensibles et doivent être gardées secrètes
- Dans `render.yaml`, ces variables sont marquées avec `sync: false` pour éviter qu'elles soient synchronisées automatiquement
- Si ces variables ne sont pas définies, l'application utilisera les valeurs par défaut (clés de test)

---

## 📱 Variables WhatsApp (Optionnel)

Ces variables sont nécessaires uniquement si vous souhaitez activer l'envoi de messages WhatsApp.

**Fichiers utilisant ces variables :** `service/SmsService.php` (lignes 21-22)

| Variable | Exemple | Description | Obligatoire | Utilisé dans |
|----------|---------|-------------|-------------|--------------|
| `WHATSAPP_API_KEY` | `EAABwzLix...` | Clé API WhatsApp Business | ❌ Non | `SmsService.php` |
| `WHATSAPP_PHONE_NUMBER_ID` | `123456789012345` | ID du numéro de téléphone WhatsApp Business | ❌ Non | `SmsService.php` |

**Note :** 
- Si ces variables ne sont pas définies, le service WhatsApp fonctionnera en **mode simulation** (voir `SmsService.php` ligne 60-67)
- Pour obtenir ces clés, vous devez créer une application WhatsApp Business sur [Facebook Developers](https://developers.facebook.com/)
- Le service vérifie si ces variables sont vides et retourne un succès simulé en mode développement

---

## 📧 Variables SMTP (Email)

Ces variables permettent de configurer l'envoi d'emails via SMTP. Si elles ne sont pas définies, l'application utilisera les valeurs par défaut (Gmail).

**Fichiers utilisant ces variables :** `service/smtp_config.php` (lignes 15-21), `components/SecureSubscriptionChecker.php`

| Variable | Exemple | Description | Obligatoire | Utilisé dans |
|----------|---------|-------------|-------------|--------------|
| `SMTP_HOST` | `smtp.gmail.com` | Serveur SMTP (par défaut: smtp.gmail.com) | ❌ Non | `smtp_config.php` |
| `SMTP_PORT` | `587` | Port SMTP (par défaut: 587 pour STARTTLS, 465 pour SSL) | ❌ Non | `smtp_config.php` |
| `SMTP_USERNAME` | `votre-email@gmail.com` | Nom d'utilisateur SMTP (par défaut: methndiaye43@gmail.com) | ❌ Non | `smtp_config.php` |
| `SMTP_PASSWORD` | `votre_mot_de_passe_app` | Mot de passe d'application SMTP (par défaut: valeur hardcodée) | ❌ Non | `smtp_config.php` |
| `SMTP_FROM_EMAIL` | `votre-email@gmail.com` | Email expéditeur (par défaut: methndiaye43@gmail.com) | ❌ Non | `smtp_config.php` |
| `SMTP_FROM_NAME` | `SchoolManager` | Nom de l'expéditeur (par défaut: SchoolManager) | ❌ Non | `smtp_config.php` |
| `SMTP_ENCRYPTION` | `tls` | Type de chiffrement (tls ou ssl, par défaut: tls) | ❌ Non | `smtp_config.php` |

**Note :** 
- ⚠️ **Recommandé sur Render.com :** Définir ces variables pour éviter d'exposer les identifiants SMTP dans le code
- L'application essaie automatiquement plusieurs méthodes de connexion (port 587 avec STARTTLS, puis port 465 avec SSL) pour améliorer la compatibilité avec Render
- Pour Gmail, vous devez utiliser un **mot de passe d'application** (pas votre mot de passe Gmail normal)
- Les timeouts sont augmentés (90 secondes) pour améliorer la connexion depuis Render
- **⚠️ IMPORTANT :** Si vous rencontrez des erreurs "Connection timed out" sur Render.com, cela peut indiquer que Render bloque les connexions SMTP sortantes. Dans ce cas :
  - Vérifiez que les ports 587 et 465 ne sont pas bloqués dans les paramètres réseau de Render
  - **Recommandation :** Utilisez un service d'email tiers comme SendGrid ou Mailgun qui sont mieux optimisés pour les environnements cloud
  - L'application inclut maintenant des retries avec délais progressifs et des messages d'erreur plus explicites

**Configuration recommandée pour Gmail sur Render :**
```
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=votre-email@gmail.com
SMTP_PASSWORD=votre_mot_de_passe_application_gmail
SMTP_FROM_EMAIL=votre-email@gmail.com
SMTP_FROM_NAME=SchoolManager
SMTP_ENCRYPTION=tls
```

**Alternative : Services d'email tiers recommandés pour Render :**
- **SendGrid** : `SMTP_HOST=smtp.sendgrid.net`, `SMTP_PORT=587`
- **Mailgun** : `SMTP_HOST=smtp.mailgun.org`, `SMTP_PORT=587`
- **Amazon SES** : `SMTP_HOST=email-smtp.region.amazonaws.com`, `SMTP_PORT=587`

---

## 📝 Configuration dans Render.com

### Étapes pour configurer les variables d'environnement :

1. Connectez-vous à votre dashboard Render.com
2. Sélectionnez votre service Web (ex: `schoolmanager`)
3. Allez dans l'onglet **"Environment"** ou **"Settings" > "Environment Variables"**
4. Cliquez sur **"Add Environment Variable"**
5. Ajoutez chaque variable avec sa valeur correspondante
6. Cliquez sur **"Save Changes"**

### Exemple de configuration minimale (Base de données externe) :

```
# Détection d'environnement Render
RENDER=true
IS_RENDER=true

# Configuration de l'application
APP_URL=https://schoolmanager.sn

# Base de données externe (OBLIGATOIRE si RENDER=true)
EXTERNAL_DATABASE_HOST=mysql.example.com
EXTERNAL_DATABASE_PORT=3306
EXTERNAL_DATABASE_USER=mon_utilisateur
EXTERNAL_DATABASE_PASSWORD=mon_mot_de_passe
EXTERNAL_DATABASE_NAME=gestion

# Clés PayDunya (OBLIGATOIRE)
PAYDUNYA_MASTER_KEY=votre_cle_maitre
PAYDUNYA_PUBLIC_KEY=votre_cle_publique
PAYDUNYA_PRIVATE_KEY=votre_cle_privee
PAYDUNYA_TOKEN=votre_token

# WhatsApp (Optionnel)
WHATSAPP_API_KEY=votre_cle_whatsapp
WHATSAPP_PHONE_NUMBER_ID=votre_phone_id

# SMTP (Email - Optionnel mais recommandé)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=votre-email@gmail.com
SMTP_PASSWORD=votre_mot_de_passe_application
SMTP_FROM_EMAIL=votre-email@gmail.com
SMTP_FROM_NAME=SchoolManager
SMTP_ENCRYPTION=tls
```

### Exemple de configuration avec base de données Render.com (via render.yaml) :

Si vous utilisez `render.yaml`, les variables suivantes sont suffisantes (les variables DB_* sont générées automatiquement) :

```
# Détection d'environnement Render
RENDER=true
IS_RENDER=true

# Configuration de l'application
APP_URL=https://schoolmanager.sn

# Clés PayDunya (OBLIGATOIRE)
PAYDUNYA_MASTER_KEY=votre_cle_maitre
PAYDUNYA_PUBLIC_KEY=votre_cle_publique
PAYDUNYA_PRIVATE_KEY=votre_cle_privee
PAYDUNYA_TOKEN=votre_token
```

---

## ✅ Vérification

Après avoir configuré les variables d'environnement :

1. Redéployez votre service sur Render.com
2. Vérifiez les logs pour confirmer que les variables sont bien chargées
3. Testez la connexion à la base de données
4. Testez le système de paiement PayDunya

### Commandes utiles pour vérifier les logs :

Dans les logs Render.com, vous devriez voir :
- `"Détection d'environnement - RENDER: true"`
- `"Environnement Render.com détecté. Connexion à la base de données externe: [votre-hôte]"`
- `"Configuration PayDunya chargée - Mode: Production"`
- `"Connexion à la base de données réussie"`

Si vous voyez des erreurs comme :
- `"ERREUR CRITIQUE: Variables d'environnement manquantes pour la base de données sur Render.com"`
- Cela signifie que les variables `EXTERNAL_DATABASE_*` ne sont pas correctement configurées

---

## 🔒 Sécurité

⚠️ **Important :** 

- Ne commitez jamais les valeurs réelles des variables d'environnement dans votre dépôt Git
- Utilisez des mots de passe forts pour la base de données
- Gardez vos clés PayDunya secrètes
- Activez le mode HTTPS pour votre application
- En production, utilisez des clés PayDunya de production (pas les clés de test)

---

## 📚 Ressources

- [Documentation Render.com - Environment Variables](https://render.com/docs/environment-variables)
- [Guide de déploiement Render.com](./render_deployment_guide.md)
- [Documentation PayDunya](https://paydunya.com/developers)

---

**Dernière mise à jour :** 2024

