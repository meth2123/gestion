<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


include_once('main.php');
include_once('../../service/db_utils.php');
include_once('../../service/course_filters.php');



// Récupération des informations de l'enseignant
$teacher_info = db_fetch_row(
    "SELECT * FROM teachers WHERE id = ?",
    [$check],
    's'
);


if (!$teacher_info) {
    header("Location: ../../?error=teacher_not_found");
    exit();
}

// Solution simplifiée : récupérer uniquement les cours où l'enseignant est assigné via student_teacher_course
// Nous savons que c'est la source de vérité pour les assignations enseignant-cours-classe

// 1. Récupérer les assignations de l'enseignant
// Filtrer pour ne montrer que la classe CP pour le cours anglais
$teacher_assignments = db_fetch_all(
    "SELECT DISTINCT 
        c.id as course_id, 
        c.name as course_name, 
        cl.id as class_id, 
        cl.name as class_name,
        stc.created_by
     FROM student_teacher_course stc
     JOIN course c ON stc.course_id = c.id
     JOIN class cl ON stc.class_id = cl.id
     WHERE stc.teacher_id = ? 
     AND " . get_course_modify_filter_sql($check, 'class') . "
     GROUP BY c.id, cl.id
     ORDER BY cl.name, c.name",
    [$check],
    's'
);

// 2. Formater les résultats pour correspondre à la structure attendue
$courses = [];
$unique_classes = [];
$unique_courses = [];

if ($teacher_assignments) {
    foreach ($teacher_assignments as $assignment) {
        // Ajouter chaque assignation comme un cours distinct
        $courses[] = [
            'id' => $assignment['course_id'],
            'name' => $assignment['course_name'],
            'classid' => $assignment['class_id'],
            'class_name' => $assignment['class_name'],
            'created_by' => $assignment['created_by']
        ];
        
        // Suivre les classes uniques
        if (!in_array($assignment['class_id'], $unique_classes)) {
            $unique_classes[] = $assignment['class_id'];
        }
        
        // Suivre les cours uniques
        if (!in_array($assignment['course_id'], $unique_courses)) {
            $unique_courses[] = $assignment['course_id'];
        }
    }
}

if (!$courses) {
    $courses = [];
}

if (!$courses) {
    $courses = [];
}

// Récupération des statistiques
// Utiliser les listes de classes et cours uniques que nous avons déjà préparées
$total_classes = count($unique_classes);
$total_courses = count($unique_courses);

// Compter uniquement les élèves assignés à ce professeur via student_teacher_course
// Utiliser directement la requête sans dépendre des course_ids collectés précédemment
$students_count = db_fetch_row(
    "SELECT COUNT(DISTINCT student_id) as count 
     FROM student_teacher_course 
     WHERE teacher_id = ?",
    [$check],
    's'
);

$total_students = $students_count['count'] ?? 0;

// Récupération des notifications récentes
$notifications = db_fetch_all(
    "SELECT * FROM notifications 
     WHERE user_id = ? AND user_type = 'teacher' 
     ORDER BY created_at DESC LIMIT 5",
    [$check],
    's'
);

ob_start();
?>

<h1 class="h3 mb-4">Tableau de bord</h1>

<!-- Statistiques générales -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 bg-primary bg-opacity-10 p-3 rounded">
                        <i class="fas fa-book text-primary fa-2x"></i>
                    </div>
                    <div class="ms-3">
                        <h5 class="card-title">Mes cours</h5>
                        <h2 class="mb-0"><?php echo $total_courses; ?></h2>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 bg-success bg-opacity-10 p-3 rounded">
                        <i class="fas fa-users text-success fa-2x"></i>
                    </div>
                    <div class="ms-3">
                        <h5 class="card-title">Mes élèves</h5>
                        <h2 class="mb-0"><?php echo $total_students; ?></h2>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 bg-info bg-opacity-10 p-3 rounded">
                        <i class="fas fa-chalkboard text-info fa-2x"></i>
                    </div>
                    <div class="ms-3">
                        <h5 class="card-title">Mes classes</h5>
                        <h2 class="mb-0"><?php echo $total_classes; ?></h2>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Notifications récentes -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <h5 class="card-title mb-0">Notifications récentes</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($notifications)): ?>
            <div class="p-4 text-center text-muted">
                <i class="fas fa-bell-slash fa-2x mb-3"></i>
                <p>Aucune notification récente</p>
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($notifications as $notification): ?>
                    <div class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h6>
                            <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($notification['created_at'])); ?></small>
                        </div>
                        <p class="mb-1 text-truncate"><?php echo htmlspecialchars($notification['message']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Liste des cours -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="card-title mb-0">Mes cours</h5>
    </div>
    <div class="card-body">
        <?php if (empty($courses)): ?>
            <div class="text-center py-4 text-muted">
                <i class="fas fa-book-open fa-3x mb-3"></i>
                <p>Aucun cours assigné pour le moment</p>
                <p class="small">Contactez l'administrateur pour plus d'informations</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Cours</th>
                            <th>Classe</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $course): ?>
                            <tr>
                                <td>
                                    <div class="fw-medium"><?php echo htmlspecialchars($course['name'] ?? ''); ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($course['class_name'] ?? ''); ?></span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="course.php?id=<?php echo $course['id']; ?>" class="btn btn-outline-primary">
                                            <i class="fas fa-graduation-cap me-1"></i> Notes
                                        </a>
                                        <a href="attendance.php?id=<?php echo $course['id']; ?>" class="btn btn-outline-success">
                                            <i class="fas fa-calendar-check me-1"></i> Présences
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include_once('templates/layout.php');
?>
