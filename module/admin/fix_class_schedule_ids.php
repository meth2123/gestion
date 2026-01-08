<?php
include_once('main.php');
include_once('../../service/mysqlcon.php');

// Initialiser le contenu pour le template
ob_start();

$admin_id = $_SESSION['login_id'];
$success_message = '';
$error_message = '';
$update_count = 0;

// Fonction pour corriger les IDs dans la table class_schedule
function fixClassScheduleIds($link) {
    $update_count = 0;
    $errors = [];
    
    // 0. Vérifier s'il y a une contrainte d'unicité sur teacher_id et slot_id
    $constraints_query = "SHOW CREATE TABLE class_schedule";
    $constraints_result = $link->query($constraints_query);
    $has_unique_constraint = false;
    
    if ($constraints_result && $constraints_row = $constraints_result->fetch_assoc()) {
        $create_table = $constraints_row['Create Table'];
        $has_unique_constraint = strpos($create_table, 'unique_teacher_slot') !== false;
    }
    
    // 1. Récupérer tous les enregistrements de la table class_schedule
    $query = "SELECT * FROM class_schedule WHERE teacher_id = 0 OR created_by = 0";
    $result = $link->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $schedule_id = $row['id'];
            $subject_id = $row['subject_id'];
            $slot_id = $row['slot_id'];
            $day_of_week = $row['day_of_week'];
            
            // 2. Récupérer l'ID de l'enseignant à partir de la matière
            $teacher_query = "SELECT teacherid FROM course WHERE id = ?";
            $stmt = $link->prepare($teacher_query);
            $stmt->bind_param("s", $subject_id);
            $stmt->execute();
            $teacher_result = $stmt->get_result();
            
            if ($teacher_row = $teacher_result->fetch_assoc()) {
                $teacher_id = $teacher_row['teacherid'];
                
                // Vérifier s'il y a déjà un enregistrement avec cet enseignant et ce créneau
                if ($has_unique_constraint) {
                    $check_query = "SELECT COUNT(*) as count FROM class_schedule WHERE teacher_id = ? AND slot_id = ? AND day_of_week = ? AND id != ?";
                    $check_stmt = $link->prepare($check_query);
                    
                    if (strpos($teacher_id, 'te-') !== false) {
                        // Si l'ID de l'enseignant est une chaîne de caractères
                        $check_stmt->bind_param("sisi", $teacher_id, $slot_id, $day_of_week, $schedule_id);
                    } else {
                        // Si l'ID de l'enseignant est un entier
                        $teacher_id_int = (int)$teacher_id;
                        $check_stmt->bind_param("iisi", $teacher_id_int, $slot_id, $day_of_week, $schedule_id);
                    }
                    
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    $has_conflict = $check_result->fetch_assoc()['count'] > 0;
                    
                    if ($has_conflict) {
                        $errors[] = "Impossible de mettre à jour l'enregistrement #$schedule_id car il y a déjà un enregistrement avec l'enseignant #$teacher_id et le créneau #$slot_id le $day_of_week";
                        continue;
                    }
                }
                
                // 3. Mettre à jour l'enregistrement avec les bons IDs
                // Vérifier d'abord le type de la colonne teacher_id
                $columns_query = "SHOW COLUMNS FROM class_schedule WHERE Field = 'teacher_id' OR Field = 'created_by'";
                $columns_result = $link->query($columns_query);
                $columns = [];
                while ($column = $columns_result->fetch_assoc()) {
                    $columns[$column['Field']] = $column;
                }
                
                // Déterminer les types de données appropriés
                if (isset($columns['teacher_id']) && strpos($columns['teacher_id']['Type'], 'int') !== false) {
                    // Si teacher_id est un entier, convertir la valeur
                    $teacher_id_value = (int)$teacher_id;
                    $created_by_value = 1; // Valeur entière pour created_by
                    $update_query = "UPDATE class_schedule SET teacher_id = ?, created_by = ? WHERE id = ?";
                    $update_stmt = $link->prepare($update_query);
                    $update_stmt->bind_param("iii", $teacher_id_value, $created_by_value, $schedule_id);
                } else {
                    // Si teacher_id est une chaîne de caractères, utiliser la valeur telle quelle
                    $created_by_value = 'ad-123-1';
                    $update_query = "UPDATE class_schedule SET teacher_id = ?, created_by = ? WHERE id = ?";
                    $update_stmt = $link->prepare($update_query);
                    $update_stmt->bind_param("ssi", $teacher_id, $created_by_value, $schedule_id);
                }
                
                if ($update_stmt->execute()) {
                    $update_count++;
                } else {
                    $errors[] = "Erreur lors de la mise à jour de l'enregistrement #$schedule_id : " . $update_stmt->error;
                }
            } else {
                $errors[] = "Impossible de trouver l'enseignant pour la matière #$subject_id (emploi du temps #$schedule_id)";
            }
        }
    }
    
    return ['count' => $update_count, 'errors' => $errors];
}

// Traiter la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_ids'])) {
    $result = fixClassScheduleIds($link);
    $update_count = $result['count'];
    
    if ($update_count > 0) {
        $success_message = "$update_count enregistrements ont été mis à jour avec succès.";
    } else if (empty($result['errors'])) {
        $success_message = "Aucun enregistrement n'avait besoin d'être mis à jour.";
    }
    
    if (!empty($result['errors'])) {
        $error_message = "Des erreurs se sont produites lors de la mise à jour :<br>" . implode("<br>", $result['errors']);
    }
}

// Récupérer les statistiques actuelles
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN teacher_id = 0 THEN 1 ELSE 0 END) as missing_teacher,
    SUM(CASE WHEN created_by = 0 THEN 1 ELSE 0 END) as missing_admin
FROM class_schedule";
$stats_result = $link->query($stats_query);
$stats = $stats_result->fetch_assoc();
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h2 class="card-title mb-0">Correction des IDs dans la table class_schedule</h2>
                </div>
                <div class="card-body">
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
                    
                    <p class="text-muted mb-4">
                        Cet outil vous permet de corriger les IDs d'enseignants et d'administrateurs dans la table class_schedule.
                        Pour chaque enregistrement où l'ID de l'enseignant est 0, l'outil récupérera l'ID correct à partir de la matière.
                        Pour chaque enregistrement où l'ID de l'administrateur est 0, l'outil utilisera l'ID 'ad-123-1'.
                    </p>
                    
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h3 class="card-title h5 mb-0">Statistiques actuelles</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h4 class="h2"><?php echo $stats['total']; ?></h4>
                                            <p class="mb-0">Total des enregistrements</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h4 class="h2"><?php echo $stats['missing_teacher']; ?></h4>
                                            <p class="mb-0">Sans ID d'enseignant</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h4 class="h2"><?php echo $stats['missing_admin']; ?></h4>
                                            <p class="mb-0">Sans ID d'administrateur</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST" action="">
                        <div class="d-grid gap-2">
                            <button type="submit" name="fix_ids" class="btn btn-primary">
                                <i class="fas fa-wrench me-2"></i>Corriger les IDs manquants
                            </button>
                            <a href="new_timetable.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Retour à l'emploi du temps
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include('templates/layout.php');
?>
