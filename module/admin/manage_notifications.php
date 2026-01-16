<?php
// Ne pas démarrer la session ici car elle est déjà démarrée dans main.php
include_once('main.php'); // Inclure main.php en premier (charge mysqlcon.php)
require_once __DIR__ . '/../../service/NotificationService.php';
require_once __DIR__ . '/../../service/AuthService.php';
<<<<<<< C:\wamp64\www\gestion\module\admin\manage_notifications.php
<<<<<<< C:\wamp64\www\gestion\module\admin\manage_notifications.php
=======
require_once __DIR__ . '/../../service/PushNotificationService.php';
>>>>>>> c:\Users\DELL\.windsurf\worktrees\gestion\gestion-a995ea30\module\admin\manage_notifications.php
=======
require_once __DIR__ . '/../../service/PushNotificationService.php';
>>>>>>> c:\Users\DELL\.windsurf\worktrees\gestion\gestion-a995ea30\module\admin\manage_notifications.php

// Vérifier si l'utilisateur est connecté et est un administrateur

// S'assurer que $link est disponible
global $link;
if ($link === null || !$link) {
    die('Erreur de connexion à la base de données. Vérifiez les variables d\'environnement Railway (MYSQL_URL, MYSQL_PUBLIC_URL, etc.) sur Render.');
}

// Vérifier et ajouter la colonne created_by si elle n'existe pas
$result = $link->query("SHOW COLUMNS FROM students LIKE 'created_by'");
if ($result->num_rows === 0) {
    $link->query("ALTER TABLE students ADD COLUMN created_by VARCHAR(50) NOT NULL DEFAULT 'admin_default'");
    // Mettre à jour les enregistrements existants
    $link->query("UPDATE students SET created_by = 'admin_default' WHERE created_by IS NULL");
}

// Récupérer le nom de l'administrateur
$stmt = $link->prepare("SELECT name FROM admin WHERE id = ?");
$stmt->bind_param("s", $_SESSION['login_id']);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$login_session = $row['name'] ?? 'Administrateur';

// Désactiver temporairement les contraintes de clé étrangère
$link->query("SET FOREIGN_KEY_CHECKS = 0");

