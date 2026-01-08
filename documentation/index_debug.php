<?php
// Version de debug avec gestion d'erreurs améliorée
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<!-- Début du chargement -->\n";

try {
    echo "<!-- Tentative de chargement de db_utils.php -->\n";
    $db_utils_path = __DIR__ . '/../service/db_utils.php';
    
    if (!file_exists($db_utils_path)) {
        throw new Exception("Fichier db_utils.php non trouvé: $db_utils_path");
    }
    
    require_once $db_utils_path;
    echo "<!-- db_utils.php chargé avec succès -->\n";
    
    // Vérifier la connexion à la base de données
    global $link;
    if (!$link) {
        throw new Exception("Connexion à la base de données échouée");
    }
    
    echo "<!-- Connexion à la base de données OK -->\n";
    
} catch (Exception $e) {
    echo "<!DOCTYPE html><html><head><title>Erreur</title></head><body>";
    echo "<h1>Erreur lors du chargement</h1>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Fichier:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Ligne:</strong> " . $e->getLine() . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</body></html>";
    exit;
}

// Si on arrive ici, tout est OK, charger le reste
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentation SchoolManager</title>
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

