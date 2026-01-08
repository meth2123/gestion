<?php
include_once('main.php');
include_once('includes/auth_check.php');
include_once('../../service/mysqlcon.php');
require_once('../../service/db_utils.php');

// L'ID de l'administrateur est déjà défini dans auth_check.php
$admin_id = $_SESSION['login_id'];

// Initialiser les variables
$success_message = '';
$error_message = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['message_id'])) {
        $message_id = $_POST['message_id'];
        $action = $_POST['action'];
        
        // Vérifier que le message existe
        $conn = getDbConnection();
        $check_sql = "SELECT id FROM help_messages WHERE id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $message_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Effectuer l'action demandée
            switch ($action) {
                case 'mark_in_progress':
                    $update_sql = "UPDATE help_messages SET status = 'in_progress' WHERE id = ?";
                    break;
                case 'mark_resolved':
                    $update_sql = "UPDATE help_messages SET status = 'resolved' WHERE id = ?";
                    break;
                case 'delete':
                    $update_sql = "DELETE FROM help_messages WHERE id = ?";
                    break;
                default:
                    $error_message = "Action non reconnue.";
                    break;
            }
            
            if (!empty($update_sql)) {
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("i", $message_id);
                
                if ($update_stmt->execute()) {
                    $success_message = "L'action a été effectuée avec succès.";
                } else {
                    $error_message = "Une erreur est survenue lors de l'exécution de l'action.";
                }
                
                $update_stmt->close();
            }
        } else {
            $error_message = "Le message demandé n'existe pas.";
        }
        
        $check_stmt->close();
        $conn->close();
    }
}

// Récupérer les messages
$conn = getDbConnection();

// Créer la table help_messages si elle n'existe pas
$sql_create_table = "CREATE TABLE IF NOT EXISTS help_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    user_type VARCHAR(20) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('new', 'in_progress', 'resolved') DEFAULT 'new'
)";

if ($conn->query($sql_create_table) === TRUE) {
    // Récupérer les messages
    $sql = "SELECT * FROM help_messages ORDER BY 
            CASE 
                WHEN status = 'new' THEN 1
                WHEN status = 'in_progress' THEN 2
                WHEN status = 'resolved' THEN 3
            END, 
            created_at DESC";
    
    $result = $conn->query($sql);
} else {
    $error_message = "Une erreur est survenue lors de la création de la table.";
}


?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Messages d'Aide - Administration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .message-card {
            transition: transform 0.2s;
            margin-bottom: 20px;
        }
        .message-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .status-badge {
            font-size: 0.8rem;
        }
        .message-content {
            white-space: pre-line;
            max-height: 200px;
            overflow-y: auto;
        }
        .message-actions {
            display: flex;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include('includes/sidebar.php'); ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-headset me-2"></i>Gestion des Messages d'Aide</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="../help_center.php" class="btn btn-sm btn-outline-secondary" target="_blank">
                            <i class="fas fa-external-link-alt me-2"></i>Voir le Centre d'Aide
                        </a>
                    </div>
                </div>
                
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Statistiques</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php
                                    // Compter les messages par statut
                                    $new_count = 0;
                                    $in_progress_count = 0;
                                    $resolved_count = 0;
                                    
                                    if (isset($result) && $result->num_rows > 0) {
                                        $total_count = $result->num_rows;
                                        $result_copy = $result;
                                        
                                        while ($row = $result_copy->fetch_assoc()) {
                                            switch ($row['status']) {
                                                case 'new':
                                                    $new_count++;
                                                    break;
                                                case 'in_progress':
                                                    $in_progress_count++;
                                                    break;
                                                case 'resolved':
                                                    $resolved_count++;
                                                    break;
                                            }
                                        }
                                        
                                        // Réinitialiser le pointeur du résultat
                                        $result->data_seek(0);
                                    } else {
                                        $total_count = 0;
                                    }
                                    ?>
                                    <div class="col-md-3">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0">
                                                <div class="bg-primary text-white p-3 rounded">
                                                    <i class="fas fa-inbox fa-2x"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h6 class="mb-0">Total</h6>
                                                <h4 class="mb-0"><?php echo $total_count; ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0">
                                                <div class="bg-danger text-white p-3 rounded">
                                                    <i class="fas fa-exclamation-circle fa-2x"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h6 class="mb-0">Nouveaux</h6>
                                                <h4 class="mb-0"><?php echo $new_count; ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0">
                                                <div class="bg-warning text-white p-3 rounded">
                                                    <i class="fas fa-clock fa-2x"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h6 class="mb-0">En cours</h6>
                                                <h4 class="mb-0"><?php echo $in_progress_count; ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0">
                                                <div class="bg-success text-white p-3 rounded">
                                                    <i class="fas fa-check-circle fa-2x"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h6 class="mb-0">Résolus</h6>
                                                <h4 class="mb-0"><?php echo $resolved_count; ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <h3 class="mb-4">Messages reçus</h3>
                        
                        <?php if (isset($result) && $result->num_rows > 0): ?>
                            <?php while ($message = $result->fetch_assoc()): ?>
                                <div class="card message-card">
                                    <div class="card-header message-header">
                                        <div>
                                            <h5 class="mb-0"><?php echo htmlspecialchars($message['subject']); ?></h5>
                                            <small class="text-muted">
                                                De: <?php echo htmlspecialchars($message['name']); ?> 
                                                (<?php echo htmlspecialchars($message['email']); ?>) - 
                                                Type: <?php echo htmlspecialchars($message['user_type']); ?>
                                            </small>
                                        </div>
                                        <div>
                                            <?php
                                            $status_class = '';
                                            $status_text = '';
                                            
                                            switch ($message['status']) {
                                                case 'new':
                                                    $status_class = 'bg-danger';
                                                    $status_text = 'Nouveau';
                                                    break;
                                                case 'in_progress':
                                                    $status_class = 'bg-warning';
                                                    $status_text = 'En cours';
                                                    break;
                                                case 'resolved':
                                                    $status_class = 'bg-success';
                                                    $status_text = 'Résolu';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $status_class; ?> status-badge">
                                                <?php echo $status_text; ?>
                                            </span>
                                            <small class="text-muted ms-2">
                                                <?php echo date('d/m/Y H:i', strtotime($message['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="message-content mb-3">
                                            <?php echo htmlspecialchars($message['message']); ?>
                                        </div>
                                        <div class="message-actions">
                                            <a href="mailto:<?php echo htmlspecialchars($message['email']); ?>?subject=Re: <?php echo htmlspecialchars($message['subject']); ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-reply me-2"></i>Répondre par email
                                            </a>
                                            
                                            <?php if ($message['status'] === 'new'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                                    <input type="hidden" name="action" value="mark_in_progress">
                                                    <button type="submit" class="btn btn-sm btn-outline-warning">
                                                        <i class="fas fa-clock me-2"></i>Marquer en cours
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($message['status'] === 'new' || $message['status'] === 'in_progress'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                                    <input type="hidden" name="action" value="mark_resolved">
                                                    <button type="submit" class="btn btn-sm btn-outline-success">
                                                        <i class="fas fa-check-circle me-2"></i>Marquer comme résolu
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce message ?');">
                                                <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-trash me-2"></i>Supprimer
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>Aucun message n'a été reçu pour le moment.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
