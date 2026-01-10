<?php
include_once('main.php');

// Get admin ID for filtering
$admin_id = $_SESSION['login_id'];

// Utiliser la connexion $link créée par main.php
global $link;
$conn = $link;

// Vérifier si la connexion a réussi
if ($conn === null || !$conn) {
    // Afficher un message d'erreur en français
    die('
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Erreur de connexion à la base de données</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
                background-color: #f5f5f5;
            }
            .error-container {
                background: white;
                padding: 2rem;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                max-width: 600px;
            }
            h1 {
                color: #d32f2f;
                margin-top: 0;
            }
            .error-details {
                background: #ffebee;
                padding: 1rem;
                border-left: 4px solid #d32f2f;
                margin: 1rem 0;
            }
            ul {
                margin: 0.5rem 0;
                padding-left: 1.5rem;
            }
            code {
                background: #f5f5f5;
                padding: 2px 6px;
                border-radius: 3px;
                font-family: monospace;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <h1>❌ Erreur de connexion à la base de données</h1>
            <div class="error-details">
                <p><strong>La connexion à la base de données a échoué.</strong></p>
                <p>Veuillez vérifier les points suivants :</p>
                <ul>
                    <li>Les variables d\'environnement de base de données sont correctement configurées</li>
                    <li>Le serveur MySQL est accessible et en cours d\'exécution</li>
                    <li>Les identifiants de connexion (hôte, utilisateur, mot de passe) sont corrects</li>
                    <li>Le firewall/autorisations réseau permettent la connexion</li>
                </ul>
                <p><strong>Variables d\'environnement requises (Railway) :</strong></p>
                <ul>
                    <li>Vérifiez que les variables Railway sont définies : <code>MYSQL_URL</code> ou <code>MYSQL_PUBLIC_URL</code> (URL complète)</li>
                    <li>Ou utilisez les variables individuelles : <code>MYSQLHOST</code>, <code>MYSQLUSER</code>, <code>MYSQLPASSWORD</code>, <code>MYSQLDATABASE</code></li>
                    <li>Consultez les logs de l\'application pour plus de détails</li>
                </ul>
            </div>
            <p><a href="../../">← Retour à l\'accueil</a></p>
        </div>
    </body>
    </html>
    ');
}

// Get admin name
$sql = "SELECT name FROM admin WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$login_session = $loged_user_name = $admin['name'];

if(!isset($login_session)){
    header("Location:../../");
    exit;
}

// Close database statement
$stmt->close();

// Contenu de la page
$content = '
<div class="container py-4">
    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="h4 mb-4">
                <i class="fas fa-users me-2 text-primary"></i>
                Gestion des Parents
            </h2>
            
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <a href="addParent.php" class="card h-100 text-decoration-none border-0 shadow-sm hover-card">
                        <div class="card-body text-center p-4">
                            <div class="mb-3">
                                <i class="fas fa-user-plus fa-3x text-primary"></i>
                            </div>
                            <h3 class="h5 card-title">Ajouter un Parent</h3>
                            <p class="card-text text-muted">Créer un nouveau compte parent</p>
                        </div>
                    </a>
                </div>

                <div class="col-md-6 col-lg-3">
                    <a href="viewParent.php" class="card h-100 text-decoration-none border-0 shadow-sm hover-card">
                        <div class="card-body text-center p-4">
                            <div class="mb-3">
                                <i class="fas fa-list fa-3x text-success"></i>
                            </div>
                            <h3 class="h5 card-title">Liste des Parents</h3>
                            <p class="card-text text-muted">Voir tous les parents</p>
                        </div>
                    </a>
                </div>

                <div class="col-md-6 col-lg-3">
                    <a href="updateParent.php" class="card h-100 text-decoration-none border-0 shadow-sm hover-card">
                        <div class="card-body text-center p-4">
                            <div class="mb-3">
                                <i class="fas fa-edit fa-3x text-warning"></i>
                            </div>
                            <h3 class="h5 card-title">Modifier un Parent</h3>
                            <p class="card-text text-muted">Mettre à jour les informations</p>
                        </div>
                    </a>
                </div>

                <div class="col-md-6 col-lg-3">
                    <a href="deleteParent.php" class="card h-100 text-decoration-none border-0 shadow-sm hover-card">
                        <div class="card-body text-center p-4">
                            <div class="mb-3">
                                <i class="fas fa-trash fa-3x text-danger"></i>
                            </div>
                            <h3 class="h5 card-title">Supprimer un Parent</h3>
                            <p class="card-text text-muted">Supprimer un compte parent</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Raccourcis supplémentaires -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h3 class="h5 mb-0">Actions rapides</h3>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <a href="assignStudents.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-user-graduate me-2"></i>Assigner des Étudiants
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="manageStudent.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-users me-2"></i>Gestion des Étudiants
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="index.php" class="btn btn-outline-dark w-100">
                                <i class="fas fa-home me-2"></i>Tableau de bord
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .hover-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .hover-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
    }
</style>
';

// Inclure le template layout
include('templates/layout.php');
?>
