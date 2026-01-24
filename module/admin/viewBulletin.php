<?php
include_once('main.php');
include_once('includes/auth_check.php');
include_once('../../service/db_utils.php');

// La vérification de la session admin est déjà faite dans auth_check.php

// Utiliser la connexion $link créée par main.php
global $link;
$conn = $link;
if ($conn === null || !$conn) {
    die('Erreur de connexion à la base de données. Vérifiez les variables d\'environnement Railway.');
}

// L'ID de l'administrateur et le login_session sont déjà définis dans auth_check.php
// $loged_user_name est défini dans le template layout.php
$student_id = $_GET['student'] ?? '';
$class_id = $_GET['class'] ?? '';
$period = $_GET['period'] ?? '1';

// Vérifier que l'admin a accès à cette classe
$class = db_fetch_row(
    "SELECT * FROM class WHERE id = ? AND (created_by = ? OR created_by = '21')",
    [$class_id, $admin_id],
    'ss'
);

if (!$class) {
    die("Accès non autorisé à cette classe.");
}

// Récupérer les informations de l'élève
$student = db_fetch_row(
    "SELECT * FROM students WHERE id = ? AND classid = ?",
    [$student_id, $class_id],
    'ss'
);

if (!$student) {
    die("Élève non trouvé dans cette classe.");
}

// Récupérer les notes de l'élève pour la période
// Vérifier si la table des coefficients spécifiques aux classes existe
$check_table = db_fetch_row(
    "SHOW TABLES LIKE 'class_course_coefficients'"
);

if ($check_table) {
    // Utiliser les coefficients spécifiques à la classe si disponibles
    $grades = db_fetch_all(
        "SELECT 
            c.name as course_name,
            COALESCE(ccc.coefficient, c.coefficient, 1) as course_coefficient,
            stc.grade_type,
            stc.grade_number,
            stc.grade,
            stc.coefficient as grade_coefficient,
            stc.semester,
            t.name as teacher_name
         FROM student_teacher_course stc
         JOIN course c ON stc.course_id = c.id
         JOIN teachers t ON stc.teacher_id = t.id
         LEFT JOIN class_course_coefficients ccc ON c.id = ccc.course_id AND ccc.class_id = ?
         WHERE stc.student_id = ?
         AND stc.class_id = ?
         AND stc.semester = ?
         ORDER BY c.name, stc.grade_type, stc.grade_number",
        [$class_id, $student_id, $class_id, $period],
        'ssss'
    );
} else {
    // Utiliser les coefficients généraux des cours si la table n'existe pas
    $grades = db_fetch_all(
        "SELECT 
            c.name as course_name,
            c.coefficient as course_coefficient,
            stc.grade_type,
            stc.grade_number,
            stc.grade,
            stc.coefficient as grade_coefficient,
            stc.semester,
            t.name as teacher_name
         FROM student_teacher_course stc
         JOIN course c ON stc.course_id = c.id
         JOIN teachers t ON stc.teacher_id = t.id
         WHERE stc.student_id = ?
         AND stc.class_id = ?
         AND stc.semester = ?
         ORDER BY c.name, stc.grade_type, stc.grade_number",
        [$student_id, $class_id, $period],
        'sss'
    );
}

// Récupérer les absences depuis student_attendance pour la période du bulletin
// Déterminer les dates de début et fin du semestre
$semester_start = null;
$semester_end = null;
$current_year = date('Y');
$current_month = (int)date('m');

if ($period == '1') {
    // Premier semestre : septembre de l'année précédente à janvier de l'année actuelle
    // Si on est entre janvier et août, le semestre 1 est de septembre de l'année précédente à janvier de l'année actuelle
    // Si on est entre septembre et décembre, le semestre 1 est de septembre de l'année actuelle à janvier de l'année suivante
    if ($current_month >= 9) {
        // On est en septembre-décembre, le semestre 1 commence en septembre de cette année
        $semester_start = $current_year . '-09-01';
        $semester_end = ($current_year + 1) . '-01-31';
    } else {
        // On est en janvier-août, le semestre 1 était de septembre de l'année précédente à janvier de cette année
        $semester_start = ($current_year - 1) . '-09-01';
        $semester_end = $current_year . '-01-31';
    }
} elseif ($period == '2') {
    // Deuxième semestre : février à juin
    // Si on est entre février et juin, le semestre 2 est de février à juin de cette année
    // Si on est entre juillet et janvier, le semestre 2 était de février à juin de cette année (ou l'année précédente si on est en janvier)
    if ($current_month >= 2 && $current_month <= 6) {
        $semester_start = $current_year . '-02-01';
        $semester_end = $current_year . '-06-30';
    } elseif ($current_month == 1) {
        // En janvier, le semestre 2 était de février à juin de l'année précédente
        $semester_start = ($current_year - 1) . '-02-01';
        $semester_end = ($current_year - 1) . '-06-30';
    } else {
        // En juillet-août, le semestre 2 était de février à juin de cette année
        $semester_start = $current_year . '-02-01';
        $semester_end = $current_year . '-06-30';
    }
} else {
    // Par défaut, utiliser les 30 derniers jours
    $semester_start = date('Y-m-d', strtotime('-30 days'));
    $semester_end = date('Y-m-d');
}

