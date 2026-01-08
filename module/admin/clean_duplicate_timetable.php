<?php
include_once('main.php');
include_once('../../service/mysqlcon.php');

// Vérifier si l'utilisateur est connecté
$check = $_SESSION['login_id'] ?? null;
if(!isset($check)) {
    header("Location:../../");
    exit();
}

// Initialiser le contenu pour le template
ob_start();

$admin_id = $_SESSION['login_id'];
$success_message = '';
$error_message = '';

// Fonction pour nettoyer les emplois du temps dupliqués
function cleanDuplicateTimetables($link, $admin_id) {
    // Étape 1: Identifier les emplois du temps dupliqués
    $query = "
        SELECT class_id, subject_id, slot_id, day_of_week, semester, academic_year, COUNT(*) as count
        FROM class_schedule
        WHERE CONVERT(created_by USING utf8mb4) = CONVERT(? USING utf8mb4)
        GROUP BY class_id, subject_id, slot_id, day_of_week, semester, academic_year
        HAVING COUNT(*) > 1
    ";
    
    $stmt = $link->prepare($query);
    $stmt->bind_param("s", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $duplicates = [];
    while ($row = $result->fetch_assoc()) {
        $duplicates[] = $row;
    }
    
    if (empty($duplicates)) {
        return "Aucun emploi du temps dupliqué trouvé.";
    }
    
    $deleted_count = 0;
    
    // Étape 2: Pour chaque groupe de doublons, conserver seulement le premier et supprimer les autres
    foreach ($duplicates as $duplicate) {
        $query = "
            SELECT id
            FROM class_schedule
            WHERE class_id = ? 
            AND subject_id = ? 
            AND slot_id = ? 
            AND day_of_week = ? 
            AND semester = ? 
            AND academic_year = ?
            AND CONVERT(created_by USING utf8mb4) = CONVERT(? USING utf8mb4)
            ORDER BY id ASC
        ";
        
        $stmt = $link->prepare($query);
        $stmt->bind_param("ssissss", 
            $duplicate['class_id'], 
            $duplicate['subject_id'], 
            $duplicate['slot_id'], 
            $duplicate['day_of_week'], 
            $duplicate['semester'], 
            $duplicate['academic_year'],
            $admin_id
        );
        $stmt->execute();
        $ids_result = $stmt->get_result();
        
        $ids = [];
        while ($id_row = $ids_result->fetch_assoc()) {
            $ids[] = $id_row['id'];
        }
        
        // Conserver le premier ID et supprimer les autres
        $first_id = array_shift($ids);
        
        if (!empty($ids)) {
            $ids_str = implode(',', $ids);
            $delete_query = "DELETE FROM class_schedule WHERE id IN ($ids_str)";
            $link->query($delete_query);
            $deleted_count += $link->affected_rows;
        }
    }
    
    return "Nettoyage terminé. $deleted_count emplois du temps dupliqués ont été supprimés.";
}

// Traiter la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clean'])) {
    try {
        $result_message = cleanDuplicateTimetables($link, $admin_id);
        $success_message = $result_message;
    } catch (Exception $e) {
        $error_message = "Erreur lors du nettoyage : " . $e->getMessage();
    }
}

?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h2 class="card-title mb-0">Nettoyage des emplois du temps dupliqués</h2>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        Cet outil vous permet de nettoyer les emplois du temps dupliqués dans votre base de données.
                        Pour chaque groupe d'emplois du temps identiques (même classe, même matière, même créneau, même jour),
                        seul le premier sera conservé et les autres seront supprimés.
                    </p>
                    
                    <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="d-grid gap-2">
                            <button type="submit" name="clean" class="btn btn-primary">
                                <i class="fas fa-broom me-2"></i>Nettoyer les emplois du temps dupliqués
                            </button>
                            <a href="timeTable.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Retour à la gestion des emplois du temps
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card shadow-sm mt-4">
                <div class="card-header bg-info text-white">
                    <h3 class="card-title mb-0">Comment éviter les doublons à l'avenir</h3>
                </div>
                <div class="card-body">
                    <h4>Recommandations</h4>
                    <ol>
                        <li>
                            <strong>Vérifiez les assignations</strong> : Assurez-vous que l'enseignant est bien assigné à la classe et à la matière avant de créer un emploi du temps.
                        </li>
                        <li>
                            <strong>Utilisez le filtrage</strong> : Utilisez les filtres de classe et d'enseignant pour voir les emplois du temps existants avant d'en créer de nouveaux.
                        </li>
                        <li>
                            <strong>Vérifiez les conflits</strong> : Le système vérifie automatiquement les conflits d'horaire pour une même classe ou un même enseignant, mais soyez vigilant.
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include('templates/layout.php');
?>
