<?php
include_once('main.php');
include_once('../../service/mysqlcon.php');

// Initialiser le contenu pour le template
ob_start();

$admin_id = $_SESSION['login_id'];
$success_message = '';
$error_message = '';

// Récupérer les enregistrements avec teacher_id = 0
$query = "SELECT * FROM class_schedule WHERE teacher_id = 0";
$result = $link->query($query);
$zero_teacher_records = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $zero_teacher_records[] = $row;
    }
}

// Traiter la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_records'])) {
    // Supprimer les enregistrements avec teacher_id = 0
    $delete_query = "DELETE FROM class_schedule WHERE teacher_id = 0";
    if ($link->query($delete_query)) {
        $affected_rows = $link->affected_rows;
        $success_message = "$affected_rows enregistrements ont été supprimés avec succès.";
    } else {
        $error_message = "Erreur lors de la suppression des enregistrements : " . $link->error;
    }
    
    // Rafraîchir la liste des enregistrements
    $result = $link->query($query);
    $zero_teacher_records = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $zero_teacher_records[] = $row;
        }
    }
}

// Traiter la soumission du formulaire pour mettre à jour un enregistrement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_record'])) {
    $record_id = $_POST['record_id'] ?? 0;
    $teacher_id = $_POST['teacher_id'] ?? 0;
    
    if ($record_id && $teacher_id) {
        // Mettre à jour l'enregistrement avec le bon teacher_id
        $update_query = "UPDATE class_schedule SET teacher_id = ? WHERE id = ?";
        $stmt = $link->prepare($update_query);
        $stmt->bind_param("ii", $teacher_id, $record_id);
        
        if ($stmt->execute()) {
            $success_message = "L'enregistrement #$record_id a été mis à jour avec succès.";
        } else {
            $error_message = "Erreur lors de la mise à jour de l'enregistrement #$record_id : " . $stmt->error;
        }
        
        // Rafraîchir la liste des enregistrements
        $result = $link->query($query);
        $zero_teacher_records = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $zero_teacher_records[] = $row;
            }
        }
    } else {
        $error_message = "ID d'enregistrement ou ID d'enseignant manquant.";
    }
}

// Récupérer les enseignants pour le formulaire de mise à jour
$teachers_query = "SELECT id, name FROM teachers ORDER BY name";
$teachers_result = $link->query($teachers_query);
$teachers = [];
if ($teachers_result && $teachers_result->num_rows > 0) {
    while ($row = $teachers_result->fetch_assoc()) {
        $teachers[] = $row;
    }
}
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card shadow-sm">
                <div class="card-header bg-danger text-white">
                    <h2 class="card-title mb-0">Nettoyage des enregistrements avec teacher_id = 0</h2>
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
                        Cette page vous permet de nettoyer les enregistrements problématiques dans la table class_schedule 
                        où teacher_id = 0. Ces enregistrements peuvent causer des erreurs de contrainte d'unicité.
                    </p>
                    
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h3 class="card-title h5 mb-0">Enregistrements avec teacher_id = 0</h3>
                        </div>
                        <div class="card-body">
                            <?php if (count($zero_teacher_records) > 0): ?>
                                <div class="table-responsive mb-4">
                                    <table class="table table-striped table-bordered">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Classe</th>
                                                <th>Matière</th>
                                                <th>Créneau</th>
                                                <th>Jour</th>
                                                <th>Salle</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($zero_teacher_records as $record): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($record['id']); ?></td>
                                                <td><?php echo htmlspecialchars($record['class_id']); ?></td>
                                                <td><?php echo htmlspecialchars($record['subject_id']); ?></td>
                                                <td><?php echo htmlspecialchars($record['slot_id']); ?></td>
                                                <td><?php echo htmlspecialchars($record['day_of_week']); ?></td>
                                                <td><?php echo htmlspecialchars($record['room']); ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#updateModal<?php echo $record['id']; ?>">
                                                        <i class="fas fa-edit me-1"></i>Mettre à jour
                                                    </button>
                                                    
                                                    <!-- Modal pour mettre à jour l'enregistrement -->
                                                    <div class="modal fade" id="updateModal<?php echo $record['id']; ?>" tabindex="-1" aria-labelledby="updateModalLabel<?php echo $record['id']; ?>" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="updateModalLabel<?php echo $record['id']; ?>">Mettre à jour l'enregistrement #<?php echo $record['id']; ?></h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <form method="POST" action="">
                                                                    <div class="modal-body">
                                                                        <input type="hidden" name="record_id" value="<?php echo $record['id']; ?>">
                                                                        
                                                                        <div class="mb-3">
                                                                            <label for="teacher_id<?php echo $record['id']; ?>" class="form-label">Enseignant</label>
                                                                            <select name="teacher_id" id="teacher_id<?php echo $record['id']; ?>" class="form-select" required>
                                                                                <option value="">Sélectionner un enseignant</option>
                                                                                <?php foreach ($teachers as $teacher): ?>
                                                                                <option value="<?php echo htmlspecialchars($teacher['id']); ?>">
                                                                                    <?php echo htmlspecialchars($teacher['name']); ?>
                                                                                </option>
                                                                                <?php endforeach; ?>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                                        <button type="submit" name="update_record" class="btn btn-primary">Mettre à jour</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <form method="POST" action="" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer tous ces enregistrements ?');">
                                    <div class="d-grid gap-2">
                                        <button type="submit" name="delete_records" class="btn btn-danger">
                                            <i class="fas fa-trash-alt me-2"></i>Supprimer tous les enregistrements avec teacher_id = 0
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-success mb-0">
                                    <i class="fas fa-check-circle me-2"></i>Aucun enregistrement avec teacher_id = 0 n'a été trouvé.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
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
