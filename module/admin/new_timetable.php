<?php
include_once('main.php');
include_once('../../service/mysqlcon.php');

// Initialiser le contenu pour le template
ob_start();

$admin_id = $_SESSION['login_id'];
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';

// Effacer les messages après les avoir récupérés
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Récupérer les classes pour les filtres
$classes_query = "SELECT id, name FROM class WHERE created_by = ? ORDER BY name";
$stmt = $link->prepare($classes_query);
$stmt->bind_param("s", $admin_id);
$classes_result = $stmt->execute() ? $stmt->get_result() : false;
$has_classes = $classes_result && $classes_result->num_rows > 0;

// Récupérer les enseignants pour les filtres
$teachers_query = "SELECT id, name FROM teachers WHERE created_by = ? ORDER BY name";
$stmt = $link->prepare($teachers_query);
$stmt->bind_param("s", $admin_id);
$teachers_result = $stmt->execute() ? $stmt->get_result() : false;
$has_teachers = $teachers_result && $teachers_result->num_rows > 0;
$has_classes = $classes_result && $classes_result->num_rows > 0;

// Récupérer les enseignants pour les filtres
$teachers_query = "SELECT id, name FROM teachers WHERE created_by = ? ORDER BY name";
$stmt = $link->prepare($teachers_query);
$stmt->bind_param("s", $admin_id);
$stmt->execute();
$teachers_result = $stmt->get_result();
$has_teachers = $teachers_result && $teachers_result->num_rows > 0;

// Récupérer les créneaux horaires
$timeSlots_query = "SELECT DISTINCT slot_id, start_time, end_time, CONCAT(start_time, ' - ', end_time) as time_range FROM time_slots ORDER BY start_time";
$timeSlots_result = $link->query($timeSlots_query);
$has_timeSlots = $timeSlots_result && $timeSlots_result->num_rows > 0;

// Si aucun créneau horaire n'existe, en créer quelques-uns par défaut
if (!$has_timeSlots) {
    $default_slots = [
        ['08:00:00', '09:00:00'],
        ['09:00:00', '10:00:00'],
        ['10:00:00', '11:00:00'],
        ['11:00:00', '12:00:00'],
        ['13:00:00', '14:00:00'],
        ['14:00:00', '15:00:00'],
        ['15:00:00', '16:00:00'],
        ['16:00:00', '17:00:00']
    ];
    
    foreach ($default_slots as $slot) {
        $stmt = $link->prepare("INSERT INTO time_slots (start_time, end_time) VALUES (?, ?)");
        $stmt->bind_param("ss", $slot[0], $slot[1]);
        $stmt->execute();
    }
    
    // Récupérer à nouveau les créneaux horaires
    $timeSlots_result = $link->query($timeSlots_query);
    $has_timeSlots = $timeSlots_result && $timeSlots_result->num_rows > 0;
}

// Jours de la semaine
$days_of_week = [
    'Lundi' => 'Lundi',
    'Mardi' => 'Mardi',
    'Mercredi' => 'Mercredi',
    'Jeudi' => 'Jeudi',
    'Vendredi' => 'Vendredi',
    'Samedi' => 'Samedi'
];

// Filtrer par classe si demandé
$filter_class = isset($_GET['class_id']) && !empty($_GET['class_id']) ? $_GET['class_id'] : '';
$filter_teacher = isset($_GET['teacher_id']) && !empty($_GET['teacher_id']) ? $_GET['teacher_id'] : '';

// Récupérer les emplois du temps avec filtrage
// Déterminer le type de la colonne created_by
$columns_query = "SHOW COLUMNS FROM class_schedule WHERE Field = 'created_by'";
$columns_result = $link->query($columns_query);
$created_by_type = 'int';

if ($columns_result && $column = $columns_result->fetch_assoc()) {
    $created_by_type = strpos($column['Type'], 'int') !== false ? 'int' : 'varchar';
}

$timetable_query = "
    SELECT DISTINCT cs.id, cs.class_id, cs.subject_id, cs.teacher_id, cs.slot_id, 
           cs.day_of_week, cs.room, cs.semester, cs.academic_year, cs.created_by,
           c.name as class_name, co.name as subject_name, 
           COALESCE(t.name, 'Enseignant non assigné') as teacher_name,
           ts.start_time, ts.end_time, CONCAT(ts.start_time, ' - ', ts.end_time) as time_slot
    FROM class_schedule cs
    JOIN class c ON cs.class_id = c.id
    JOIN course co ON cs.subject_id = co.id
    LEFT JOIN teachers t ON cs.teacher_id = t.id
    JOIN time_slots ts ON cs.slot_id = ts.slot_id
";

$params = [$admin_id];
$types = "s"; // Changé de "i" à "s" car created_by est maintenant VARCHAR

// Ajouter la condition WHERE pour l'administrateur
$timetable_query .= " WHERE cs.created_by = ?";

if (!empty($filter_class)) {
    $timetable_query .= " AND cs.class_id = ?";
    $params[] = $filter_class;
    $types .= "s";
}

if (!empty($filter_teacher)) {
    $timetable_query .= " AND cs.teacher_id = ?";
    $params[] = $filter_teacher;
    $types .= "s";
}

$timetable_query .= " ORDER BY cs.day_of_week, ts.start_time, c.name";

