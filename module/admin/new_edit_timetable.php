<?php
include_once('main.php');
include_once('../../service/mysqlcon.php');

// Initialiser le contenu pour le template
ob_start();

$admin_id = $_SESSION['login_id'];
$success_message = '';
$error_message = '';

// Récupérer l'ID de l'emploi du temps à modifier
$schedule_id = $_GET['id'] ?? 0;

// Vérifier si l'emploi du temps existe
$stmt = $link->prepare("
    SELECT cs.*, c.name as class_name, co.name as subject_name, t.name as teacher_name
    FROM class_schedule cs
    JOIN class c ON cs.class_id = c.id
    JOIN course co ON cs.subject_id = co.id
    JOIN teachers t ON cs.teacher_id = t.id
    WHERE cs.id = ?
");
$stmt->bind_param("i", $schedule_id);
$stmt->execute();
$result = $stmt->get_result();
$schedule = $result->fetch_assoc();

if (!$schedule) {
    $_SESSION['error_message'] = "Emploi du temps non trouvé ou vous n'avez pas les droits d'accès";
    header("Location: new_timetable.php");
    exit();
}

// Récupérer les classes
$classes_query = "SELECT id, name FROM class ORDER BY name";
$classes_result = $link->query($classes_query);
$has_classes = $classes_result && $classes_result->num_rows > 0;

// Récupérer les créneaux horaires
$timeSlots_query = "SELECT slot_id, CONCAT(start_time, ' - ', end_time) as time_range FROM time_slots ORDER BY start_time";
$timeSlots_result = $link->query($timeSlots_query);
$has_timeSlots = $timeSlots_result && $timeSlots_result->num_rows > 0;

// Jours de la semaine
$days_of_week = [
    'Lundi' => 'Lundi',
    'Mardi' => 'Mardi',
    'Mercredi' => 'Mercredi',
    'Jeudi' => 'Jeudi',
    'Vendredi' => 'Vendredi',
    'Samedi' => 'Samedi'
];

// Fonction pour charger les matières et les enseignants en fonction de la classe sélectionnée
function loadSubjectsAndTeachers($link, $class_id, $admin_id) {
    $subjects = [];
    $teachers = [];
    
    if (!empty($class_id)) {
        // Récupérer les matières et les enseignants assignés à cette classe
        $stmt = $link->prepare("
            SELECT c.id as subject_id, c.name as subject_name, c.teacherid as teacher_id, t.name as teacher_name
            FROM course c
            JOIN teachers t ON c.teacherid = t.id
            WHERE c.classid = ? AND CONVERT(t.created_by USING utf8mb4) = CONVERT(? USING utf8mb4)
            ORDER BY c.name
        ");
        $stmt->bind_param("ss", $class_id, $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $subject_id = $row['subject_id'];
            $teacher_id = $row['teacher_id'];
            
            // Ajouter la matière si elle n'existe pas déjà
            if (!isset($subjects[$subject_id])) {
                $subjects[$subject_id] = $row['subject_name'];
            }
            
            // Ajouter l'enseignant s'il n'existe pas déjà
            if (!isset($teachers[$teacher_id])) {
                $teachers[$teacher_id] = $row['teacher_name'];
            }
        }
    }
    
    return ['subjects' => $subjects, 'teachers' => $teachers];
}

// Si une classe est sélectionnée, charger les matières et les enseignants
$selected_class = $_POST['class_id'] ?? $schedule['class_id'];
$subjects_teachers = loadSubjectsAndTeachers($link, $selected_class, $admin_id);

// Traiter la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_timetable'])) {
    $class_id = $_POST['class_id'] ?? '';
    $subject_id = $_POST['subject_id'] ?? '';
    $teacher_id = $_POST['teacher_id'] ?? '';
    $slot_id = $_POST['slot_id'] ?? '';
    $day_of_week = $_POST['day_of_week'] ?? '';
    $room = $_POST['room'] ?? '';
    $semester = $_POST['semester'] ?? '';
    $academic_year = $_POST['academic_year'] ?? '';
    
    // Validation des champs
    if (empty($class_id) || empty($subject_id) || empty($teacher_id) || empty($slot_id) || 
        empty($day_of_week) || empty($room) || empty($semester) || empty($academic_year)) {
        $error_message = "Tous les champs sont obligatoires";
    } else {
        // Vérifier d'abord si l'enseignant est bien assigné à cette classe et cette matière
        $stmt = $link->prepare("
            SELECT COUNT(*) as count FROM course 
            WHERE id = ? AND teacherid = ? AND classid = ?
        ");
        $stmt->bind_param("sss", $subject_id, $teacher_id, $class_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $valid_assignment = $result->fetch_assoc()['count'] > 0;
        
        if (!$valid_assignment) {
            $error_message = "Cet enseignant n'est pas assigné à cette classe pour cette matière. Veuillez vérifier les assignations.";
        } else {
            // Vérifier si cet emploi du temps existe déjà (sauf lui-même)
            $stmt = $link->prepare("
                SELECT COUNT(*) as count FROM class_schedule 
                WHERE class_id = ? AND subject_id = ? AND teacher_id = ? AND slot_id = ? AND day_of_week = ? 
                AND semester = ? AND academic_year = ? AND CONVERT(created_by USING utf8mb4) = CONVERT(? USING utf8mb4)
                AND id != ?
            ");
            $stmt->bind_param("sssissssi", $class_id, $subject_id, $teacher_id, $slot_id, $day_of_week, $semester, $academic_year, $admin_id, $schedule_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $duplicate_entry = $result->fetch_assoc()['count'] > 0;
            
            if ($duplicate_entry) {
                $error_message = "Cet emploi du temps existe déjà pour cette classe, cette matière, cet enseignant et ce créneau";
            } else {
                // Vérifier les conflits pour la classe (même classe, même créneau, même jour)
                $stmt = $link->prepare("
                    SELECT COUNT(*) as count FROM class_schedule 
                    WHERE class_id = ? AND slot_id = ? AND day_of_week = ? AND semester = ? AND academic_year = ? 
                    AND CONVERT(created_by USING utf8mb4) = CONVERT(? USING utf8mb4)
                    AND id != ?
                ");
                $stmt->bind_param("ssisssi", $class_id, $slot_id, $day_of_week, $semester, $academic_year, $admin_id, $schedule_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $class_conflict = $result->fetch_assoc()['count'] > 0;
                
                // Vérifier les conflits pour l'enseignant (même enseignant, même créneau, même jour)
                $stmt = $link->prepare("
                    SELECT COUNT(*) as count FROM class_schedule 
                    WHERE teacher_id = ? AND slot_id = ? AND day_of_week = ? AND semester = ? AND academic_year = ? 
                    AND CONVERT(created_by USING utf8mb4) = CONVERT(? USING utf8mb4)
                    AND id != ?
                ");
                $stmt->bind_param("ssisssi", $teacher_id, $slot_id, $day_of_week, $semester, $academic_year, $admin_id, $schedule_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $teacher_conflict = $result->fetch_assoc()['count'] > 0;
                
                if ($class_conflict) {
                    $error_message = "Cette classe a déjà un cours programmé sur ce créneau";
                } else if ($teacher_conflict) {
                    $error_message = "Cet enseignant a déjà un cours programmé sur ce créneau";
                } else {
                    // Mettre à jour l'emploi du temps
                    $stmt = $link->prepare("
                        UPDATE class_schedule 
                        SET class_id = ?, subject_id = ?, teacher_id = ?, slot_id = ?, day_of_week = ?, 
                            room = ?, semester = ?, academic_year = ?, created_by = ? 
                        WHERE id = ?
                    ");
                    
                    // Vérifier si l'ID de l'administrateur est valide
                    if (empty($admin_id) || $admin_id == 'ad-123-1') {
                        // Utiliser l'ID de l'administrateur tel quel s'il est valide
                        $created_by = $admin_id;
                    } else {
                        // Sinon, utiliser une valeur par défaut
                        $created_by = 'ad-123-1';
                        error_log("Utilisation de l'ID d'administrateur par défaut: $created_by");
                    }
                    
                    $stmt->bind_param("sssisssssi", $class_id, $subject_id, $teacher_id, $slot_id, $day_of_week, $room, $semester, $academic_year, $created_by, $schedule_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success_message'] = "Emploi du temps mis à jour avec succès";
                        header("Location: new_timetable.php");
                        exit();
                    } else {
                        $error_message = "Erreur lors de la mise à jour de l'emploi du temps: " . $link->error;
                    }
                }
            }
        }
    }
}
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h2 class="card-title mb-0">Modifier un cours dans l'emploi du temps</h2>
                </div>
                <div class="card-body">
                    <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="timetableForm">
                        <div class="mb-3">
                            <label for="class_id" class="form-label">Classe</label>
                            <select name="class_id" id="class_id" class="form-select" required>
                                <option value="">Sélectionner une classe</option>
                                <?php if ($has_classes): while($class = $classes_result->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($class['id']); ?>" <?php echo ($selected_class == $class['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['name']); ?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="subject_id" class="form-label">Matière</label>
                            <select name="subject_id" id="subject_id" class="form-select" required <?php echo empty($subjects_teachers['subjects']) ? 'disabled' : ''; ?>>
                                <option value="">Sélectionner une matière</option>
                                <?php if (!empty($subjects_teachers['subjects'])): foreach($subjects_teachers['subjects'] as $id => $name): ?>
                                <option value="<?php echo htmlspecialchars($id); ?>" <?php echo ($schedule['subject_id'] == $id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                                <?php endforeach; endif; ?>
                            </select>
                            <?php if (empty($subjects_teachers['subjects']) && !empty($selected_class)): ?>
                            <div class="form-text text-danger">Aucune matière n'est assignée à cette classe. Veuillez d'abord assigner des matières.</div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="teacher_id" class="form-label">Enseignant</label>
                            <select name="teacher_id" id="teacher_id" class="form-select" required <?php echo empty($subjects_teachers['teachers']) ? 'disabled' : ''; ?>>
                                <option value="">Sélectionner un enseignant</option>
                                <?php if (!empty($subjects_teachers['teachers'])): foreach($subjects_teachers['teachers'] as $id => $name): ?>
                                <option value="<?php echo htmlspecialchars($id); ?>" <?php echo ($schedule['teacher_id'] == $id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                                <?php endforeach; endif; ?>
                            </select>
                            <?php if (empty($subjects_teachers['teachers']) && !empty($selected_class)): ?>
                            <div class="form-text text-danger">Aucun enseignant n'est assigné à cette classe. Veuillez d'abord assigner des enseignants.</div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="slot_id" class="form-label">Créneau horaire</label>
                            <select name="slot_id" id="slot_id" class="form-select" required>
                                <option value="">Sélectionner un créneau horaire</option>
                                <?php if ($has_timeSlots): while($slot = $timeSlots_result->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($slot['slot_id']); ?>" <?php echo ($schedule['slot_id'] == $slot['slot_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($slot['time_range']); ?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="day_of_week" class="form-label">Jour de la semaine</label>
                            <select name="day_of_week" id="day_of_week" class="form-select" required>
                                <option value="">Sélectionner un jour</option>
                                <?php foreach($days_of_week as $key => $day): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>" <?php echo ($schedule['day_of_week'] == $key) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($day); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="room" class="form-label">Salle</label>
                            <input type="text" name="room" id="room" class="form-control" required value="<?php echo htmlspecialchars($schedule['room']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="semester" class="form-label">Semestre</label>
                            <select name="semester" id="semester" class="form-select" required>
                                <option value="">Sélectionner un semestre</option>
                                <option value="1" <?php echo ($schedule['semester'] == '1') ? 'selected' : ''; ?>>Semestre 1</option>
                                <option value="2" <?php echo ($schedule['semester'] == '2') ? 'selected' : ''; ?>>Semestre 2</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="academic_year" class="form-label">Année académique</label>
                            <input type="text" name="academic_year" id="academic_year" class="form-control" required 
                                   placeholder="ex: 2023-2024" value="<?php echo htmlspecialchars($schedule['academic_year']); ?>">
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" name="update_timetable" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Mettre à jour l'emploi du temps
                            </button>
                            <a href="new_timetable.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Retour à la liste
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Lorsque la classe change, soumettre le formulaire pour charger les matières et les enseignants
    document.getElementById('class_id').addEventListener('change', function() {
        if (this.value) {
            document.getElementById('timetableForm').submit();
        }
    });
    
    // Lorsque la matière change, sélectionner automatiquement l'enseignant correspondant
    document.getElementById('subject_id').addEventListener('change', function() {
        const subjectId = this.value;
        if (subjectId) {
            // Faire une requête AJAX pour obtenir l'enseignant de cette matière
            fetch('get_teacher_for_subject.php?subject_id=' + subjectId + '&class_id=' + document.getElementById('class_id').value)
                .then(response => response.json())
                .then(data => {
                    if (data.teacher_id) {
                        document.getElementById('teacher_id').value = data.teacher_id;
                    }
                })
                .catch(error => console.error('Erreur:', error));
        }
    });
});
</script>

<?php
$content = ob_get_clean();
include('templates/layout.php');
?>
