<?php
$page_title = 'Documentation SchoolManager';
$page_description = 'Documentation et guides d�utilisation de SchoolManager.';
$robots = 'index, follow';
$include_google_verification = false;
// Page de documentation - ne nécessite pas de connexion à la base de données
// Charger db_utils.php seulement si nécessaire (lazy loading)
// Cela évite les timeouts de connexion qui ralentissent l'affichage
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Ne pas charger db_utils.php ici car la documentation n'en a pas besoin
// Cela évite les attentes de connexion à la base de données
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentation SchoolManager</title>
    <?php require_once __DIR__ . '/../seo.php'; ?>
    <!-- Favicon -->
    <link rel="icon" type="image/jpeg" href="../source/logo.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="../source/logo.jpg">
    <link rel="apple-touch-icon" href="../source/logo.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .guide-card {
            transition: transform 0.2s;
            height: 100%;
        }
        .guide-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .guide-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #0d6efd;
        }
        .feature-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</head>
<body>
    <div class="container py-5">
        <h1 class="text-center mb-5">Documentation SchoolManager</h1>
        
        <!-- Introduction -->
        <div class="feature-section mb-5">
            <h2 class="text-center mb-4">Bienvenue dans la documentation</h2>
            <p class="lead text-center">
                Cette documentation vous guide dans l'utilisation de SchoolManager, notre système de gestion scolaire.
                Choisissez votre profil ci-dessous pour accéder au guide correspondant.
            </p>
        </div>

        <!-- Guides -->
        <div class="row g-4">
            <!-- Guide Administrateur -->
            <div class="col-md-6 col-lg-3">
                <div class="card guide-card">
                    <div class="card-body text-center">
                        <i class="bi bi-shield-lock guide-icon"></i>
                        <h3 class="card-title h5">Administrateur</h3>
                        <p class="card-text">Guide complet pour la gestion administrative de l'école.</p>
                        <a href="admin_guide.php" class="btn btn-primary">Accéder au guide</a>
                    </div>
                </div>
            </div>

            <!-- Guide Enseignant -->
            <div class="col-md-6 col-lg-3">
                <div class="card guide-card">
                    <div class="card-body text-center">
                        <i class="bi bi-person-workspace guide-icon"></i>
                        <h3 class="card-title h5">Enseignant</h3>
                        <p class="card-text">Guide pour la gestion des cours, notes et communications.</p>
                        <a href="teacher_guide.php" class="btn btn-primary">Accéder au guide</a>
                    </div>
                </div>
            </div>

            <!-- Guide Élève -->
            <div class="col-md-6 col-lg-3">
                <div class="card guide-card">
                    <div class="card-body text-center">
                        <i class="bi bi-mortarboard guide-icon"></i>
                        <h3 class="card-title h5">Élève</h3>
                        <p class="card-text">Guide pour accéder aux cours, notes et ressources.</p>
                        <a href="student_guide.php" class="btn btn-primary">Accéder au guide</a>
                    </div>
                </div>
            </div>

            <!-- Guide Directeur -->
            <div class="col-md-6 col-lg-3">
                <div class="card guide-card">
                    <div class="card-body text-center">
                        <i class="bi bi-briefcase guide-icon"></i>
                        <h3 class="card-title h5">Directeur</h3>
                        <p class="card-text">Guide pour la supervision, la gestion globale et les rapports.</p>
                        <a href="director_guide.php" class="btn btn-primary">Accéder au guide</a>
                    </div>
                </div>
            </div>

            <!-- Guide Parent -->
            <div class="col-md-6 col-lg-3">
                <div class="card guide-card">
                    <div class="card-body text-center">
                        <i class="bi bi-people guide-icon"></i>
                        <h3 class="card-title h5">Parent</h3>
                        <p class="card-text">Guide pour suivre la scolarité de votre enfant.</p>
                        <a href="parent_guide.php" class="btn btn-primary">Accéder au guide</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fonctionnalités principales -->
        <div class="feature-section mt-5">
            <h2 class="text-center mb-4">Fonctionnalités principales</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-calendar-check text-primary me-3" style="font-size: 1.5rem;"></i>
                        <h3 class="h5 mb-0">Gestion des emplois du temps</h3>
                    </div>
                    <p>Planification et suivi des cours, gestion des salles et des ressources.</p>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-journal-text text-primary me-3" style="font-size: 1.5rem;"></i>
                        <h3 class="h5 mb-0">Suivi des notes</h3>
                    </div>
                    <p>Gestion des évaluations, bulletins et suivi de la progression.</p>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-cash-coin text-primary me-3" style="font-size: 1.5rem;"></i>
                        <h3 class="h5 mb-0">Gestion des paiements</h3>
                    </div>
                    <p>Suivi des frais de scolarité, paiements en ligne et facturation.</p>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-chat-dots text-primary me-3" style="font-size: 1.5rem;"></i>
                        <h3 class="h5 mb-0">Communication</h3>
                    </div>
                    <p>Messagerie interne, annonces et notifications en temps réel.</p>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-file-earmark-text text-primary me-3" style="font-size: 1.5rem;"></i>
                        <h3 class="h5 mb-0">Documents</h3>
                    </div>
                    <p>Gestion des documents administratifs et ressources pédagogiques.</p>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-graph-up text-primary me-3" style="font-size: 1.5rem;"></i>
                        <h3 class="h5 mb-0">Rapports et statistiques</h3>
                    </div>
                    <p>Tableaux de bord et analyses pour le suivi des performances.</p>
                </div>
            </div>
        </div>

        <!-- Support -->
        <div class="feature-section mt-5">
            <h2 class="text-center mb-4">Besoin d'aide ?</h2>
            <div class="row justify-content-center">
                <div class="col-md-8 text-center">
                    <p class="lead mb-4">
                        Si vous ne trouvez pas l'information recherchée dans la documentation,
                        n'hésitez pas à contacter notre support.
                    </p>
                    <div class="d-flex justify-content-center gap-3">
                        <a href="mailto:methndiaye43@gmail.com" class="btn btn-outline-primary">
                            <i class="bi bi-envelope me-2"></i>Contacter le support
                        </a>
                        <a href="../module/help_center.php" class="btn btn-outline-primary">
                            <i class="bi bi-question-circle me-2"></i>Centre d'aide
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
