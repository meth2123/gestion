<?php
include_once('main.php');
include_once('../../service/mysqlcon.php');

// Initialiser le contenu pour le template
ob_start();

$admin_id = $_SESSION['login_id'];
$success_message = '';
$error_message = '';

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

// Traiter la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_timetable'])) {
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
            // Vérifier si cet emploi du temps existe déjà
            $stmt = $link->prepare("
                SELECT COUNT(*) as count FROM class_schedule 
                WHERE class_id = ? AND subject_id = ? AND teacher_id = ? AND slot_id = ? AND day_of_week = ? 
                AND semester = ? AND academic_year = ? AND created_by = ?
            ");
            $stmt->bind_param("sssisssi", $class_id, $subject_id, $teacher_id, $slot_id, $day_of_week, $semester, $academic_year, $admin_id);
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
                    AND created_by = ?
                ");
                $stmt->bind_param("sisssi", $class_id, $slot_id, $day_of_week, $semester, $academic_year, $admin_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $class_conflict = $result->fetch_assoc()['count'] > 0;
                
                // Déterminer le type de la colonne teacher_id
                $columns_query = "SHOW COLUMNS FROM class_schedule WHERE Field = 'teacher_id'";
                $columns_result = $link->query($columns_query);
                $teacher_id_type = 'varchar';
                
                if ($columns_result && $column = $columns_result->fetch_assoc()) {
                    $teacher_id_type = strpos($column['Type'], 'int') !== false ? 'int' : 'varchar';
                }
                
                // Vérifier les conflits pour l'enseignant (même enseignant, même créneau, même jour)
                if ($teacher_id_type === 'int') {
                    // Si teacher_id est un entier, convertir la valeur
                    $teacher_id_value = (int)$teacher_id;
                    $stmt = $link->prepare("
                        SELECT COUNT(*) as count FROM class_schedule 
                        WHERE teacher_id = ? AND slot_id = ? AND day_of_week = ? AND semester = ? AND academic_year = ?
                    ");
                    $stmt->bind_param("iisss", $teacher_id_value, $slot_id, $day_of_week, $semester, $academic_year);
                } else {
                    // Si teacher_id est une chaîne de caractères, utiliser la valeur telle quelle
                    $stmt = $link->prepare("
                        SELECT COUNT(*) as count FROM class_schedule 
                        WHERE teacher_id = ? AND slot_id = ? AND day_of_week = ? AND semester = ? AND academic_year = ?
                    ");
                    $stmt->bind_param("sisss", $teacher_id, $slot_id, $day_of_week, $semester, $academic_year);
                }
                
                $stmt->execute();
                $result = $stmt->get_result();
                $teacher_conflict = $result->fetch_assoc()['count'] > 0;
                
                if ($class_conflict) {
                    $error_message = "Cette classe a déjà un cours programmé sur ce créneau";
                } else if ($teacher_conflict) {
                    $error_message = "Cet enseignant a déjà un cours programmé sur ce créneau";
                } else {
                    // Insérer dans la table class_schedule
                    $stmt = $link->prepare("
                        INSERT INTO class_schedule (class_id, subject_id, teacher_id, slot_id, day_of_week, room, semester, academic_year, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    // Toujours récupérer l'ID de l'enseignant à partir de la matière et de la classe
                    $teacher_query = "SELECT teacherid FROM course WHERE id = ? AND classid = ?";
                    $teacher_stmt = $link->prepare($teacher_query);
                    $teacher_stmt->bind_param("ss", $subject_id, $class_id);
                    $teacher_stmt->execute();
                    $teacher_result = $teacher_stmt->get_result();
                    
                    if ($teacher_row = $teacher_result->fetch_assoc()) {
                        // Utiliser l'ID de l'enseignant de la base de données, pas celui du formulaire
                        $teacher_id = $teacher_row['teacherid'];
                    }
                    
                    // Vérifier si l'ID de l'enseignant est valide
                    if (empty($teacher_id) || $teacher_id == '0') {
                        $error_message = "Impossible de créer l'emploi du temps : aucun enseignant n'est assigné à cette classe pour cette matière.";
                    } else {
                        // Toujours utiliser l'ID de l'administrateur connecté
                        $admin_id = $_SESSION['login_id'];
                        
                        // S'assurer que l'ID de l'administrateur n'est pas vide
                        if (empty($admin_id)) {
                            $error_message = "Impossible de créer l'emploi du temps : vous n'êtes pas connecté en tant qu'administrateur.";
                        } else {
                            // Débogage : afficher les valeurs avant insertion
                            echo "<pre>";
                            echo "class_id: $class_id\n";
                            echo "subject_id: $subject_id\n";
                            echo "teacher_id: $teacher_id\n";
                            echo "slot_id: $slot_id\n";
                            echo "day_of_week: $day_of_week\n";
                            echo "room: $room\n";
                            echo "semester: $semester\n";
                            echo "academic_year: $academic_year\n";
                            echo "admin_id: $admin_id\n";
                            echo "Type de teacher_id: $teacher_id_type\n";
                            echo "</pre>";
                            
                            if ($teacher_id_type === 'int') {
                                // Si teacher_id est un entier, convertir les valeurs
                                $teacher_id_value = (int)$teacher_id;
                                $admin_id_value = (int)$admin_id; // Valeur entière pour created_by
                                
                                $stmt->bind_param("ssiissssi", $class_id, $subject_id, $teacher_id_value, $slot_id, $day_of_week, $room, $semester, $academic_year, $admin_id_value);
                            } else {
                                // Si teacher_id est une chaîne de caractères, utiliser les valeurs telles quelles
                                $stmt->bind_param("sssisssss", $class_id, $subject_id, $teacher_id, $slot_id, $day_of_week, $room, $semester, $academic_year, $admin_id);
                            }
                            
                            $success = $stmt->execute();  
                            if ($success) {
                                $_SESSION['success_message'] = "Emploi du temps créé avec succès";
                                header("Location: new_timetable.php");
                                exit();
                            } else {
                                $error_message = "Erreur lors de la création de l'emploi du temps: " . $link->error;
                            }
                        }
                    }
                }
            }
        }
    }
}

// Fonction pour charger les matières et les enseignants en fonction de la classe sélectionnée
function loadSubjectsAndTeachers($link, $class_id, $admin_id) {
    $subjects = [];
    $teachers = [];
    
    if (!empty($class_id)) {
        // Récupérer les matières et les enseignants assignés à cette classe
        $stmt = $link->prepare("
            SELECT c.id as subject_id, c.name as subject_name, 
                   t.id as teacher_id, t.name as teacher_name
            FROM course c
            LEFT JOIN teachers t ON c.teacherid = t.id
            WHERE c.classid = ?
            ORDER BY c.name
        ");
        $stmt->bind_param("s", $class_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $subjects[$row['subject_id']] = $row['subject_name'];
            if (!empty($row['teacher_id'])) {
                $teachers[$row['subject_id']] = [
                    'id' => $row['teacher_id'],
                    'name' => $row['teacher_name']
                ];
            }
        }
    }
    
    return [
        'subjects' => $subjects,
        'teachers' => $teachers
    ];
}

// Si une classe est sélectionnée, charger les matières et les enseignants
$selected_class = $_POST['class_id'] ?? '';
$subjects_teachers = [];

if (!empty($selected_class)) {
    $subjects_teachers = loadSubjectsAndTeachers($link, $selected_class, $admin_id);
}
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h2 class="card-title mb-0">Créer un nouvel emploi du temps</h2>
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
                                <?php if ($has_classes): while ($class = $classes_result->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($class['id']); ?>" <?php echo (isset($_POST['class_id']) && $_POST['class_id'] == $class['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['name']); ?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="subject_id" class="form-label">Matière</label>
                            <select name="subject_id" id="subject_id" class="form-select" required>
                                <option value="">Sélectionner une matière</option>
                                <?php if (!empty($subjects_teachers['subjects'])): ?>
                                    <?php foreach ($subjects_teachers['subjects'] as $id => $name): ?>
                                    <option value="<?php echo htmlspecialchars($id); ?>" <?php echo (isset($_POST['subject_id']) && $_POST['subject_id'] == $id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($name); ?>
                                    </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="teacher_id" class="form-label">Enseignant</label>
                            <select name="teacher_id" id="teacher_id" class="form-select" required>
                                <option value="">Sélectionner un enseignant</option>
                                <?php if (!empty($subjects_teachers['teachers'])): ?>
                                    <?php foreach ($subjects_teachers['teachers'] as $subject_id => $teacher): ?>
                                    <option value="<?php echo htmlspecialchars($teacher['id']); ?>" 
                                            data-subject="<?php echo htmlspecialchars($subject_id); ?>"
                                            <?php echo (isset($_POST['teacher_id']) && $_POST['teacher_id'] == $teacher['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($teacher['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="slot_id" class="form-label">Créneau horaire</label>
                            <select name="slot_id" id="slot_id" class="form-select" required>
                                <option value="">Sélectionner un créneau</option>
                                <?php if ($has_timeSlots): while ($slot = $timeSlots_result->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($slot['slot_id']); ?>" <?php echo (isset($_POST['slot_id']) && $_POST['slot_id'] == $slot['slot_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($slot['time_range']); ?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="day_of_week" class="form-label">Jour</label>
                            <select name="day_of_week" id="day_of_week" class="form-select" required>
                                <option value="">Sélectionner un jour</option>
                                <?php foreach($days_of_week as $key => $day): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>" <?php echo (isset($_POST['day_of_week']) && $_POST['day_of_week'] == $key) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($day); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="room" class="form-label">Salle</label>
                            <input type="text" name="room" id="room" class="form-control" required value="<?php echo htmlspecialchars($_POST['room'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="semester" class="form-label">Semestre</label>
                            <select name="semester" id="semester" class="form-select" required>
                                <option value="">Sélectionner un semestre</option>
                                <option value="1" <?php echo (isset($_POST['semester']) && $_POST['semester'] == '1') ? 'selected' : ''; ?>>Semestre 1</option>
                                <option value="2" <?php echo (isset($_POST['semester']) && $_POST['semester'] == '2') ? 'selected' : ''; ?>>Semestre 2</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="academic_year" class="form-label">Année académique</label>
                            <input type="text" name="academic_year" id="academic_year" class="form-control" required 
                                   placeholder="ex: 2023-2024" value="<?php echo htmlspecialchars($_POST['academic_year'] ?? date('Y').'-'.(date('Y')+1)); ?>">
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" name="create_timetable" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Créer l'emploi du temps
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