if (!empty($params)) {
    $stmt = $link->prepare($timetable_query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $timetable_result = $stmt->get_result();
} else {
    $timetable_result = $link->query($timetable_query);
}
$has_timetable = $timetable_result && $timetable_result->num_rows > 0;

// Organiser les emplois du temps par jour
$timetable_by_day = [];
if ($has_timetable) {
    while ($row = $timetable_result->fetch_assoc()) {
        $day = $row['day_of_week'];
        if (!isset($timetable_by_day[$day])) {
            $timetable_by_day[$day] = [];
        }
        $timetable_by_day[$day][] = $row;
    }
}

// Fonction pour supprimer un emploi du temps
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $schedule_id = $_GET['delete'];
    
    // Vérifier si l'emploi du temps existe
    $stmt = $link->prepare("
        SELECT id FROM class_schedule 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $schedule_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $schedule = $result->fetch_assoc();
    
    if ($schedule) {
        $stmt = $link->prepare("DELETE FROM class_schedule WHERE id = ?");
        $stmt->bind_param("i", $schedule_id);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Emploi du temps supprimé avec succès";
        } else {
            $_SESSION['error_message'] = "Erreur lors de la suppression de l'emploi du temps: " . $link->error;
        }
    } else {
        $_SESSION['error_message'] = "Emploi du temps non trouvé";
    }
    
    // Rediriger pour éviter les soumissions multiples
    header("Location: new_timetable.php" . (!empty($filter_class) ? "?class_id=$filter_class" : "") . 
           (!empty($filter_teacher) ? (empty($filter_class) ? "?teacher_id=$filter_teacher" : "&teacher_id=$filter_teacher") : ""));
    exit();
}

?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="card-title mb-0">Gestion de l'Emploi du Temps</h2>
                        <div>
                            <a href="clean_duplicate_timetable.php" class="btn btn-outline-secondary me-2">
                                <i class="fas fa-broom me-2"></i>Nettoyer les doublons
                            </a>
                            <a href="new_create_timetable.php" class="btn btn-primary">
                                <i class="fas fa-plus-circle me-2"></i>Ajouter un cours
                            </a>
                        </div>
                    </div>
                    
                    <!-- Formulaire de filtrage -->
                    <form action="" method="GET" class="row g-3 mb-4">
                        <div class="col-md-5">
                            <label for="class_id" class="form-label">Filtrer par classe</label>
                            <select name="class_id" id="class_id" class="form-select">
                                <option value="">Toutes les classes</option>
                                <?php if ($has_classes): while($class = $classes_result->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($class['id']); ?>" <?php echo ($filter_class == $class['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['name']); ?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label for="teacher_id" class="form-label">Filtrer par enseignant</label>
                            <select name="teacher_id" id="teacher_id" class="form-select">
                                <option value="">Tous les enseignants</option>
                                <?php if ($has_teachers): while($teacher = $teachers_result->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($teacher['id']); ?>" <?php echo ($filter_teacher == $teacher['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher['name']); ?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Filtrer</button>
                        </div>
                    </form>
                    
                    <p class="text-muted">Gérez les emplois du temps des classes et des enseignants</p>
                </div>
            </div>
        </div>
    </div>

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

    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h3 class="card-title mb-4">Emploi du Temps</h3>
                    
                    <?php if ($has_timetable): ?>
                    <div class="accordion" id="timetableAccordion">
                        <?php foreach ($timetable_by_day as $day => $schedules): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading<?php echo $day; ?>">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $day; ?>" aria-expanded="true" aria-controls="collapse<?php echo $day; ?>">
                                    <?php echo htmlspecialchars($day); ?>
                                </button>
                            </h2>
                            <div id="collapse<?php echo $day; ?>" class="accordion-collapse collapse show" aria-labelledby="heading<?php echo $day; ?>" data-bs-parent="#timetableAccordion">
                                <div class="accordion-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Horaire</th>
                                                    <th>Classe</th>
                                                    <th>Matière</th>
                                                    <th>Enseignant</th>
                                                    <th>Salle</th>
                                                    <th>Semestre</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($schedules as $schedule): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($schedule['time_slot']); ?></td>
                                                    <td><?php echo htmlspecialchars($schedule['class_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($schedule['subject_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($schedule['teacher_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($schedule['room']); ?></td>
                                                    <td><?php echo htmlspecialchars($schedule['semester']); ?></td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="new_edit_timetable.php?id=<?php echo $schedule['id']; ?>" 
                                                               class="btn btn-outline-primary" title="Modifier">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <a href="new_timetable.php?delete=<?php echo $schedule['id']; ?><?php echo !empty($filter_class) ? '&class_id='.$filter_class : ''; ?><?php echo !empty($filter_teacher) ? '&teacher_id='.$filter_teacher : ''; ?>" 
                                                               class="btn btn-outline-danger" 
                                                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet emploi du temps ?');" 
                                                               title="Supprimer">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info d-flex align-items-center" role="alert">
                        <i class="fas fa-info-circle me-3 fs-4"></i>
                        <div>
                            <?php if (!empty($filter_class) || !empty($filter_teacher)): ?>
                            Aucun emploi du temps trouvé avec les filtres sélectionnés. <a href="new_timetable.php" class="alert-link">Voir tous les emplois du temps</a>
                            <?php else: ?>
                            Aucun emploi du temps n'a été créé. Utilisez le bouton "Ajouter un cours" pour commencer à créer l'emploi du temps.
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include('templates/layout.php');
?>





