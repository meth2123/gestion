<?php
include_once('main.php');
include_once('../../service/db_utils.php');
include_once('../../service/course_filters.php');

// Vérification de la session (utilise la même méthode que main.php)
if (!isset($_SESSION['teacher_id'])) {
    header("Location: ../../index.php");
    exit();
}

$teacher_id = $_SESSION['teacher_id'];

// Définir la variable check pour le template layout.php
$check = $teacher_id;

// Récupérer les informations du professeur
$teacher_info = db_fetch_row(
    "SELECT * FROM teachers WHERE id = ?",
    [$teacher_id],
    's'
);

if (!$teacher_info) {
    $content = '<div class="alert alert-danger" role="alert">
        <h4 class="alert-heading">Erreur</h4>
        <p>Impossible de trouver les informations du professeur.</p>
        <hr>
        <p class="mb-0">Veuillez contacter l\'administrateur si vous pensez qu\'il s\'agit d\'une erreur.</p>
    </div>';
    include('templates/layout.php');
    exit();
}

// Vérifier si un ID de cours spécifique est demandé dans l'URL
// Accepter à la fois 'id' et 'course_id' pour la compatibilité
$specific_course_id = isset($_GET['id']) ? $_GET['id'] : (isset($_GET['course_id']) ? $_GET['course_id'] : null);

// Récupérer tous les cours assignés à ce professeur
if ($specific_course_id) {
    // Nouvelle requête qui récupère le cours spécifique avec toutes les classes auxquelles l'enseignant est assigné
    $courses = db_fetch_all(
        "SELECT DISTINCT c.id, c.name, stc.class_id as classid, cl.name as class_name 
         FROM course c 
         JOIN student_teacher_course stc ON stc.course_id = c.id
         JOIN class cl ON stc.class_id = cl.id
         WHERE c.id = ? AND stc.teacher_id = ?
         ORDER BY cl.name",
        [$specific_course_id, $teacher_id],
        'ss'
    );
    
    // Si aucun résultat n'est trouvé via student_teacher_course, essayer avec la méthode classique
    if (empty($courses)) {
        $courses = db_fetch_all(
            "SELECT DISTINCT c.*, cl.name as class_name 
             FROM course c 
             JOIN class cl ON c.classid = cl.id 
             WHERE c.id = ? AND c.teacherid = ?
             ORDER BY c.name",
            [$specific_course_id, $teacher_id],
            'ss'
        );
    }
    
    // Débogage - Enregistrer les informations dans le journal
    error_log("Course ID: $specific_course_id, Teacher ID: $teacher_id, Courses found: " . count($courses));
} else {
    // Sinon, récupérer tous les cours de l'enseignant (simplifié)
    $courses = db_fetch_all(
        "SELECT DISTINCT c.*, cl.name as class_name 
         FROM course c 
         JOIN class cl ON CONVERT(c.classid USING utf8mb4) = CONVERT(cl.id USING utf8mb4) 
         WHERE CONVERT(c.teacherid USING utf8mb4) = CONVERT(? USING utf8mb4)
         UNION
         SELECT DISTINCT c.*, cl.name as class_name 
         FROM course c 
         JOIN class cl ON CONVERT(c.classid USING utf8mb4) = CONVERT(cl.id USING utf8mb4) 
         JOIN student_teacher_course stc ON CONVERT(c.id USING utf8mb4) = CONVERT(stc.course_id USING utf8mb4) 
         WHERE CONVERT(stc.teacher_id USING utf8mb4) = CONVERT(? USING utf8mb4)
         ORDER BY name",
        [$teacher_id, $teacher_id],
        'ss'
    );
    
    // Débogage - Enregistrer les informations dans le journal
    error_log("Teacher ID: $teacher_id, All courses found: " . count($courses));
}

// Récupérer l'emploi du temps du professeur
$schedule = db_fetch_all(
    "SELECT cs.*, 
            c.name as subject_name,
            cl.name as class_name,
            ts.start_time,
            ts.end_time,
            CASE 
                WHEN cs.day_of_week = 'Lundi' THEN 1
                WHEN cs.day_of_week = 'Mardi' THEN 2
                WHEN cs.day_of_week = 'Mercredi' THEN 3
                WHEN cs.day_of_week = 'Jeudi' THEN 4
                WHEN cs.day_of_week = 'Vendredi' THEN 5
                WHEN cs.day_of_week = 'Samedi' THEN 6
                ELSE 7
            END as day_num,
            cs.day_of_week as day_name
     FROM class_schedule cs
     JOIN course c ON CONVERT(cs.subject_id USING utf8mb4) = CONVERT(c.id USING utf8mb4)
     JOIN class cl ON CONVERT(cs.class_id USING utf8mb4) = CONVERT(cl.id USING utf8mb4)
     JOIN time_slots ts ON cs.slot_id = ts.slot_id
     WHERE CONVERT(cs.teacher_id USING utf8mb4) = CONVERT(? USING utf8mb4)
     ORDER BY day_num, ts.start_time",
    [$teacher_id],
    's'
);

