<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration OneSignal</title>
    <script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.js" defer></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .status {
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .status.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .status.info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
        }
        button:hover {
            background-color: #0056b3;
        }
        button:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }
        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Configuration des Notifications Push</h1>
        
        <div id="status" class="status info">
            Initialisation du service de notification...
        </div>
        
        <div id="userInfo" class="hidden">
            <h3>Informations utilisateur</h3>
            <p><strong>Type:</strong> <span id="userType"></span></p>
            <p><strong>ID:</strong> <span id="userId"></span></p>
            <p><strong>Nom:</strong> <span id="userName"></span></p>
        </div>
        
        <div id="actions">
            <button id="subscribeBtn" onclick="subscribeToNotifications()">S'abonner aux notifications</button>
            <button id="unsubscribeBtn" onclick="unsubscribeFromNotifications()" class="hidden">Se désabonner</button>
            <button id="testBtn" onclick="sendTestNotification()" class="hidden">Envoyer une notification test</button>
        </div>
        
        <div id="logs">
            <h3>Journal d'activité</h3>
            <div id="logContent" style="background: #f8f9fa; padding: 10px; border-radius: 4px; max-height: 200px; overflow-y: auto; font-family: monospace; font-size: 12px;">
            </div>
        </div>
    </div>

    <script>
        // Configuration OneSignal - À remplacer avec vos vraies clés
        const ONESIGNAL_APP_ID = 'YOUR_APP_ID_HERE';
        
        let currentUser = null;
        let isSubscribed = false;
        let playerId = null;

        // Journalisation
        function log(message, type = 'info') {
            const logContent = document.getElementById('logContent');
            const timestamp = new Date().toLocaleTimeString();
            const logEntry = document.createElement('div');
            logEntry.style.color = type === 'error' ? 'red' : type === 'success' ? 'green' : 'black';
            logEntry.textContent = `[${timestamp}] ${message}`;
            logContent.appendChild(logEntry);
            logContent.scrollTop = logContent.scrollHeight;
        }

        // Mettre à jour le statut
        function updateStatus(message, type = 'info') {
            const status = document.getElementById('status');
            status.textContent = message;
            status.className = `status ${type}`;
        }

        // Initialiser OneSignal
        window.OneSignal = window.OneSignal || [];
        OneSignal.push(function() {
            OneSignal.init({
                appId: ONESIGNAL_APP_ID,
                notifyButton: {
                    enable: false
                },
                allowLocalhostAsSecureOrigin: true
            });

            // Vérifier l'état de l'abonnement
            OneSignal.isPushNotificationsEnabled().then(function(isEnabled) {
                isSubscribed = isEnabled;
                if (isEnabled) {
                    OneSignal.getUserId().then(function(userId) {
                        playerId = userId;
                        log(`Player ID: ${playerId}`);
                        updateStatus('Notifications activées', 'success');
                        updateUI();
                        registerPlayerId();
                    });
                } else {
                    updateStatus('Notifications non activées', 'info');
                    updateUI();
                }
            });

            // Écouter les changements d'abonnement
            OneSignal.on('subscriptionChange', function(isSubscribed) {
                log(`Abonnement changé: ${isSubscribed}`);
                if (isSubscribed) {
                    OneSignal.getUserId().then(function(userId) {
                        playerId = userId;
                        log(`Nouveau Player ID: ${playerId}`);
                        registerPlayerId();
                    });
                }
                updateUI();
            });
        });

        // Récupérer les informations utilisateur depuis la session
        function getUserInfo() {
            // Ces informations devraient venir de votre session PHP
            // Pour l'exemple, nous utilisons des valeurs par défaut
            return {
                type: '<?php echo isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'student'; ?>',
                id: '<?php echo isset($_SESSION['login_id']) ? $_SESSION['login_id'] : ''; ?>',
                name: '<?php echo isset($_SESSION['login_name']) ? $_SESSION['login_name'] : 'Utilisateur'; ?>'
            };
        }

        // S'abonner aux notifications
        function subscribeToNotifications() {
            updateStatus('Demande d\'abonnement...', 'info');
            OneSignal.showNativePrompt();
        }

        // Se désabonner
        function unsubscribeFromNotifications() {
            OneSignal.setSubscription(false);
            updateStatus('Désabonnement en cours...', 'info');
        }

        // Envoyer une notification test
        function sendTestNotification() {
            if (!currentUser || !playerId) {
                updateStatus('Informations utilisateur manquantes', 'error');
                return;
            }

            const notificationData = {
                title: 'Notification Test',
                message: 'Ceci est une notification de test',
                user_id: currentUser.id,
                user_type: currentUser.type
            };

            fetch('api/send_test_notification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(notificationData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    log('Notification test envoyée avec succès', 'success');
                    updateStatus('Notification test envoyée', 'success');
                } else {
                    log('Échec de l\'envoi: ' + (data.message || 'Erreur inconnue'), 'error');
                    updateStatus('Échec de l\'envoi', 'error');
                }
            })
            .catch(error => {
                log('Erreur: ' + error.message, 'error');
                updateStatus('Erreur de connexion', 'error');
            });
        }

        // Enregistrer le Player ID auprès du serveur
        function registerPlayerId() {
            if (!currentUser || !playerId) return;

            fetch('api/register_player_id.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: currentUser.id,
                    user_type: currentUser.type,
                    player_id: playerId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    log('Player ID enregistré avec succès', 'success');
                } else {
                    log('Échec de l\'enregistrement: ' + (data.message || 'Erreur inconnue'), 'error');
                }
            })
            .catch(error => {
                log('Erreur d\'enregistrement: ' + error.message, 'error');
            });
        }

        // Mettre à jour l'interface
        function updateUI() {
            const subscribeBtn = document.getElementById('subscribeBtn');
            const unsubscribeBtn = document.getElementById('unsubscribeBtn');
            const testBtn = document.getElementById('testBtn');

            if (isSubscribed) {
                subscribeBtn.classList.add('hidden');
                unsubscribeBtn.classList.remove('hidden');
                testBtn.classList.remove('hidden');
            } else {
                subscribeBtn.classList.remove('hidden');
                unsubscribeBtn.classList.add('hidden');
                testBtn.classList.add('hidden');
            }
        }

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            currentUser = getUserInfo();
            
            if (currentUser.id) {
                document.getElementById('userType').textContent = currentUser.type;
                document.getElementById('userId').textContent = currentUser.id;
                document.getElementById('userName').textContent = currentUser.name;
                document.getElementById('userInfo').classList.remove('hidden');
                log(`Utilisateur: ${currentUser.name} (${currentUser.type}:${currentUser.id})`);
            } else {
                updateStatus('Utilisateur non connecté', 'error');
                log('Utilisateur non connecté', 'error');
            }

            updateUI();
        });
    </script>
</body>
</html>