// Récupérer TOUTES les absences enregistrées pour cet élève dans cette classe pour la période du semestre
// Chaque absence enregistrée par l'enseignant doit apparaître dans le bulletin
// IMPORTANT: course_id est INT dans student_attendance mais peut être VARCHAR dans course, donc on utilise CAST
$absences_query = "
SELECT 
    DATE_FORMAT(sa.datetime, '%d/%m/%Y') as date,
    TIME(sa.datetime) as course_time,
    COALESCE(c.name, 'Cours supprimé') as course_name,
    COALESCE(t.name, 'Professeur non assigné') as teacher_name,
    sa.status,
    sa.comment,
    sa.datetime as raw_datetime,
    DATE(sa.datetime) as absence_date
FROM student_attendance sa
LEFT JOIN course c ON CAST(sa.course_id AS CHAR) = CAST(c.id AS CHAR)
LEFT JOIN teachers t ON CAST(c.teacherid AS CHAR) = CAST(t.id AS CHAR)
WHERE CAST(sa.student_id AS CHAR) = CAST(? AS CHAR)
AND CAST(sa.class_id AS CHAR) = CAST(? AS CHAR)
AND sa.status IN ('absent', 'late')
AND DATE(sa.datetime) >= ?
AND DATE(sa.datetime) <= ?
ORDER BY sa.datetime DESC";

// Diagnostic: Vérifier d'abord s'il y a des absences pour cet élève (sans filtre de date)
$debug_query = "
SELECT COUNT(*) as total_count,
       COUNT(CASE WHEN sa.status IN ('absent', 'late') THEN 1 END) as absent_count,
       MIN(DATE(sa.datetime)) as min_date,
       MAX(DATE(sa.datetime)) as max_date
FROM student_attendance sa
WHERE CAST(sa.student_id AS CHAR) = CAST(? AS CHAR)
AND CAST(sa.class_id AS CHAR) = CAST(? AS CHAR)";
$debug_stmt = $conn->prepare($debug_query);
if ($debug_stmt) {
    $debug_stmt->bind_param("ss", $student_id, $class_id);
    $debug_stmt->execute();
    $debug_result = $debug_stmt->get_result()->fetch_assoc();
    error_log("DEBUG Bulletin - Total enregistrements student_attendance pour élève $student_id, classe $class_id: " . ($debug_result['total_count'] ?? 0));
    error_log("DEBUG Bulletin - Absences/retards trouvés: " . ($debug_result['absent_count'] ?? 0));
    error_log("DEBUG Bulletin - Plage de dates dans la table: " . ($debug_result['min_date'] ?? 'N/A') . " à " . ($debug_result['max_date'] ?? 'N/A'));
    $debug_stmt->close();
}

$stmt = $conn->prepare($absences_query);
if (!$stmt) {
    error_log("Erreur de préparation de la requête absences: " . $conn->error);
    $absences = [];
} else {
    $stmt->bind_param("ssss", $student_id, $class_id, $semester_start, $semester_end);
    if (!$stmt->execute()) {
        error_log("Erreur d'exécution de la requête absences: " . $stmt->error);
        $absences = [];
    } else {
        $absences = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        error_log("Nombre d'absences récupérées pour l'élève $student_id, classe $class_id, période $semester_start à $semester_end: " . count($absences));
        
        // Log détaillé des premières absences trouvées
        if (count($absences) > 0) {
            error_log("Première absence trouvée: " . json_encode($absences[0]));
        } else {
            // Si aucune absence trouvée, vérifier sans filtre de date
            $no_date_query = "
            SELECT COUNT(*) as count, sa.status, DATE(sa.datetime) as date
            FROM student_attendance sa
            WHERE CAST(sa.student_id AS CHAR) = CAST(? AS CHAR)
            AND CAST(sa.class_id AS CHAR) = CAST(? AS CHAR)
            AND sa.status IN ('absent', 'late')
            GROUP BY sa.status, DATE(sa.datetime)
            ORDER BY date DESC
            LIMIT 5";
            $no_date_stmt = $conn->prepare($no_date_query);
            if ($no_date_stmt) {
                $no_date_stmt->bind_param("ss", $student_id, $class_id);
                $no_date_stmt->execute();
                $no_date_result = $no_date_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                error_log("DEBUG - Absences trouvées SANS filtre de date: " . json_encode($no_date_result));
                $no_date_stmt->close();
            }
        }
    }
    $stmt->close();
}

