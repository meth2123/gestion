# Configuration des Notifications Push OneSignal

## Installation et Configuration

### 1. Configuration OneSignal

1. Créez un compte sur [https://dashboard.onesignal.com/](https://dashboard.onesignal.com/)
2. Créez une nouvelle application
3. Configurez les paramètres pour votre plateforme web
4. Récupérez votre **App ID** et **API Key**

### 2. Configuration des variables d'environnement

Ajoutez ces variables dans votre configuration:

```bash
ONESIGNAL_APP_ID=votre_app_id_ici
ONESIGNAL_API_KEY=votre_api_key_ici
```

### 3. Intégration dans le code

Le système de notifications push est maintenant intégré dans votre application avec les fonctionnalités suivantes:

#### Notifications pour création d'emplois du temps
- Quand un admin crée un emploi du temps pour une classe:
  - L'enseignant concerné reçoit une notification
  - Tous les étudiants de la classe reçoivent une notification
  - La notification contient les détails de l'emploi du temps

#### Notifications ciblées par l'admin
- Quand l'admin crée une notification depuis le panneau de gestion:
  - Les destinataires sélectionnés reçoivent la notification push
  - La notification affiche "L'admin X a créé une notification: [titre]"
  - Seuls les utilisateurs créés par cet admin peuvent recevoir les notifications

### 4. Configuration des abonnements

Chaque utilisateur doit s'abonner aux notifications push:

1. Accédez à `notifications_setup.php` sur votre site
2. Cliquez sur "S'abonner aux notifications"
3. Acceptez la permission du navigateur
4. Le player ID sera automatiquement enregistré

### 5. Structure des fichiers créés

- `service/OneSignalService.php` - Service OneSignal de base
- `service/PushNotificationService.php` - Service de notification push intégré
- `api/register_player_id.php` - API pour enregistrer les player IDs
- `api/send_test_notification.php` - API pour tester les notifications
- `notifications_setup.php` - Page de configuration pour les utilisateurs

### 6. Modifications apportées

#### module/admin/createTimeTable.php
- Ajout de l'intégration des notifications push lors de la création d'emplois du temps
- Notification automatique aux enseignants et étudiants concernés

#### module/admin/manage_notifications.php  
- Ajout de l'envoi de notifications push lors de la création de notifications admin
- Support des notifications ciblées par type d'utilisateur

### 7. Base de données

Les colonnes suivantes ont été ajoutées automatiquement:
- `admin.onesignal_player_id`
- `teachers.onesignal_player_id`  
- `students.onesignal_player_id`
- `parents.onesignal_player_id`

### 8. Test du système

1. Configurez vos clés OneSignal
2. Accédez à `notifications_setup.php` pour vous abonner
3. Créez un emploi du temps ou une notification admin
4. Vérifiez que vous recevez les notifications push

### 9. Personnalisation

Vous pouvez personnaliser:
- Les titres et messages des notifications dans les services
- Les segments et filtres pour des notifications plus ciblées
- Les données additionnelles envoyées avec les notifications

### 10. Dépannage

- Vérifiez que les clés OneSignal sont correctes
- Assurez-vous que les utilisateurs ont accepté les notifications
- Vérifiez les logs d'erreurs PHP pour plus de détails
- Testez avec `send_test_notification.php` pour vérifier la connectivité