// Uniformiser les collations des tables concernées
try {
    // Convertir toutes les tables en utf8mb4
    $link->query("ALTER TABLE admin CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $link->query("ALTER TABLE notifications CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $link->query("ALTER TABLE teachers CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $link->query("ALTER TABLE students CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $link->query("ALTER TABLE parents CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Modifier spécifiquement la colonne created_by dans notifications
    $link->query("ALTER TABLE notifications MODIFY created_by VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");
} catch (Exception $e) {
    // En cas d'erreur, enregistrer l'erreur mais continuer
    error_log("Erreur lors de la conversion des tables: " . $e->getMessage());
}

// Réactiver les contraintes de clé étrangère
$link->query("SET FOREIGN_KEY_CHECKS = 1");

// Traiter l'ajout d'une nouvelle notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $notificationService = new NotificationService($link, $_SESSION['login_id'], 'admin');
    
    // Initialiser le service de notification push
    $pushService = new PushNotificationService($link);
    $pushService->setupDatabase();
    
    // Récupérer le nom de l'admin
    $adminNameQuery = "SELECT name FROM admin WHERE id = ?";
    $adminStmt = $link->prepare($adminNameQuery);
    $adminStmt->bind_param("s", $_SESSION['login_id']);
    $adminStmt->execute();
    $adminResult = $adminStmt->get_result();
    $adminName = $adminResult->fetch_assoc()['name'] ?? 'Administrateur';
    
    switch ($_POST['action']) {
        case 'create':
            $title = $_POST['title'] ?? '';
            $message = $_POST['message'] ?? '';
            $type = $_POST['type'] ?? 'info';
            $link_url = $_POST['link'] ?? null;
            $target_type = $_POST['target_type'] ?? '';
            $target_ids = $_POST['target_ids'] ?? [];
            
            if (empty($title) || empty($message) || empty($target_type)) {
                $_SESSION['error'] = "Tous les champs obligatoires doivent être remplis";
            } else {
                // Préparer les cibles pour les notifications push
                $targets = [];
                if (!empty($target_ids)) {
                    $targets[$target_type . 's'] = $target_ids;
                } else {
                    // Si tous les utilisateurs du type sont ciblés, récupérer tous les IDs
                    $tableMap = [
                        'teacher' => 'teachers',
                        'student' => 'students', 
                        'parent' => 'parents'
                    ];
                    
                    $table = $tableMap[$target_type] ?? null;
                    if ($table) {
                        $allUsersQuery = "SELECT id FROM {$table} WHERE created_by = ?";
                        $allStmt = $link->prepare($allUsersQuery);
                        $allStmt->bind_param("s", $_SESSION['login_id']);
                        $allStmt->execute();
                        $allResult = $allStmt->get_result();
                        
                        $userIds = [];
                        while ($user = $allResult->fetch_assoc()) {
                            $userIds[] = $user['id'];
                        }
                        
                        if (!empty($userIds)) {
                            $targets[$target_type . 's'] = $userIds;
                        }
                    }
                }
                
                // Envoyer la notification dans la base de données
                $dbSuccess = false;
                if (empty($target_ids)) {
                    // Notification pour tous les utilisateurs du type spécifié
                    if ($notificationService->createForAllUsersOfType($title, $message, $target_type, $type, $link_url)) {
                        $dbSuccess = true;
                        $_SESSION['success'] = "Notification envoyée à tous les " . $target_type . "s";
                    } else {
                        $_SESSION['error'] = "Erreur lors de l'envoi de la notification";
                    }
                } else {
                    // Notification pour des utilisateurs spécifiques
                    if ($notificationService->createForMultipleUsers($title, $message, $target_ids, $target_type, $type, $link_url)) {
                        $dbSuccess = true;
                        $_SESSION['success'] = "Notification envoyée aux utilisateurs sélectionnés";
                    } else {
                        $_SESSION['error'] = "Erreur lors de l'envoi de la notification";
                    }
                }
                
                // Envoyer les notifications push si la notification DB a réussi
                if ($dbSuccess && !empty($targets)) {
                    $pushResult = $pushService->sendAdminNotification($adminName, $_SESSION['login_id'], $title, $message, $targets);
                    
                    if ($pushResult['success']) {
                        error_log("Notifications push envoyées avec succès pour la notification admin");
                    } else {
                        error_log("Échec de l'envoi des notifications push: " . ($pushResult['message'] ?? 'Erreur inconnue'));
                    }
                }
            }
            break;
            
        case 'delete':
            $notification_id = $_POST['notification_id'] ?? null;
            if ($notification_id) {
                if ($notificationService->delete($notification_id)) {
                    $_SESSION['success'] = "Notification supprimée avec succès";
                } else {
                    $_SESSION['error'] = "Erreur lors de la suppression de la notification";
                }
            }
            break;
    }
    
    header('Location: manage_notifications.php');
    exit;
}

<<<<<<< C:\wamp64\www\gestion\module\admin\manage_notifications.php
// Récupérer les utilisateurs pour le formulaire
$users = [
    'teacher' => [],
    'student' => [],
    'parent' => []
];

// Récupérer les enseignants créés par cet administrateur
$stmt = $link->prepare("SELECT id, name FROM teachers WHERE created_by = ? OR created_by = 'admin_default'");
$stmt->bind_param("s", $_SESSION['login_id']);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $users['teacher'][] = $row;
}

// Récupérer les élèves créés par cet administrateur
$stmt = $link->prepare("SELECT id, name FROM students WHERE created_by = ? OR created_by = 'admin_default'");
$stmt->bind_param("s", $_SESSION['login_id']);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $users['student'][] = $row;
}

// Récupérer les parents
$stmt = $link->prepare("SELECT id, CONCAT(fathername, ' / ', mothername) as name FROM parents");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $users['parent'][] = $row;
}