// Calculer les statistiques d'absence
// Chaque absence enregistrée par l'enseignant compte comme 1 absence
$total_absences = count($absences);
$justified_absences = 0;
$unjustified_absences = 0;

foreach ($absences as $absence) {
    // Si un commentaire existe, considérer comme justifié, sinon non justifié
    if (!empty($absence['comment']) && trim($absence['comment']) !== '') {
        $justified_absences++;
    } else {
        $unjustified_absences++;
    }
}

// Log pour déboguer
error_log("Bulletin - Élève: $student_id, Classe: $class_id, Période: $period");
error_log("Bulletin - Dates semestre: $semester_start à $semester_end");
error_log("Bulletin - Total absences trouvées: $total_absences (justifiées: $justified_absences, non justifiées: $unjustified_absences)");

// Pré-traitement pour éliminer les doublons d'examens
$filtered_grades = [];
$grade_keys = [];

// Créer un tableau temporaire pour identifier les examens avec notes
foreach ($grades as $grade) {
    $key = $grade['course_name'] . '-' . $grade['grade_type'] . '-' . $grade['grade_number'];
    if (!isset($grade_keys[$key]) || ($grade['grade'] && !$grade_keys[$key]['has_grade'])) {
        $grade_keys[$key] = [
            'index' => count($filtered_grades),
            'has_grade' => !empty($grade['grade'])
        ];
        $filtered_grades[] = $grade;
    }
}

// Calculer les moyennes par matière
$course_averages = [];

foreach ($filtered_grades as $grade) {
    // Ignorer les entrées sans note (sauf si c'est la seule entrée pour ce type d'évaluation)
    if (empty($grade['grade'])) {
        continue;
    }
    
    $course_name = $grade['course_name'];
    if (!isset($course_averages[$course_name])) {
        $course_averages[$course_name] = [
            'total_points' => 0,
            'total_coefficients' => 0,
            'course_coefficient' => $grade['course_coefficient'] ?? 1, // Coefficient de la matière
            'grades' => [],
            'grade_count' => 0 // Ajouter un compteur pour le nombre d'évaluations
        ];
    }
    $course_averages[$course_name]['grades'][] = $grade;
    $course_averages[$course_name]['grade_count']++; // Incrémenter le compteur
    // Utiliser le coefficient de la NOTE (grade_coefficient) pour chaque note
    $weighted_grade = $grade['grade'] * ($grade['grade_coefficient'] ?? 1);
    $course_averages[$course_name]['total_points'] += $weighted_grade;
    $course_averages[$course_name]['total_coefficients'] += ($grade['grade_coefficient'] ?? 1);
}

// Calculer la moyenne générale
$total_points = 0;
$total_course_coefficients = 0;
foreach ($course_averages as $course) {
    if ($course['total_coefficients'] > 0) {
        $course_average = $course['total_points'] / $course['total_coefficients'];
        $total_points += $course_average * $course['course_coefficient'];
        $total_course_coefficients += $course['course_coefficient'];
    }
}

$general_average = $total_course_coefficients > 0 ? $total_points / $total_course_coefficients : 0;

// Récupérer les moyennes de tous les élèves de la classe pour calculer le rang
$class_averages = db_fetch_all(
    "WITH student_grades AS (
        SELECT 
            s.id as student_id,
            s.name as student_name,
            stc.grade,
            stc.coefficient as grade_coefficient,
            c.coefficient as course_coefficient,
            c.name as course_name
        FROM students s
        JOIN student_teacher_course stc ON CAST(stc.student_id AS CHAR) = CAST(s.id AS CHAR)
        JOIN course c ON stc.course_id = c.id
        WHERE s.classid = ?
        AND stc.class_id = ?
        AND stc.semester = ?
    ),
    course_averages AS (
        SELECT 
            student_id,
            student_name,
            course_name,
            course_coefficient,
            ROUND(
                SUM(grade * grade_coefficient) / NULLIF(SUM(grade_coefficient), 0),
                2
            ) as course_average
        FROM student_grades
        GROUP BY student_id, student_name, course_name, course_coefficient
    )
    SELECT 
        student_id,
        student_name,
        ROUND(
            SUM(course_average * course_coefficient) / NULLIF(SUM(course_coefficient), 0),
            2
        ) as general_average
    FROM course_averages
    GROUP BY student_id, student_name
    ORDER BY general_average DESC",
    [$class_id, $class_id, $period],
    'sss'
);

