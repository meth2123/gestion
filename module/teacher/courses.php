<?php
include_once('main.php');
include_once('../../service/db_utils.php');
include_once('../../service/course_filters.php');

// Vérification de la session (utilise la même méthode que main.php)
if (!isset($_SESSION['teacher_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Définir la variable check pour le template layout.php
$check = $_SESSION['teacher_id'];
$teacher_id = $check;

// Récupérer les classes assignées à ce professeur
// Filtrer pour ne montrer que les classes qui sont réellement assignées à l'enseignant
$classes = db_fetch_all(
    "SELECT DISTINCT c.* 
     FROM class c 
     WHERE c.id IN (
         -- Classes assignées via la table course
         SELECT co.classid FROM course co WHERE co.teacherid = ?
         UNION
         -- Classes assignées via la table student_teacher_course
         SELECT stc.class_id FROM student_teacher_course stc 
         JOIN course co ON stc.course_id = co.id
         JOIN class cl ON stc.class_id = cl.id
         WHERE stc.teacher_id = ? 
         AND (co.name != 'anglais' OR (co.name = 'anglais' AND cl.name = 'cp'))
     )
     ORDER BY c.name",
    [$teacher_id, $teacher_id],
    'ss'
);

// Récupérer les cours si une classe est sélectionnée
$selected_class = $_GET['class_id'] ?? '';
$courses = [];

if ($selected_class) {
    // Nouvelle requête qui prend en compte les cours assignés via student_teacher_course
    // même si leur classid est différent
    $courses = db_fetch_all(
        "SELECT DISTINCT c.*, 
                (SELECT COUNT(DISTINCT stc.student_id) FROM student_teacher_course stc WHERE stc.course_id = c.id) as student_count,
                (SELECT COUNT(DISTINCT stc.student_id) FROM student_teacher_course stc WHERE stc.course_id = c.id AND stc.grade IS NOT NULL) as graded_count,
                (SELECT stc3.class_id FROM student_teacher_course stc3 WHERE stc3.course_id = c.id AND stc3.teacher_id = ? AND stc3.class_id = ? LIMIT 1) as assigned_class_id
         FROM course c 
         WHERE c.id IN (
             -- Cours directement assignés à l'enseignant dans la classe sélectionnée
             SELECT id FROM course WHERE classid = ? AND teacherid = ?
             UNION
             -- Cours assignés via student_teacher_course
             SELECT DISTINCT course_id FROM student_teacher_course 
             WHERE teacher_id = ? AND class_id = ?
         )
         ORDER BY c.name",
        [$teacher_id, $selected_class, $selected_class, $teacher_id, $teacher_id, $selected_class],
        'ssssss'
    );
    
    // Si aucun cours n'est trouvé, afficher un message de débogage
    if (empty($courses)) {
        error_log("Aucun cours trouvé pour la classe $selected_class et l'enseignant $teacher_id");
    }
}

// Préparation du contenu pour le template
$content = '';

// Informations de la classe sélectionnée
if ($selected_class) {
    $class_info = db_fetch_row(
        "SELECT * FROM class WHERE id = ?",
        [$selected_class],
        's'
    );
    
    if ($class_info) {
        $content .= '<div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 mb-0">Cours de la classe: <span class="text-primary">' . htmlspecialchars($class_info['name']) . '</span></h2>
            <a href="courses.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Toutes les classes</a>
        </div>';
    }
}

// Sélection de la classe
$content .= '<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <h5 class="card-title mb-0">Sélectionner une classe</h5>
    </div>
    <div class="card-body">
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">';

if (empty($classes)) {
    $content .= '<div class="col-12">
        <div class="alert alert-info mb-0">
            <i class="fas fa-info-circle me-2"></i>Aucune classe assignée.
        </div>
    </div>';
} else {
    foreach ($classes as $class) {
        $isActive = $selected_class === $class['id'];
        $content .= '<div class="col">
            <a href="?class_id=' . htmlspecialchars($class['id']) . '" class="text-decoration-none">
                <div class="card h-100 ' . ($isActive ? 'border-primary' : 'border-light') . ' hover-shadow">
                    <div class="card-body">
                        <h5 class="card-title">' . htmlspecialchars($class['name']) . '</h5>
                        <p class="card-text text-muted small">' . htmlspecialchars($class['description'] ?? 'Aucune description') . '</p>
                    </div>
                    ' . ($isActive ? '<div class="card-footer bg-primary bg-opacity-10 border-top-0 text-primary"><small><i class="fas fa-check-circle me-1"></i>Sélectionnée</small></div>' : '') . '
                </div>
            </a>
        </div>';
    }
}

$content .= '</div>
    </div>
</div>';

// Liste des cours si une classe est sélectionnée
if ($selected_class) {
    if (!empty($courses)) {
        $content .= '<div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Cours disponibles</h5>
                <span class="badge bg-primary rounded-pill">' . count($courses) . ' cours</span>
            </div>
            <div class="card-body">
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">';
        
        foreach ($courses as $course) {
            $progressPercentage = $course['student_count'] > 0 ? round(($course['graded_count'] / $course['student_count']) * 100) : 0;
            
            $content .= '<div class="col">
                <div class="card h-100 border-light hover-shadow">
                    <div class="card-body">
                        <h5 class="card-title">' . htmlspecialchars($course['name']) . '</h5>
                        <div class="d-flex justify-content-between mb-1">
                            <small class="text-muted">Progression des notes</small>
                            <small>' . $progressPercentage . '%</small>
                        </div>
                        <div class="progress mb-3" style="height: 6px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: ' . $progressPercentage . '%;" 
                                aria-valuenow="' . $progressPercentage . '" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <div class="small text-muted">
                                <i class="fas fa-users me-1"></i> ' . $course['student_count'] . ' élèves
                            </div>
                            <div class="small text-muted">
                                <i class="fas fa-check-circle me-1"></i> ' . $course['graded_count'] . ' notés
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-top-0 text-center">
                        <a href="course.php?course_id=' . htmlspecialchars($course['id']) . '" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-graduation-cap me-1"></i> Gérer les notes
                        </a>
                    </div>
                </div>
            </div>';
        }
        
        $content .= '</div>
            </div>
        </div>';
    } else {
        $content .= '<div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Aucun cours trouvé</strong> pour cette classe.
        </div>';
    }
}

// Ajouter du style CSS personnalisé
$content .= '<style>
    .hover-shadow:hover {
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
        transform: translateY(-2px);
        transition: all 0.3s ease;
    }
</style>';

// Inclure le template
include('templates/layout.php');
?>