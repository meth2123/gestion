<?php
/**
 * Démonstration des changements du système d'abonnement
 * Ce fichier montre les différents états du bouton intelligent
 */

session_start();
require_once __DIR__ . '/service/mysqlcon.php';
require_once __DIR__ . '/components/SmartSubscriptionButton.php';

$smartButton = new SmartSubscriptionButton($link);

// Récupérer quelques abonnements existants pour la démo
$subscriptions = [];
try {
    $result = $link->query("SELECT * FROM subscriptions LIMIT 3");
    while ($row = $result->fetch_assoc()) {
        $subscriptions[] = $row;
    }
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Démonstration - Système d'Abonnement Intelligent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <h1 class="text-center mb-5">🎯 Démonstration du Système d'Abonnement Intelligent</h1>
        
        <div class="alert alert-info">
            <h4><i class="fas fa-info-circle"></i> Comment voir les changements</h4>
            <p>Le bouton "S'abonner" change automatiquement selon votre statut d'abonnement. Voici les différents états :</p>
        </div>

        <!-- État 1: Utilisateur non connecté -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h3><i class="fas fa-user-times"></i> État 1: Utilisateur NON connecté</h3>
            </div>
            <div class="card-body">
                <p><strong>Ce que vous voyez actuellement :</strong></p>
                <div class="mb-3">
                    <?php 
                    // Simuler un utilisateur non connecté
                    unset($_SESSION['user_id']);
                    echo $smartButton->render();
                    ?>
                </div>
                <p class="text-muted">→ Bouton "S'abonner" (vert) qui redirige vers l'inscription</p>
            </div>
        </div>

        <!-- État 2: Utilisateur connecté sans abonnement -->
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h3><i class="fas fa-user"></i> État 2: Utilisateur connecté SANS abonnement</h3>
            </div>
            <div class="card-body">
                <p><strong>Si vous vous connectez avec un compte qui n'a pas d'abonnement :</strong></p>
                <div class="mb-3">
                    <?php 
                    // Simuler un utilisateur connecté sans abonnement
                    $_SESSION['user_id'] = 'test-user-without-subscription';
                    $_SESSION['user_type'] = 'admin';
                    echo $smartButton->renderForLoggedUser();
                    ?>
                </div>
                <p class="text-muted">→ Bouton "S'abonner" (vert) car aucun abonnement trouvé</p>
            </div>
        </div>

        <?php if (!empty($subscriptions)): ?>
        <!-- État 3: Utilisateur avec abonnement actif -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h3><i class="fas fa-user-check"></i> État 3: Utilisateur avec abonnement ACTIF</h3>
            </div>
            <div class="card-body">
                <p><strong>Si vous vous connectez avec un compte qui a un abonnement actif :</strong></p>
                <div class="mb-3">
                    <?php 
                    // Simuler un utilisateur avec abonnement actif
                    $active_subscription = $subscriptions[0];
                    echo $smartButton->render($active_subscription['admin_email'], $active_subscription['school_name']);
                    ?>
                </div>
                <p class="text-muted">→ Bouton "Mon Abonnement" (bleu) qui redirige vers le tableau de bord</p>
                <small class="text-muted">Détails: École: <?php echo htmlspecialchars($active_subscription['school_name']); ?>, Statut: <?php echo $active_subscription['payment_status']; ?></small>
            </div>
        </div>

        <!-- État 4: Utilisateur avec abonnement expiré -->
        <div class="card mb-4">
            <div class="card-header bg-danger text-white">
                <h3><i class="fas fa-user-times"></i> État 4: Utilisateur avec abonnement EXPIRÉ</h3>
            </div>
            <div class="card-body">
                <p><strong>Si vous vous connectez avec un compte qui a un abonnement expiré :</strong></p>
                <div class="mb-3">
                    <?php 
                    // Simuler un utilisateur avec abonnement expiré
                    $expired_subscription = $subscriptions[1] ?? $subscriptions[0];
                    echo $smartButton->render($expired_subscription['admin_email'], $expired_subscription['school_name']);
                    ?>
                </div>
                <p class="text-muted">→ Bouton "Renouveler" (orange) qui redirige vers la page de renouvellement</p>
                <small class="text-muted">Détails: École: <?php echo htmlspecialchars($expired_subscription['school_name']); ?>, Statut: <?php echo $expired_subscription['payment_status']; ?></small>
            </div>
        </div>
        <?php endif; ?>

        <!-- Instructions pour tester -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h3><i class="fas fa-play-circle"></i> Comment tester en vrai</h3>
            </div>
            <div class="card-body">
                <h5>Pour voir les changements sur votre site :</h5>
                <ol>
                    <li><strong>Connectez-vous</strong> avec un compte administrateur qui a un abonnement</li>
                    <li><strong>Retournez sur la page d'accueil</strong> - le bouton devrait changer</li>
                    <li><strong>Testez différents comptes</strong> avec différents statuts d'abonnement</li>
                </ol>
                
                <h5>Pages à tester :</h5>
                <ul>
                    <li><a href="index.php" class="btn btn-primary btn-sm">Page d'accueil</a> - Bouton intelligent dans la navigation</li>
                    <li><a href="module/subscription/dashboard.php" class="btn btn-success btn-sm">Tableau de bord</a> - Nouvelle page de gestion</li>
                    <li><a href="module/subscription/renew.php" class="btn btn-warning btn-sm">Renouvellement</a> - Page améliorée</li>
                </ul>
            </div>
        </div>

        <!-- Comparaison avant/après -->
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h3><i class="fas fa-balance-scale"></i> Avant vs Après</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="text-danger">❌ AVANT (Problème)</h5>
                        <ul>
                            <li>Bouton "S'abonner" pour tout le monde</li>
                            <li>Redirection vers formulaire d'inscription</li>
                            <li>Demande de remplir les informations à nouveau</li>
                            <li>Confusion pour les utilisateurs déjà abonnés</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h5 class="text-success">✅ APRÈS (Solution)</h5>
                        <ul>
                            <li>Bouton intelligent qui s'adapte</li>
                            <li>Détection automatique du statut</li>
                            <li>Interface pré-remplie pour les abonnés</li>
                            <li>Expérience utilisateur fluide</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-4">
            <a href="index.php" class="btn btn-primary btn-lg">
                <i class="fas fa-home"></i> Retour à la page d'accueil
            </a>
        </div>
    </div>
</body>
</html>