// Calculer le rang de l'élève
$student_rank = 0;
$total_students = count($class_averages);
foreach ($class_averages as $index => $student) {
    if ($student['student_id'] === $student_id) {
        $student_rank = $index + 1;
        break;
    }
}

// Déterminer la mention selon les nouveaux critères
$mention = '';
if ($general_average >= 16) {
    $mention = 'Bien';
} elseif ($general_average >= 12) {
    $mention = 'Assez Bien';
} elseif ($general_average >= 9.99) {
    $mention = 'Passable';
} else {
    $mention = 'Insuffisant';
}

// Fonction utilitaire pour gérer les valeurs nulles avec htmlspecialchars
function safe_html($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Déterminer la couleur de la mention pour Bootstrap selon les nouveaux critères
$mention_color = '';
if ($general_average >= 16) {
    $mention_color = 'text-success'; // Bien (vert)
} elseif ($general_average >= 12) {
    $mention_color = 'text-primary'; // Assez bien (bleu)
} elseif ($general_average >= 9.99) {
    $mention_color = 'text-warning'; // Passable (jaune)
} else {
    $mention_color = 'text-danger'; // Insuffisant (rouge)
}

$content = '
<div class="container py-4">
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="text-center mb-4">
                <h1 class="h3 mb-2">Bulletin de Notes</h1>
                <p class="text-muted">Semestre ' . safe_html($period) . '</p>
            </div>

            <!-- Informations de l\'élève -->
            <div class="card mb-4 bg-light">
                <div class="card-body">
                    <h2 class="h5 mb-3">Informations de l\'élève</h2>
                    <div class="row">
                        <div class="col-md-6">
                            <p class="small text-muted mb-1">Nom</p>
                            <p class="fw-medium">' . safe_html($student['name'] ?? $student_id) . '</p>
                        </div>
                        <div class="col-md-6">
                            <p class="small text-muted mb-1">Classe</p>
                            <p class="fw-medium">' . safe_html($class['name'] ?? $class_id) . '</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes par matière -->
            <div class="mb-4">
                <h2 class="h5 mb-3">Notes par matière</h2>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Matière</th>
                                <th>Type</th>
                                <th>Note</th>
                                <th>Coefficient</th>
                                <th>Professeur</th>
                            </tr>
                        </thead>
                        <tbody>';

foreach ($course_averages as $course_name => $course) {
    $rowspan = count($course['grades']);
    $first = true;
    
    foreach ($course['grades'] as $grade) {
        $content .= '
            <tr>
                ' . ($first ? '<td rowspan="' . $rowspan . '" class="align-middle fw-medium">' . 
                    safe_html($course_name) . ' <span class="badge bg-secondary">coef ' . safe_html($course['course_coefficient']) . '</span></td>' : '') . '
                <td>' . 
                    ($grade['grade_type'] === 'devoir' ? 'Devoir ' : 'Examen ') . 
                    safe_html($grade['grade_number']) . '</td>
                <td>' . 
                    safe_html($grade['grade']) . '/20</td>
                <td>' . 
                    safe_html($grade['grade_coefficient']) . '</td>
                <td>' . 
                    safe_html($grade['teacher_name']) . '</td>
            </tr>';
        $first = false;
    }
    
    // Afficher la moyenne de la matière
    $course_average = $course['total_coefficients'] > 0 ? 
        round($course['total_points'] / $course['total_coefficients'], 2) : 0;
    
    // Calculer le total des coefficients (somme des coefficients des notes)
    $total_coefficient = $course['total_coefficients'];
    
    // Déterminer la classe de couleur pour la moyenne selon les nouveaux critères
    $avg_color_class = '';
    if ($course_average >= 16) {
        $avg_color_class = 'text-success fw-bold'; // Bien (vert)
    } elseif ($course_average >= 12) {
        $avg_color_class = 'text-primary fw-bold'; // Assez bien (bleu)
    } elseif ($course_average >= 9.99) {
        $avg_color_class = 'text-warning fw-bold'; // Passable (jaune)
    } else {
        $avg_color_class = 'text-danger fw-bold'; // Insuffisant (rouge)
    }
    
    $content .= '
        <tr class="table-light">
            <td colspan="2" class="fw-medium">
                Moyenne ' . safe_html($course_name) . '
            </td>
            <td class="' . $avg_color_class . '">' . 
                number_format($course_average, 2) . '/20</td>
            <td colspan="2" class="small">
                Coef. matière: ' . safe_html($course['course_coefficient']) . ' | 
                Coef. total: ' . number_format($total_coefficient, 2) . ' (somme coef notes)
            </td>
        </tr>';
}

