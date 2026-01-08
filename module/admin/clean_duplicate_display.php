<?php
include_once('main.php');
include_once('../../service/mysqlcon.php');

// Initialiser le contenu pour le template
ob_start();

$admin_id = $_SESSION['login_id'];
$success_message = '';
$error_message = '';

// Récupérer les doublons potentiels
$duplicates_query = "
    SELECT cs1.id, cs1.class_id, cs1.subject_id, cs1.teacher_id, cs1.slot_id, cs1.day_of_week,
           c.name as class_name, co.name as subject_name, t.name as teacher_name,
           ts.start_time, ts.end_time, CONCAT(ts.start_time, ' - ', ts.end_time) as time_slot
    FROM class_schedule cs1
    JOIN class c ON cs1.class_id = c.id
    JOIN course co ON cs1.subject_id = co.id
    LEFT JOIN teachers t ON cs1.teacher_id = t.id
    JOIN time_slots ts ON cs1.slot_id = ts.slot_id
    WHERE EXISTS (
        SELECT 1 FROM class_schedule cs2
        WHERE cs2.class_id = cs1.class_id
        AND cs2.subject_id = cs1.subject_id
        AND cs2.slot_id = cs1.slot_id
        AND cs2.day_of_week = cs1.day_of_week
        AND cs2.id != cs1.id
    )
    ORDER BY cs1.slot_id, cs1.day_of_week, cs1.class_id, cs1.subject_id
";

$duplicates_result = $link->query($duplicates_query);
$has_duplicates = $duplicates_result && $duplicates_result->num_rows > 0;
$duplicates = [];

if ($has_duplicates) {
    while ($row = $duplicates_result->fetch_assoc()) {
        $duplicates[] = $row;
    }
}

// Traiter la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_duplicates'])) {
    // Supprimer les doublons
    $deleted_count = 0;
    $errors = [];
    
    // Récupérer les groupes de doublons
    $groups_query = "
        SELECT class_id, subject_id, slot_id, day_of_week, COUNT(*) as count
        FROM class_schedule
        GROUP BY class_id, subject_id, slot_id, day_of_week
        HAVING COUNT(*) > 1
    ";
    $groups_result = $link->query($groups_query);
    
    if ($groups_result && $groups_result->num_rows > 0) {
        while ($group = $groups_result->fetch_assoc()) {
            // Pour chaque groupe, garder seulement l'enregistrement avec l'ID le plus élevé
            $delete_query = "
                DELETE FROM class_schedule
                WHERE class_id = ? AND subject_id = ? AND slot_id = ? AND day_of_week = ?
                AND id NOT IN (
                    SELECT MAX(id) FROM (
                        SELECT id FROM class_schedule
                        WHERE class_id = ? AND subject_id = ? AND slot_id = ? AND day_of_week = ?
                    ) as temp
                )
            ";
            $stmt = $link->prepare($delete_query);
            $stmt->bind_param("ssissis", 
                $group['class_id'], $group['subject_id'], $group['slot_id'], $group['day_of_week'],
                $group['class_id'], $group['subject_id'], $group['slot_id'], $group['day_of_week']
            );
            
            if ($stmt->execute()) {
                $deleted_count += $stmt->affected_rows;
            } else {
                $errors[] = "Erreur lors de la suppression des doublons pour le groupe {$group['class_id']}, {$group['subject_id']}, {$group['slot_id']}, {$group['day_of_week']} : " . $stmt->error;
            }
        }
    }
    
    if ($deleted_count > 0) {
        $success_message = "$deleted_count doublons ont été supprimés avec succès.";
    } else if (empty($errors)) {
        $success_message = "Aucun doublon n'a été trouvé.";
    }
    
    if (!empty($errors)) {
        $error_message = "Des erreurs se sont produites lors de la suppression des doublons :<br>" . implode("<br>", $errors);
    }
    
    // Rafraîchir la liste des doublons
    $duplicates_result = $link->query($duplicates_query);
    $has_duplicates = $duplicates_result && $duplicates_result->num_rows > 0;
    $duplicates = [];
    
    if ($has_duplicates) {
        while ($row = $duplicates_result->fetch_assoc()) {
            $duplicates[] = $row;
        }
    }
}

