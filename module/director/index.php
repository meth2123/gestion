<?php
require_once 'check_director_access.php';
require_once 'functions_stats.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - Directeur</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --sidebar-width: 250px;
            --sidebar-collapsed-width: 70px;
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --success-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --info-color: #3498db;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background-color: var(--secondary-color);
            color: white;
            transition: width 0.3s;
            z-index: 1000;
        }
        .sidebar-header {
            padding: 20px 16px 10px 16px;
            background: var(--secondary-color);
            border-bottom: 1px solid #34495e;
            text-align: center;
        }
        .sidebar-header h3 {
            margin: 0;
            font-size: 1.3rem;
            letter-spacing: 1px;
        }
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar-menu li {
            width: 100%;
        }
        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 24px;
            color: #ecf0f1;
            text-decoration: none;
            transition: background 0.2s;
            font-size: 1rem;
        }
        .sidebar-menu a.active, .sidebar-menu a:hover {
            background: var(--primary-color);
            color: white;
        }
        .sidebar-menu i {
            min-width: 22px;
            text-align: center;
        }
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 32px 24px 24px 24px;
            min-height: 100vh;
            background: #f5f5f5;
            transition: margin-left 0.3s;
        }
        @media (max-width: 768px) {
            .sidebar {
                width: var(--sidebar-collapsed-width);
            }
            .main-content {
                margin-left: var(--sidebar-collapsed-width);
                padding: 16px 4vw;
            }
            .sidebar-header h3, .sidebar-menu span {
                display: none;
            }
        }
        .menu-toggle {
            position: fixed;
            top: 10px;
            left: 10px;
            z-index: 1100;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 6px 10px;
            font-size: 1.3rem;
            display: none;
        }
        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }
        }
    </style>
</head>
<body>
    <!-- Menu toggle button for mobile -->
    <button class="menu-toggle" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>
    <!-- Sidebar Directeur -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>Directeur</h3>
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="index.php" class="active"><i class="fas fa-tachometer-alt"></i> <span>Tableau de bord</span></a>
            </li>
            <li>
                <a href="payment.php"><i class="fas fa-credit-card"></i> <span>Gestion des Paiements</span></a>
            </li>
            <li>
                <a href="salary.php"><i class="fas fa-wallet"></i> <span>Gestion des Salaires</span></a>
            </li>
            <li>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Déconnexion</span></a>
            </li>
        </ul>
    </div>
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <h2 class="mb-4">Tableau de Bord - Directeur</h2>
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-2x text-primary mb-2"></i>
                        <h5 class="card-title">Élèves</h5>
                        <p class="fw-bold mb-0"><?php echo get_total_students(); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body text-center">
                        <i class="fas fa-chalkboard-teacher fa-2x text-info mb-2"></i>
                        <h5 class="card-title">Enseignants</h5>
                        <p class="fw-bold mb-0"><?php echo get_total_teachers(); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body text-center">
                        <i class="fas fa-school fa-2x text-secondary mb-2"></i>
                        <h5 class="card-title">Classes</h5>
                        <p class="fw-bold mb-0"><?php echo get_total_classes(); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body text-center">
                        <i class="fas fa-user-clock fa-2x text-danger mb-2"></i>
                        <h5 class="card-title">Impayés ce mois</h5>
                        <p class="fw-bold mb-0"><?php echo get_unpaid_payments(); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body text-center">
                        <i class="fas fa-coins fa-2x text-success mb-2"></i>
                        <h5 class="card-title">Payé ce mois</h5>
                        <p class="fw-bold mb-0"><?php echo get_total_payments_current_month(); ?> FCFA</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body text-center">
                        <i class="fas fa-file-invoice-dollar fa-2x text-warning mb-2"></i>
                        <h5 class="card-title">Attendu ce mois</h5>
                        <p class="fw-bold mb-0"><?php echo get_total_expected_payments_current_month(); ?> FCFA</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body text-center">
                        <i class="fas fa-exclamation-circle fa-2x text-danger mb-2"></i>
                        <h5 class="card-title">Impayé ce mois</h5>
                        <p class="fw-bold mb-0"><?php echo get_total_unpaid_current_month(); ?> FCFA</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body text-center">
                        <i class="fas fa-calendar-alt fa-2x text-info mb-2"></i>
                        <h5 class="card-title">Attendu année</h5>
                        <p class="fw-bold mb-0"><?php echo get_total_expected_payments_year(); ?> FCFA</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body text-center">
                        <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                        <h5 class="card-title">Impayé année</h5>
                        <p class="fw-bold mb-0"><?php echo get_total_unpaid_year(); ?> FCFA</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h3 class="card-title"><i class="fas fa-credit-card text-success me-2"></i>Statistiques des Paiements</h3>
                        <p class="mb-2">Total des paiements : <span class="fw-bold text-success"><?php echo get_total_payments(); ?></span> FCFA</p>
                        <p>Paiements en retard : <span class="fw-bold text-danger"><?php echo get_unpaid_payments(); ?></span></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h3 class="card-title"><i class="fas fa-wallet text-primary me-2"></i>Statistiques des Salaires</h3>
                        <p class="mb-2">Total des salaires : <span class="fw-bold text-primary"><?php echo get_total_salaries_current_month(); ?></span> FCFA</p>
                        <p>Salaires impayés : <span class="fw-bold text-warning"><?php echo get_unpaid_salaries_current_month(); ?></span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script>
        // Sidebar toggle pour mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            var sidebar = document.getElementById('sidebar');
            var mainContent = document.getElementById('mainContent');
            if (sidebar.style.width === '70px') {
                sidebar.style.width = '250px';
                mainContent.style.marginLeft = '250px';
            } else {
                sidebar.style.width = '70px';
                mainContent.style.marginLeft = '70px';
            }
        });
    </script>
</body>
</html>