$content .= '
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Résultats généraux -->
        <div class="card bg-light mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3">Résultats généraux</h2>
                <div class="row text-center">
                    <div class="col-md-3">
                        <p class="small text-muted mb-1">Moyenne générale</p>
                        <p class="display-6 fw-bold ' . 
                            ($general_average >= 16 ? 'text-success' : 
                            ($general_average >= 12 ? 'text-primary' : 
                            ($general_average >= 9.99 ? 'text-warning' : 'text-danger'))) . '">' . 
                            number_format($general_average, 2) . '/20</p>
                    </div>
                    <div class="col-md-3">
                        <p class="small text-muted mb-1">Rang</p>
                        <p class="display-6 fw-bold">' . $student_rank . '<span class="fs-6">/' . $total_students . '</span></p>
                    </div>
                    <div class="col-md-3">
                        <p class="small text-muted mb-1">Mention</p>
                        <p class="display-6 fw-bold ' . $mention_color . '">' . safe_html($mention) . '</p>
                    </div>
                    <div class="col-md-3">
                        <p class="small text-muted mb-1">Absences</p>
                        <p class="display-6 fw-bold text-danger">' . $total_absences . '</p>
                        <small class="text-muted">' . $justified_absences . ' justifiées / ' . $unjustified_absences . ' non justifiées</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section des absences -->
        <div class="card mb-4">
            <div class="card-header bg-danger bg-opacity-10">
                <h2 class="h5 mb-0 text-danger"><i class="fas fa-calendar-times me-2"></i>Absences</h2>
            </div>
            <div class="card-body">
                ' . ($total_absences > 0 ? '
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Heure</th>
                                <th>Matière</th>
                                <th>Professeur</th>
                                <th>Statut</th>
                                <th>Commentaire</th>
                            </tr>
                        </thead>
                        <tbody>' : '<p class="text-muted text-center mb-0"><i class="fas fa-check-circle me-2 text-success"></i>Aucune absence enregistrée pour ce semestre.</p>') . '';

if ($total_absences > 0) {
    foreach ($absences as $absence) {
        $status_badge = '';
        $status_text = '';
        if ($absence['status'] === 'absent') {
            $status_badge = 'bg-danger';
            $status_text = 'Absent';
        } elseif ($absence['status'] === 'late') {
            $status_badge = 'bg-warning';
            $status_text = 'En retard';
        }
        
        $justified_badge = !empty($absence['comment']) && trim($absence['comment']) !== '' 
            ? '<span class="badge bg-success">Justifiée</span>' 
            : '<span class="badge bg-secondary">Non justifiée</span>';
        
        $content .= '
                            <tr>
                                <td>' . safe_html($absence['date']) . '</td>
                                <td>' . safe_html($absence['course_time']) . '</td>
                                <td>' . safe_html($absence['course_name']) . '</td>
                                <td>' . safe_html($absence['teacher_name'] ?? 'N/A') . '</td>
                                <td>
                                    <span class="badge ' . $status_badge . '">' . safe_html($status_text) . '</span>
                                    ' . $justified_badge . '
                                </td>
                                <td>' . safe_html($absence['comment'] ?? '-') . '</td>
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

        <!-- Actions -->
        <div class="d-flex justify-content-between">
            <div>
                <a href="manageBulletins.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Retour
                </a>
            </div>
            <div>
                <a href="generateBulletin.php?student=' . htmlspecialchars($student_id) . 
                   '&class=' . htmlspecialchars($class_id) . 
                   '&period=' . htmlspecialchars($period) . '"
                   class="btn btn-outline-success me-2">
                   <i class="fas fa-file-pdf me-2"></i>Générer PDF
                </a>
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="fas fa-print me-2"></i>Imprimer
                </button>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    .container, .container * {
        visibility: visible;
    }
    .container {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    .btn, .d-flex.justify-content-between {
        display: none !important;
    }
}
</style>';

include('templates/layout.php');
?>