// Traiter la soumission du formulaire pour corriger l'affichage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_display'])) {
    // Ajouter un GROUP BY à la requête dans new_timetable.php
    $file_path = 'new_timetable.php';
    $file_content = file_get_contents($file_path);
    
    if ($file_content !== false) {
        // Vérifier si GROUP BY est déjà présent
        if (strpos($file_content, 'GROUP BY cs.id') === false) {
            // Ajouter GROUP BY avant ORDER BY
            $file_content = str_replace(
                'ORDER BY cs.day_of_week, ts.start_time, c.name',
                'GROUP BY cs.id ORDER BY cs.day_of_week, ts.start_time, c.name',
                $file_content
            );
            
            if (file_put_contents($file_path, $file_content) !== false) {
                $success_message = "L'affichage a été corrigé avec succès. Veuillez rafraîchir la page d'emploi du temps.";
            } else {
                $error_message = "Erreur lors de la modification du fichier $file_path.";
            }
        } else {
            $success_message = "L'affichage est déjà corrigé.";
        }
    } else {
        $error_message = "Erreur lors de la lecture du fichier $file_path.";
    }
}
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card shadow-sm">
                <div class="card-header bg-danger text-white">
                    <h2 class="card-title mb-0">Correction des doublons dans l'affichage de l'emploi du temps</h2>
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
                        Cette page vous permet de corriger les problèmes d'affichage des doublons dans l'emploi du temps.
                        Il y a deux types de problèmes possibles :
                    </p>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <h3 class="card-title h5 mb-0">1. Problème d'affichage</h3>
                                </div>
                                <div class="card-body">
                                    <p>
                                        Si vous voyez des doublons dans l'affichage de l'emploi du temps mais qu'il n'y a qu'un seul enregistrement 
                                        dans la base de données, c'est un problème d'affichage.
                                    </p>
                                    <form method="POST" action="">
                                        <div class="d-grid">
                                            <button type="submit" name="fix_display" class="btn btn-primary">
                                                <i class="fas fa-wrench me-2"></i>Corriger l'affichage
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <h3 class="card-title h5 mb-0">2. Vrais doublons dans la base de données</h3>
                                </div>
                                <div class="card-body">
                                    <p>
                                        Si vous avez réellement des doublons dans la base de données (plusieurs enregistrements identiques),
                                        vous pouvez les supprimer ici.
                                    </p>
                                    <form method="POST" action="" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer tous les doublons ?');">
                                        <div class="d-grid">
                                            <button type="submit" name="delete_duplicates" class="btn btn-danger">
                                                <i class="fas fa-trash-alt me-2"></i>Supprimer les doublons
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($has_duplicates): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h3 class="card-title h5 mb-0">Doublons détectés</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Horaire</th>
                                            <th>Jour</th>
                                            <th>Classe</th>
                                            <th>Matière</th>
                                            <th>Enseignant</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($duplicates as $duplicate): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($duplicate['id']); ?></td>
                                            <td><?php echo htmlspecialchars($duplicate['time_slot']); ?></td>
                                            <td><?php echo htmlspecialchars($duplicate['day_of_week']); ?></td>
                                            <td><?php echo htmlspecialchars($duplicate['class_name']); ?></td>
                                            <td><?php echo htmlspecialchars($duplicate['subject_name']); ?></td>
                                            <td><?php echo htmlspecialchars($duplicate['teacher_name']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info mb-4">
                        <i class="fas fa-info-circle me-2"></i>Aucun doublon n'a été détecté dans la base de données.
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-grid gap-2">
                        <a href="new_timetable.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Retour à l'emploi du temps
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include('templates/layout.php');
?>