// Fonction utilitaire pour sécuriser l'affichage des valeurs
function safe_html($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$content = '
<div class="container-fluid">
    <div class="mb-4">
        <h1 class="h3 mb-2">Mon Emploi du Temps</h1>
        <p class="text-muted">Professeur : ' . safe_html($teacher_info['name']) . '</p>
    </div>';

// Organiser l'emploi du temps par jour
$schedule_by_day = [];
foreach ($schedule as $slot) {
    $day = $slot['day_num'];
    if (!isset($schedule_by_day[$day])) {
        $schedule_by_day[$day] = [];
    }
    $schedule_by_day[$day][] = $slot;
}

// Trier les jours
ksort($schedule_by_day);

// Afficher l'emploi du temps par jour
$content .= '
<div class="row">';

// Jours de la semaine
$days = [
    1 => 'Lundi',
    2 => 'Mardi',
    3 => 'Mercredi',
    4 => 'Jeudi',
    5 => 'Vendredi',
    6 => 'Samedi',
    7 => 'Dimanche'
];

foreach ($days as $day_num => $day_name) {
    $content .= '
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">' . $day_name . '</h5>
            </div>
            <div class="card-body">';
    
    if (isset($schedule_by_day[$day_num]) && !empty($schedule_by_day[$day_num])) {
        $content .= '
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Horaire</th>
                                <th>Matière</th>
                                <th>Classe</th>
                                <th>Salle</th>
                                <th>Semestre</th>
                                <th>Année académique</th>
                            </tr>
                        </thead>
                        <tbody>';
        
        foreach ($schedule_by_day[$day_num] as $slot) {
            $content .= '
                            <tr>
                                <td>' . safe_html($slot['start_time']) . ' - ' . safe_html($slot['end_time']) . '</td>
                                <td>' . safe_html($slot['subject_name']) . '</td>
                                <td>' . safe_html($slot['class_name']) . '</td>
                                <td>' . safe_html($slot['room'] ?? 'Non spécifiée') . '</td>
                                <td>' . safe_html($slot['semester']) . '</td>
                                <td>' . safe_html($slot['academic_year'] ?? '') . '</td>
                            </tr>';
        }
        
        $content .= '
                        </tbody>
                    </table>
                </div>';
    } else {
        $content .= '<div class="alert alert-info">Aucun cours ce jour</div>';
    }
    
    $content .= '
            </div>
        </div>
    </div>';
}

$content .= '
</div>';

// Afficher un message si l'emploi du temps est vide
if (empty($schedule)) {
    $content = str_replace('<div class="row"></div>', '<div class="alert alert-info mb-4">Aucun emploi du temps n\'a été défini pour vous. Veuillez contacter l\'administrateur.</div>', $content);
}

// Afficher la liste des cours assignés
$content .= '
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="card-title mb-0">Mes Cours</h5>
    </div>
    <div class="card-body">';

if (empty($courses)) {
    $content .= '<div class="alert alert-info">Aucun cours ne vous est assigné.</div>';
} else {
    $content .= '
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="table-light">
                <tr>
                    <th>Cours</th>
                    <th>Classe</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($courses as $course) {
        // Vérifier si l'enseignant est autorisé à accéder aux détails de ce cours
        $can_access_details = can_teacher_access_course_details($teacher_id, $course['id']);
        
        $content .= '
            <tr>
                <td>' . safe_html($course['name']) . '</td>
                <td>' . safe_html($course['class_name']) . '</td>
                <td>';
                
        if ($can_access_details) {
            // Inclure l'ID de classe dans le lien pour différencier les cours par classe
            $class_id_param = isset($course['classid']) ? '&class_id=' . safe_html($course['classid']) : '';
            $content .= '<a href="manageGrades.php?course_id=' . safe_html($course['id']) . $class_id_param . '" class="btn btn-sm btn-primary">Détails</a>';
        } else {
            $content .= '<span class="badge bg-secondary">Non assigné</span>';
        }
        
        $content .= '</td>
            </tr>';
    }
    
    $content .= '
            </tbody>
        </table>
    </div>';
}

$content .= '
    </div>
</div>
</div>';

// Utiliser le template enseignant
include('templates/layout.php');
?>
