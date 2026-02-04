<?php
$page_title = 'Guide Administrateur - SchoolManager';
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
    <title>Guide Administrateur - SchoolManager</title>
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
            content: "\2713";
            position: absolute;
            left: 0;
            color: #0d6efd;
        }
        .alert-tip {
            background-color: #f8f9fa;
            border-left: 4px solid #0d6efd;
            padding: 15px;
            margin: 15px 0;
        }
        .screenshot {
            border: 1px solid #dee2e6;
            border-radius: 4px;
            max-width: 100%;
            height: auto;
            margin: 15px 0;
        }
        code {
            background-color: #f8f9fa;
            padding: 2px 4px;
            border-radius: 4px;
            color: #d63384;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div class="container">
            <a class="navbar-brand" href="#">SchoolManager</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../login.php?"><i class="fas fa-sign-in-alt"></i> Connexion</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php"><i class="fas fa-home"></i> Accueil</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-3">
        <div class="row">
            <div class="col-lg-3 mb-4">
                <div class="sticky-top" style="top: 20px;">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Table des matières</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <a class="list-group-item list-group-item-action" href="#introduction"><i class="fas fa-info-circle me-2"></i>Introduction</a>
                                <a class="list-group-item list-group-item-action" href="#connexion"><i class="fas fa-sign-in-alt me-2"></i>1. Connexion au système</a>
                                <a class="list-group-item list-group-item-action" href="#gestion-personnel"><i class="fas fa-user-tie me-2"></i>2. Gestion du personnel</a>
                                <a class="list-group-item list-group-item-action" href="#gestion-eleves"><i class="fas fa-user-graduate me-2"></i>3. Gestion des élèves</a>
                                <a class="list-group-item list-group-item-action" href="#gestion-classes"><i class="fas fa-chalkboard me-2"></i>4. Gestion des classes</a>
                                <a class="list-group-item list-group-item-action" href="#gestion-notes"><i class="fas fa-clipboard-check me-2"></i>5. Gestion des notes</a>
                                <a class="list-group-item list-group-item-action" href="#bulletins"><i class="fas fa-file-alt me-2"></i>6. Bulletins scolaires</a>
                                <a class="list-group-item list-group-item-action" href="#paiements"><i class="fas fa-money-bill-wave me-2"></i>7. Gestion des paiements</a>
                                <a class="list-group-item list-group-item-action" href="#notifications"><i class="fas fa-bell me-2"></i>8. Notifications</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-9">
                <h1 class="mb-4 border-bottom pb-3">Guide Administrateur SchoolManager</h1>
                
                <!-- Introduction -->
                <div id="introduction" class="feature-section">
                    <h2><i class="fas fa-info-circle me-2 text-primary"></i>Introduction</h2>
                    <p class="lead">Bienvenue dans le guide administrateur de SchoolManager, votre solution complète pour la gestion scolaire.</p>
                    <p>Ce guide vous aidera à comprendre et à utiliser efficacement toutes les fonctionnalités du système pour gérer votre établissement scolaire. Vous y trouverez des instructions détaillées sur la gestion des élèves, du personnel, des classes, des notes et des paiements.</p>
                    <div class="alert alert-info">
                        <strong>Note importante :</strong> Chaque administrateur ne voit que les données qu'il a créées ou qui lui sont assignées. Cette séparation garantit la confidentialité et la sécurité des informations.
                    </div>
                </div>
                
                <!-- Connexion au système -->
                <div id="connexion" class="feature-section">
                    <h2><i class="fas fa-sign-in-alt me-2 text-primary"></i>1. Connexion au système</h2>
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5>Procédure de connexion</h5>
                            <ol class="step-list">
                                <li>Accédez à la page de connexion via <code>http://gestion-rlhq.onrender.com/login.php</code></li>
                                <li>Entrez votre identifiant administrateur</li>
                                <li>Entrez votre mot de passe</li>
                                <li>Cliquez sur le bouton "Connexion"</li>
                            </ol>
                            <div class="alert-tip">
                                <i class="fas fa-lightbulb me-2"></i><strong>Astuce :</strong> Si vous avez oublié votre mot de passe, utilisez l'option "Mot de passe oublié" pour le réinitialiser via votre email.
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <h5>Tableau de bord administrateur</h5>
                            <p>Après la connexion, vous accédez au tableau de bord qui présente :</p>
                            <ul>
                                <li><strong>Statistiques générales :</strong> Nombre d'élèves, enseignants, classes</li>
                                <li><strong>Actions rapides :</strong> Accès direct aux fonctions les plus utilisées</li>
                                <li><strong>Notifications récentes :</strong> Alertes et informations importantes</li>
                                <li><strong>Calendrier :</strong> Événements et échéances à venir</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Gestion du personnel -->
                <div id="gestion-personnel" class="feature-section">
                    <h2><i class="fas fa-user-tie me-2 text-primary"></i>2. Gestion du personnel</h2>
                    
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5>Ajouter un membre du personnel</h5>
                            <ol class="step-list">
                                <li>Dans le menu de gauche, cliquez sur "Personnel" puis "Ajouter un membre"</li>
                                <li>Dans la page <code>addStaff.php</code>, remplissez le formulaire avec les informations du membre :
                                    <ul>
                                        <li>Nom complet</li>
                                        <li>Informations de contact (téléphone, email)</li>
                                        <li>Poste/fonction</li>
                                        <li>Qualifications</li>
                                        <li>Date d'embauche</li>
                                    </ul>
                                </li>
                                <li>Cliquez sur "Enregistrer" pour créer le compte</li>
                                <li>Un identifiant unique sera automatiquement généré pour ce membre</li>
                            </ol>
                            <div class="alert-tip">
                                <i class="fas fa-lightbulb me-2"></i><strong>Astuce :</strong> Vous pouvez importer plusieurs membres du personnel à la fois en utilisant la fonction d'importation CSV.
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5>Gérer les membres du personnel</h5>
                            <p>Pour gérer les membres existants :</p>
                            <ol class="step-list">
                                <li>Accédez à "Personnel" puis "Gérer le personnel" (<code>manageStaff.php</code>)</li>
                                <li>Vous verrez la liste de tous les membres du personnel que vous avez ajoutés</li>
                                <li>Utilisez les options pour :
                                    <ul>
                                        <li><i class="fas fa-edit text-primary"></i> Modifier les informations d'un membre</li>
                                        <li><i class="fas fa-trash text-danger"></i> Supprimer un membre (avec confirmation)</li>
                                        <li><i class="fas fa-eye text-info"></i> Voir les détails complets</li>
                                    </ul>
                                </li>
                            </ol>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <h5>Assigner des classes aux enseignants</h5>
                            <ol class="step-list">
                                <li>Accédez à "Personnel" puis "Assigner des classes" (<code>assignClassTeacher.php</code>)</li>
                                <li>Sélectionnez l'enseignant dans la liste déroulante</li>
                                <li>Sélectionnez la classe à assigner</li>
                                <li>Cliquez sur "Assigner"</li>
                            </ol>
                            <div class="alert alert-warning">
                                <strong>Important :</strong> Un enseignant doit être assigné à une classe avant de pouvoir saisir des notes pour cette classe.
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Gestion des élèves -->
                <div id="gestion-eleves" class="feature-section">
                    <h2><i class="fas fa-user-graduate me-2 text-primary"></i>3. Gestion des élèves</h2>
                    
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5>Ajouter un nouvel élève</h5>
                            <ol class="step-list">
                                <li>Dans le menu de gauche, cliquez sur "Élèves" puis "Ajouter un élève"</li>
                                <li>Dans la page <code>addStudent.php</code>, remplissez le formulaire avec les informations de l'élève :
                                    <ul>
                                        <li>Nom complet</li>
                                        <li>Date de naissance</li>
                                        <li>Genre</li>
                                        <li>Adresse</li>
                                        <li>Informations des parents/tuteurs</li>
                                        <li>Contact d'urgence</li>
                                    </ul>
                                </li>
                                <li>Cliquez sur "Enregistrer" pour créer le dossier de l'élève</li>
                                <li>Un identifiant unique sera automatiquement généré pour cet élève</li>
                            </ol>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5>Assigner des élèves aux classes</h5>
                            <ol class="step-list">
                                <li>Accédez à "Classes" puis "Assigner des élèves" (<code>assignStudents.php</code>)</li>
                                <li>Sélectionnez la classe dans la liste déroulante</li>
                                <li>Cochez les élèves à assigner à cette classe</li>
                                <li>Cliquez sur "Assigner"</li>
                            </ol>
                            <div class="alert-tip">
                                <i class="fas fa-lightbulb me-2"></i><strong>Astuce :</strong> Vous pouvez filtrer la liste des élèves par nom ou par classe précédente pour faciliter l'assignation.
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <h5>Gérer les élèves existants</h5>
                            <p>Pour gérer les élèves existants :</p>
                            <ol class="step-list">
                                <li>Accédez à "Élèves" puis "Gérer les élèves"</li>
                                <li>Vous verrez la liste de tous les élèves que vous avez ajoutés</li>
                                <li>Utilisez les options pour :
                                    <ul>
                                        <li><i class="fas fa-edit text-primary"></i> Modifier les informations d'un élève</li>
                                        <li><i class="fas fa-trash text-danger"></i> Supprimer un élève (avec confirmation)</li>
                                        <li><i class="fas fa-eye text-info"></i> Voir les détails complets</li>
                                        <li><i class="fas fa-file-alt text-success"></i> Voir le dossier scolaire</li>
                                    </ul>
                                </li>
                            </ol>
                        </div>
                    </div>
                </div>
                
                <!-- Gestion des classes -->
                <div id="gestion-classes" class="feature-section">
                    <h2><i class="fas fa-chalkboard me-2 text-primary"></i>4. Gestion des classes</h2>
                    
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5>Créer une nouvelle classe</h5>
                            <ol class="step-list">
                                <li>Accédez à "Classes" puis "Ajouter une classe"</li>
                                <li>Remplissez le formulaire avec les informations de la classe :
                                    <ul>
                                        <li>Nom de la classe</li>
                                        <li>Niveau d'études</li>
                                        <li>Capacité maximale</li>
                                        <li>Année scolaire</li>
                                    </ul>
                                </li>
                                <li>Cliquez sur "Créer" pour ajouter la classe</li>
                            </ol>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <h5>Gérer les classes existantes</h5>
                            <ol class="step-list">
                                <li>Accédez à "Classes" puis "Gérer les classes"</li>
                                <li>Vous verrez la liste de toutes les classes que vous avez créées</li>
                                <li>Pour chaque classe, vous pouvez :
                                    <ul>
                                        <li>Modifier les détails de la classe</li>
                                        <li>Voir la liste des élèves inscrits</li>
                                        <li>Voir l'emploi du temps</li>
                                        <li>Supprimer la classe (si elle est vide)</li>
                                    </ul>
                                </li>
                            </ol>
                            <div class="alert alert-warning">
                                <strong>Attention :</strong> La suppression d'une classe n'est possible que si aucun élève n'y est assigné.
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Gestion des notes -->
                <div id="gestion-notes" class="feature-section">
                    <h2><i class="fas fa-clipboard-check me-2 text-primary"></i>5. Gestion des notes</h2>
                    
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5>Saisir les notes des élèves</h5>
                            <ol class="step-list">
                                <li>Accédez à "Notes" puis "Gérer les notes" (<code>manageGrades.php</code>)</li>
                                <li>Sélectionnez la classe dans la liste déroulante</li>
                                <li>Sélectionnez la matière</li>
                                <li>Sélectionnez la période (trimestre/semestre)</li>
                                <li>Pour chaque élève, saisissez :
                                    <ul>
                                        <li>La note obtenue (sur 20)</li>
                                        <li>Éventuellement, un commentaire</li>
                                    </ul>
                                </li>
                                <li>Cliquez sur "Enregistrer les notes"</li>
                            </ol>
                            <div class="alert-tip">
                                <i class="fas fa-lightbulb me-2"></i><strong>Astuce :</strong> Vous pouvez importer des notes en masse via un fichier CSV en utilisant l'option "Importer des notes".
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <h5>Modifier ou supprimer des notes</h5>
                            <ol class="step-list">
                                <li>Accédez à "Notes" puis "Gérer les notes"</li>
                                <li>Sélectionnez la classe, la matière et la période concernées</li>
                                <li>Les notes existantes s'afficheront dans le tableau</li>
                                <li>Modifiez les valeurs selon vos besoins</li>
                                <li>Cliquez sur "Mettre à jour les notes" pour sauvegarder vos modifications</li>
                            </ol>
                            <div class="alert alert-warning">
                                <strong>Important :</strong> Une fois les bulletins générés, les modifications de notes nécessiteront une régénération des bulletins concernés.
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Bulletins scolaires -->
                <div id="bulletins" class="feature-section">
                    <h2><i class="fas fa-file-alt me-2 text-primary"></i>6. Bulletins scolaires</h2>
                    
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5>Générer les bulletins</h5>
                            <ol class="step-list">
                                <li>Accédez à "Bulletins" puis "Gérer les bulletins" (<code>manageBulletins.php</code>)</li>
                                <li>Sélectionnez la classe dans la liste déroulante</li>
                                <li>Sélectionnez la période (trimestre/semestre)</li>
                                <li>Cliquez sur "Afficher" pour voir la liste des élèves</li>
                                <li>Pour chaque élève, vous pouvez :
                                    <ul>
                                        <li>Voir le bulletin en ligne</li>
                                        <li>Générer le bulletin en PDF</li>
                                    </ul>
                                </li>
                                <li>Pour générer tous les bulletins d'une classe, utilisez l'option "Génération par lot"</li>
                            </ol>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <h5>Télécharger et imprimer les bulletins</h5>
                            <ol class="step-list">
                                <li>Après avoir généré un bulletin, cliquez sur l'icône PDF pour le télécharger</li>
                                <li>Le bulletin s'ouvre dans un nouvel onglet au format PDF</li>
                                <li>Utilisez les options de votre navigateur pour imprimer ou sauvegarder le document</li>
                                <li>Pour télécharger plusieurs bulletins à la fois, utilisez l'option "Télécharger tous"</li>
                            </ol>
                            <div class="alert-tip">
                                <i class="fas fa-lightbulb me-2"></i><strong>Astuce :</strong> Les bulletins sont automatiquement sauvegardés dans le système et peuvent être consultés à tout moment.
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Gestion des paiements -->
                <div id="paiements" class="feature-section">
                    <h2><i class="fas fa-money-bill-wave me-2 text-primary"></i>7. Gestion des paiements</h2>
                    
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5>Enregistrer un paiement</h5>
                            <ol class="step-list">
                                <li>Accédez à "Finances" puis "Paiements" (<code>payment.php</code>)</li>
                                <li>Cliquez sur "Ajouter un paiement"</li>
                                <li>Sélectionnez l'élève concerné</li>
                                <li>Sélectionnez le type de paiement (frais de scolarité, cantine, etc.)</li>
                                <li>Entrez le montant payé</li>
                                <li>Sélectionnez la méthode de paiement (espèces, chèque, virement)</li>
                                <li>Ajoutez une référence ou un commentaire si nécessaire</li>
                                <li>Cliquez sur "Enregistrer le paiement"</li>
                            </ol>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5>Configurer les montants des paiements</h5>
                            <ol class="step-list">
                                <li>Dans la page "Paiements", accédez à la section "Configuration des montants"</li>
                                <li>Sélectionnez la classe concernée</li>
                                <li>Définissez les montants pour chaque type de frais</li>
                                <li>Cliquez sur "Enregistrer les montants"</li>
                            </ol>
                            <div class="alert-tip">
                                <i class="fas fa-lightbulb me-2"></i><strong>Astuce :</strong> Vous pouvez définir des montants différents pour chaque classe ou niveau d'études.
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <h5>Consulter l'historique des paiements</h5>
                            <ol class="step-list">
                                <li>Dans la page "Paiements", faites défiler jusqu'à la section "Historique des paiements"</li>
                                <li>Utilisez les filtres pour affiner votre recherche :
                                    <ul>
                                        <li>Par élève</li>
                                        <li>Par classe</li>
                                        <li>Par période</li>
                                        <li>Par type de paiement</li>
                                    </ul>
                                </li>
                                <li>Cliquez sur "Filtrer" pour afficher les résultats</li>
                                <li>Vous pouvez exporter les données au format CSV ou PDF pour vos rapports</li>
                            </ol>
                        </div>
                    </div>
                </div>
                
                <!-- Notifications -->
                <div id="notifications" class="feature-section">
                    <h2><i class="fas fa-bell me-2 text-primary"></i>8. Notifications</h2>
                    
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5>Envoyer des notifications</h5>
                            <ol class="step-list">
                                <li>Accédez à "Communication" puis "Gérer les notifications" (<code>manage_notifications.php</code>)</li>
                                <li>Cliquez sur "Nouvelle notification"</li>
                                <li>Sélectionnez le type de destinataire (enseignant, élève, classe)</li>
                                <li>Sélectionnez les destinataires spécifiques</li>
                                <li>Rédigez le titre et le contenu de la notification</li>
                                <li>Sélectionnez le niveau d'importance (normal, important, urgent)</li>
                                <li>Cliquez sur "Envoyer la notification"</li>
                            </ol>
                            <div class="alert-tip">
                                <i class="fas fa-lightbulb me-2"></i><strong>Astuce :</strong> Vous ne pouvez envoyer des notifications qu'aux utilisateurs que vous avez créés ou qui sont dans vos classes.
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <h5>Gérer les notifications existantes</h5>
                            <ol class="step-list">
                                <li>Dans la page "Gérer les notifications", vous verrez la liste de toutes les notifications que vous avez envoyées</li>
                                <li>Pour chaque notification, vous pouvez :
                                    <ul>
                                        <li>Voir les détails complets</li>
                                        <li>Voir la liste des destinataires</li>
                                        <li>Vérifier qui a lu la notification</li>
                                        <li>Supprimer la notification</li>
                                    </ul>
                                </li>
                            </ol>
                            <div class="alert alert-info">
                                <strong>Information :</strong> Les notifications sont automatiquement archivées après 30 jours.
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Conseils et bonnes pratiques -->
                <div class="feature-section bg-light">
                    <h2><i class="fas fa-star me-2 text-warning"></i>Conseils et bonnes pratiques</h2>
                    <div class="alert alert-info">
                        <h5>Points importants à retenir :</h5>
                        <ul>
                            <li>Effectuez régulièrement des sauvegardes de vos données</li>
                            <li>Vérifiez quotidiennement les paiements en attente</li>
                            <li>Maintenez à jour les informations des élèves et du personnel</li>
                            <li>Générez les bulletins seulement après avoir validé toutes les notes</li>
                            <li>Utilisez les filtres pour retrouver rapidement les informations dont vous avez besoin</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>SchoolManager</h5>
                    <p>Système de gestion scolaire complet</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p>&copy; 2025 SchoolManager. Tous droits réservés.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