// Pagination pour les notifications existantes
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Récupérer toutes les notifications
$stmt = $link->prepare("
    SELECT n.*, 
           CASE 
               WHEN n.user_type = 'admin' THEN a.name
               WHEN n.user_type = 'teacher' THEN t.name
               WHEN n.user_type = 'student' THEN s.name
               WHEN n.user_type = 'parent' THEN CONCAT(p.fathername, ' / ', p.mothername)
           END as user_name,
           a2.name as created_by_name
    FROM notifications n
    LEFT JOIN admin a ON n.user_type = 'admin' AND n.user_id = a.id
    LEFT JOIN teachers t ON n.user_type = 'teacher' AND n.user_id = t.id
    LEFT JOIN students s ON n.user_type = 'student' AND n.user_id = s.id
    LEFT JOIN parents p ON n.user_type = 'parent' AND n.user_id = p.id
    LEFT JOIN admin a2 ON n.created_by = a2.id
    WHERE n.created_by = ? OR 
          (n.user_type = 'teacher' AND t.created_by = ?) OR 
          (n.user_type = 'student' AND s.created_by = ?)
    ORDER BY n.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("sssii", $_SESSION['login_id'], $_SESSION['login_id'], $_SESSION['login_id'], $per_page, $offset);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Compter le total des notifications pour cet administrateur
$stmt = $link->prepare("
    SELECT COUNT(*) as count 
    FROM notifications n
    LEFT JOIN teachers t ON n.user_type = 'teacher' AND n.user_id = t.id
    LEFT JOIN students s ON n.user_type = 'student' AND n.user_id = s.id
    WHERE n.created_by = ? OR 
          (n.user_type = 'teacher' AND t.created_by = ?) OR 
          (n.user_type = 'student' AND s.created_by = ?)
");
$stmt->bind_param("sss", $_SESSION['login_id'], $_SESSION['login_id'], $_SESSION['login_id']);
$stmt->execute();
$total_notifications = $stmt->get_result()->fetch_assoc()['count'];
$total_pages = ceil($total_notifications / $per_page);

// Définir le titre de la page
$page_title = "Gestion des Notifications";
?>

=======
// Récupérer toutes les notifications
$notifications = $notificationService->getAllNotifications();
?>
>>>>>>> c:\Users\DELL\.windsurf\worktrees\gestion\gestion-a995ea30\module\admin\manage_notifications.php
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Notifications</title>
<<<<<<< C:\wamp64\www\gestion\module\admin\manage_notifications.php
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
=======
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
>>>>>>> c:\Users\DELL\.windsurf\worktrees\gestion\gestion-a995ea30\module\admin\manage_notifications.php
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .notification-card {
            transition: all 0.2s ease;
        }
        .notification-card:hover {
            background-color: #f8f9fa;
        }
        .badge-info {
            background-color: #0dcaf0;
            color: #fff;
        }
        .badge-success {
            background-color: #198754;
            color: #fff;
        }
        .badge-warning {
            background-color: #ffc107;
            color: #000;
        }
        .badge-error {
            background-color: #dc3545;
            color: #fff;
        }
        .select2-container {
            width: 100% !important;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col">
                <h1 class="h3 mb-0">Gestion des Notifications</h1>
            </div>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <!-- Formulaire d'ajout de notification -->
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">Envoyer une nouvelle notification</h5>
            </div>
            <div class="card-body">
                <form action="manage_notifications.php" method="POST">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="title" class="form-label">Titre <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="title" required class="form-control">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="type" class="form-label">Type <span class="text-danger">*</span></label>
                            <select name="type" id="type" required class="form-select">
                                <option value="info">Information</option>
                                <option value="success">Succès</option>
                                <option value="warning">Avertissement</option>
                                <option value="error">Erreur</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                        <textarea name="message" id="message" rows="3" required class="form-control"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="link" class="form-label">Lien (optionnel)</label>
                        <input type="url" name="link" id="link" class="form-control" placeholder="https://...">
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="target_type" class="form-label">Type de destinataire <span class="text-danger">*</span></label>
                            <select name="target_type" id="target_type" required class="form-select">
                                <option value="">Sélectionnez un type</option>
                                <option value="teacher">Enseignants</option>
                                <option value="student">Élèves</option>
                                <option value="parent">Parents</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="target_ids" class="form-label">Destinataires spécifiques (optionnel)</label>
<<<<<<< C:\wamp64\www\gestion\module\admin\manage_notifications.php
                            <select name="target_ids[]" id="target_ids" multiple class="form-select" disabled>
                                <option value="">Sélectionnez d'abord un type de destinataire</option>
                            </select>
                            <div class="form-text">Laissez vide pour envoyer à tous les utilisateurs du type sélectionné.</div>
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Envoyer la notification
=======
                            <select name="target_ids[]" id="target_ids" multiple class="form-select" style="display: none;">
                                <!-- Les options seront chargées via AJAX -->
                            </select>
                            <small class="form-text">Laissez vide pour envoyer à tous les utilisateurs du type sélectionné</small>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2">
                        <button type="reset" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Annuler
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Envoyer
>>>>>>> c:\Users\DELL\.windsurf\worktrees\gestion\gestion-a995ea30\module\admin\manage_notifications.php
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
<<<<<<< C:\wamp64\www\gestion\module\admin\manage_notifications.php
        <!-- Liste des notifications -->
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Notifications envoyées</h5>
                <span class="badge bg-secondary"><?php echo count($notifications); ?> notification(s)</span>
            </div>
            
            <?php if (empty($notifications)): ?>
            <div class="card-body text-center py-5">
                <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                <p class="text-muted">Aucune notification envoyée pour le moment.</p>
            </div>
            <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($notifications as $notification): ?>
                <div class="list-group-item notification-card p-3">
                    <div class="row">
                        <div class="col-md-9">
                            <div class="d-flex align-items-center mb-2">
                                <h6 class="mb-0 me-2"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                <span class="badge badge-<?php echo $notification['type']; ?> me-2"><?php echo $notification['type']; ?></span>
                                <small class="text-muted">
                                    <?php echo date('d/m/Y H:i', strtotime($notification['created_at'])); ?>
                                </small>
                            </div>
                            
                            <p class="mb-2"><?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
                            
                            <?php if (!empty($notification['link'])): ?>
                            <a href="<?php echo htmlspecialchars($notification['link']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-external-link-alt me-1"></i>Voir le lien
                            </a>
                            <?php endif; ?>
                            
                            <div class="mt-2 small">
                                <span class="badge bg-light text-dark me-2">
                                    <i class="fas fa-user me-1"></i>
                                    <?php echo ucfirst($notification['user_type']); ?>: 
                                    <?php echo htmlspecialchars($notification['user_name'] ?? 'Inconnu'); ?>
                                </span>
                                
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo $notification['is_read'] ? 'Lu' : 'Non lu'; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="col-md-3 text-md-end mt-3 mt-md-0">
                            <form action="manage_notifications.php" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette notification?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                <button type="submit" class="btn btn-outline-danger">
                                    <i class="fas fa-trash-alt me-2"></i>Supprimer
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-white">
                <nav aria-label="Pagination des notifications">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Précédent">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Suivant">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
    // Données des utilisateurs pour le formulaire
    const users = <?php echo json_encode($users); ?>;
    
    $(document).ready(function() {
        // Initialiser Select2 pour une meilleure expérience de sélection multiple
        $('#target_ids').select2({
            placeholder: 'Sélectionnez les destinataires',
            allowClear: true
        });
        
        // Gérer le changement de type de destinataire
        $('#target_type').change(function() {
            const targetType = $(this).val();
            const targetIdsSelect = $('#target_ids');
            
            // Réinitialiser les options
            targetIdsSelect.empty();
            
            if (targetType) {
                // Activer le champ
                targetIdsSelect.prop('disabled', false);
                
                // Ajouter les options en fonction du type sélectionné
                if (users[targetType] && users[targetType].length > 0) {
                    users[targetType].forEach(function(user) {
                        targetIdsSelect.append(new Option(user.name, user.id, false, false));
                    });
                } else {
                    targetIdsSelect.append(new Option('Aucun utilisateur disponible', '', true, true));
                }
            } else {
                // Désactiver le champ si aucun type n'est sélectionné
                targetIdsSelect.prop('disabled', true);
                targetIdsSelect.append(new Option('Sélectionnez d\'abord un type de destinataire', '', true, true));
            }
            
            // Rafraîchir Select2
            targetIdsSelect.trigger('change');
        });
    });
=======
        <!-- Liste des notifications existantes -->
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">Notifications envoyées</h5>
            </div>
            <div class="card-body">
                <?php if (empty($notifications)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Aucune notification envoyée pour le moment</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Message</th>
                                <th>Type</th>
                                <th>Destinataires</th>
                                <th>Date d'envoi</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notifications as $notification): ?>
                            <tr class="notification-card">
                                <td>
                                    <strong><?php echo htmlspecialchars($notification['title'] ?? ''); ?></strong>
                                    <?php if (!empty($notification['link_url'])): ?>
                                    <br><a href="<?php echo htmlspecialchars($notification['link_url']); ?>" target="_blank" class="text-primary small">
                                        <i class="fas fa-external-link-alt me-1"></i>Voir le lien
                                    </a>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars(substr($notification['message'] ?? '', 0, 100)) . (strlen($notification['message'] ?? '') > 100 ? '...' : ''); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $notification['type'] ?? 'info'; ?>">
                                        <?php 
                                        $typeLabels = [
                                            'info' => 'Information',
                                            'success' => 'Succès', 
                                            'warning' => 'Avertissement',
                                            'error' => 'Erreur'
                                        ];
                                        echo $typeLabels[$notification['type']] ?? 'Information';
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php 
                                        $targetTypes = [
                                            'teacher' => 'Enseignants',
                                            'student' => 'Élèves',
                                            'parent' => 'Parents'
                                        ];
                                        echo $targetTypes[$notification['target_type']] ?? 'Tous';
                                        ?>
                                    </small>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php 
                                        $date = new DateTime($notification['created_at']);
                                        echo $date->format('d/m/Y H:i');
                                        ?>
                                    </small>
                                </td>
                                <td>
                                    <form method="POST" action="manage_notifications.php" class="d-inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette notification ?')">
                                            <i class="fas fa-trash me-1"></i>Supprimer
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialiser Select2
            $('#target_ids').select2({
                placeholder: 'Sélectionnez des destinataires spécifiques',
                allowClear: true,
                ajax: {
                    url: 'get_users.php',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term,
                            type: $('#target_type').val()
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data
                        };
                    },
                    cache: true
                }
            });

            // Mettre à jour les destinataires quand le type change
            $('#target_type').on('change', function() {
                $('#target_ids').val(null).trigger('change');
                $('#target_ids').prop('disabled', $(this).val() === '');
            });
        });
>>>>>>> c:\Users\DELL\.windsurf\worktrees\gestion\gestion-a995ea30\module\admin\manage_notifications.php
    </script>
</body>
</html>
