<?php
$page_title = 'Guide Enseignant - SchoolManager';
$page_description = 'Documentation et guides d�utilisation de SchoolManager.';
$robots = 'index, follow';
$include_google_verification = false;
require_once '../service/db_utils.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guide Enseignant - Système de Gestion Scolaire</title>
    <?php require_once __DIR__ . '/../seo.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .feature-section {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .step-list {
            list-style-type: none;
            padding-left: 0;
        }
        .step-list li {
            margin-bottom: 15px;
            padding-left: 25px;
            position: relative;
        }
        .step-list li:before {
            content: "→";
            position: absolute;
            left: 0;
            color: #0d6efd;
        }
        .screenshot {
            border: 1px solid #dee2e6;
            border-radius: 4px;
            max-width: 100%;
            height: auto;
            margin: 15px 0;
        }
        .nav-pills .nav-link.active {
            background-color: #0d6efd;
        }
        .text-primary {
            color: #0d6efd !important;
        }
        .bg-light-primary {
            background-color: rgba(13, 110, 253, 0.1);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-school me-2"></i>Système de Gestion Scolaire
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php"><i class="fas fa-home me-1"></i> Accueil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php"><i class="fas fa-book me-1"></i> Documentation</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row mb-4">
            <div class="col-md-8">
                <h1 class="display-5 fw-bold">Guide de l'Enseignant</h1>
                <p class="lead">Ce guide vous aidera à utiliser efficacement toutes les fonctionnalités du module enseignant dans notre système de gestion scolaire.</p>
            </div>
            <div class="col-md-4 text-md-end">
                <img src="../source/teacher.png" alt="Enseignant" class="img-fluid rounded-circle" style="max-width: 120px;">
            </div>
        </div>
        
        <!-- Table des matières -->
        <div class="feature-section bg-light-primary">
            <h2 class="h4 mb-3"><i class="fas fa-list me-2 text-primary"></i>Table des matières</h2>
            <div class="row">
                <div class="col-md-6">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item bg-transparent"><a href="#tableau-bord" class="text-decoration-none"><i class="fas fa-tachometer-alt me-2 text-primary"></i>1. Tableau de bord</a></li>
                        <li class="list-group-item bg-transparent"><a href="#profil" class="text-decoration-none"><i class="fas fa-user me-2 text-primary"></i>2. Gestion du profil</a></li>
                        <li class="list-group-item bg-transparent"><a href="#cours" class="text-decoration-none"><i class="fas fa-book me-2 text-primary"></i>3. Gestion des cours</a></li>
                        <li class="list-group-item bg-transparent"><a href="#notes" class="text-decoration-none"><i class="fas fa-graduation-cap me-2 text-primary"></i>4. Gestion des notes</a></li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item bg-transparent"><a href="#presence" class="text-decoration-none"><i class="fas fa-calendar-check me-2 text-primary"></i>5. Gestion des présences</a></li>
                        <li class="list-group-item bg-transparent"><a href="#examens" class="text-decoration-none"><i class="fas fa-clock me-2 text-primary"></i>6. Examens et devoirs</a></li>
                        <li class="list-group-item bg-transparent"><a href="#salaire" class="text-decoration-none"><i class="fas fa-money-bill-wave me-2 text-primary"></i>7. Suivi des salaires</a></li>
                        <li class="list-group-item bg-transparent"><a href="#notifications" class="text-decoration-none"><i class="fas fa-bell me-2 text-primary"></i>8. Notifications</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Tableau de bord -->
        <div id="tableau-bord" class="feature-section">
            <h2><i class="fas fa-tachometer-alt me-2 text-primary"></i>1. Tableau de bord</h2>
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title">Vue d'ensemble du tableau de bord</h5>
                    <p>Le tableau de bord est votre point d'entrée principal dans le système. Il vous donne un aperçu de toutes les informations importantes en un coup d'œil.</p>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="fw-bold"><i class="fas fa-th-large me-2 text-primary"></i>Eléments du tableau de bord</h6>
                            <ul class="step-list">
                                <li>Résumé des cours assignés</li>
                                <li>Notifications récentes</li>
                                <li>Statistiques des notes</li>
                                <li>Calendrier des examens à venir</li>
                                <li>Accès rapide aux fonctionnalités principales</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-info">
                                <h6 class="fw-bold"><i class="fas fa-lightbulb me-2"></i>Astuce</h6>
                                <p class="mb-0">Utilisez les cartes d'accès rapide pour naviguer facilement vers vos tâches quotidiennes les plus fréquentes.</p>
                            </div>
                        </div>
                    </div>
                    
                    <h6 class="fw-bold"><i class="fas fa-list-check me-2 text-primary"></i>Comment utiliser le tableau de bord</h6>
                    <ol class="step-list">
                        <li>Consultez vos notifications en haut à droite de l'écran</li>
                        <li>Vérifiez vos cours du jour dans la section "Mes cours"</li>
                        <li>Accédez rapidement à vos classes via les cartes de classe</li>
                        <li>Consultez les examens à venir dans la section "Examens"</li>
                        <li>Utilisez le menu latéral pour accéder aux différentes fonctionnalités</li>
                    </ol>
                </div>
            </div>
            
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0"><i class="fas fa-bell me-2 text-primary"></i>Gestion des notifications</h5>
                </div>
                <div class="card-body">
                    <p>Le système de notifications vous permet de rester informé des événements importants.</p>
                    
                    <h6 class="fw-bold">Types de notifications</h6>
                    <ul class="step-list">
                        <li>Nouveaux messages des administrateurs</li>
                        <li>Rappels d'examens à venir</li>
                        <li>Alertes de paiement de salaire</li>
                        <li>Modifications d'emploi du temps</li>
                    </ul>
                    
                    <div class="alert alert-warning">
                        <p class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Les notifications non lues sont indiquées par un badge rouge. Cliquez sur une notification pour la marquer comme lue.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Emploi du temps -->
        <div id="emploi-temps" class="feature-section">
            <h2>2. Gestion de l'emploi du temps</h2>
            <div class="card mb-3">
                <div class="card-body">
                    <h5>Consulter et gérer votre emploi du temps</h5>
                    <ul class="step-list">
                        <li>Voir votre emploi du temps
                            <ul>
                                <li>Vue hebdomadaire</li>
                                <li>Vue mensuelle</li>
                                <li>Filtres par classe</li>
                            </ul>
                        </li>
                        <li>Gérer les changements
                            <ul>
                                <li>Demander un changement d'horaire</li>
                                <li>Signaler une indisponibilité</li>
                                <li>Proposer un remplacement</li>
                            </ul>
                        </li>
                        <li>Consulter les salles attribuées</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Notes -->
        <div id="notes" class="feature-section">
            <h2>3. Gestion des notes</h2>
            <div class="card mb-3">
                <div class="card-body">
                    <h5>Saisir et gérer les notes</h5>
                    <ol class="step-list">
                        <li>Accédez à la section "Notes"</li>
                        <li>Sélectionnez la classe et la matière</li>
                        <li>Choisissez le type d'évaluation :
                            <ul>
                                <li>Devoir</li>
                                <li>Composition</li>
                                <li>Contrôle continu</li>
                            </ul>
                        </li>
                        <li>Saisissez les notes</li>
                        <li>Ajoutez des commentaires si nécessaire</li>
                        <li>Validez la saisie</li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- Absences -->
        <div id="absences" class="feature-section">
            <h2>4. Gestion des absences</h2>
            <div class="card mb-3">
                <div class="card-body">
                    <h5>Marquer et suivre les absences</h5>
                    <ul class="step-list">
                        <li>Marquer une absence
                            <ul>
                                <li>Sélectionner la classe</li>
                                <li>Choisir la date</li>
                                <li>Indiquer le motif</li>
                                <li>Ajouter un commentaire</li>
                            </ul>
                        </li>
                        <li>Consulter l'historique des absences</li>
                        <li>Générer des rapports d'absence</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Cours -->
        <div id="cours" class="feature-section">
            <h2>5. Gestion des cours</h2>
            <div class="card mb-3">
                <div class="card-body">
                    <h5>Préparer et gérer vos cours</h5>
                    <ul class="step-list">
                        <li>Planifier les cours
                            <ul>
                                <li>Créer un plan de cours</li>
                                <li>Définir les objectifs</li>
                                <li>Préparer les supports</li>
                            </ul>
                        </li>
                        <li>Gérer les ressources
                            <ul>
                                <li>Partager des documents</li>
                                <li>Créer des exercices</li>
                                <li>Gérer les devoirs</li>
                            </ul>
                        </li>
                        <li>Suivre la progression</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Communications -->
        <div id="communications" class="feature-section">
            <h2>6. Communications</h2>
            <div class="card mb-3">
                <div class="card-body">
                    <h5>Communiquer avec les élèves et les parents</h5>
                    <ul class="step-list">
                        <li>Messages
                            <ul>
                                <li>Envoyer des messages aux élèves</li>
                                <li>Communiquer avec les parents</li>
                                <li>Partager des annonces</li>
                            </ul>
                        </li>
                        <li>Notifications
                            <ul>
                                <li>Configurer les alertes</li>
                                <li>Recevoir les rappels</li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Rapports -->
        <div id="rapports" class="feature-section">
            <h2>7. Rapports et bulletins</h2>
            <div class="card mb-3">
                <div class="card-body">
                    <h5>Générer et gérer les rapports</h5>
                    <ul class="step-list">
                        <li>Bulletins
                            <ul>
                                <li>Préparer les bulletins</li>
                                <li>Ajouter les appréciations</li>
                                <li>Valider les notes</li>
                            </ul>
                        </li>
                        <li>Rapports de progression</li>
                        <li>Statistiques de classe</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Conseils -->
        <div class="feature-section bg-light">
            <h2>Conseils pratiques</h2>
            <div class="alert alert-info">
                <h5>Points importants à retenir :</h5>
                <ul>
                    <li>Saisissez les notes régulièrement</li>
                    <li>Communiquez rapidement les absences</li>
                    <li>Maintenez à jour votre emploi du temps</li>
                    <li>Sauvegardez vos documents importants</li>
                    <li>Vérifiez régulièrement vos messages</li>
                </ul>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
