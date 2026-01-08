<?php
require_once '../service/db_utils.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guide Directeur - SchoolManager</title>
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
                        <a class="nav-link" href="../login.php"><i class="fas fa-sign-in-alt"></i> Connexion</a>
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
    <a class="list-group-item list-group-item-action" href="#connexion"><i class="fas fa-sign-in-alt me-2"></i>1. Connexion</a>
    <a class="list-group-item list-group-item-action" href="#gestion-paiements"><i class="fas fa-money-bill-wave me-2"></i>2. Paiements & Salaires</a>
</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-9">
                <h1 class="mb-4 border-bottom pb-3">Guide Directeur SchoolManager</h1>
                <!-- Introduction -->
                <div id="introduction" class="feature-section">
    <h2><i class="fas fa-info-circle me-2 text-primary"></i>Introduction</h2>
    <p class="lead">Bienvenue dans le guide du directeur de SchoolManager. Le rôle du directeur est centré sur la gestion des paiements des élèves et des salaires du personnel.</p>
    <div class="alert alert-info">
        <strong>Important :</strong> Le directeur ne gère que les aspects financiers (paiements, salaires) et n’a pas accès à la gestion des classes, enseignants ou rapports pédagogiques.
    </div>
</div>
                <!-- Connexion -->
                <div id="connexion" class="feature-section">
                    <h2><i class="fas fa-sign-in-alt me-2 text-primary"></i>1. Connexion</h2>
                    <ol class="step-list">
                        <li>Accédez à la page de connexion du système.</li>
                        <li>Entrez votre identifiant et mot de passe de directeur.</li>
                        <li>Cliquez sur <strong>Connexion</strong> pour accéder à votre tableau de bord.</li>
                    </ol>
                </div>
                <!-- Gestion des paiements et salaires -->
<div id="gestion-paiements" class="feature-section">
    <h2><i class="fas fa-money-bill-wave me-2 text-primary"></i>2. Paiements & Salaires</h2>
    <ul class="step-list">
        <li>Consultez l’historique des paiements de tous les élèves.</li>
        <li>Gérez les paiements en attente ou en retard.</li>
        <li>Générez les bulletins de paiement pour chaque élève.</li>
        <li>Consultez, validez et gérez les salaires du personnel enseignant et administratif.</li>
        <li>Exportez les états de paiement et de salaire pour la comptabilité.</li>
    </ul>
</div>

            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
