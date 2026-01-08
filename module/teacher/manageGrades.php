<?php
include_once('main.php');
include_once('../../service/db_utils.php');
include_once('../../service/course_filters.php');

// Vérification de la session
if (!isset($_SESSION['teacher_id'])) {
    header("Location: ../../index.php");
    exit();
}

$teacher_id = $_SESSION['teacher_id'];

// Récupération des informations du professeur
$teacher_info = db_fetch_row(
    "SELECT * FROM teachers WHERE id = ?",
    [$teacher_id],
    's'
);

if (!$teacher_info) {
    header("Location: index.php?error=teacher_not_found");
    exit();
}

// Récupération du cours sélectionné
$course_id = isset($_GET['course_id']) ? $_GET['course_id'] : '';
$class_id = isset($_GET['class_id']) ? $_GET['class_id'] : null; // Récupérer l'ID de classe depuis l'URL
$selected_semester = isset($_GET['semester']) ? $_GET['semester'] : '1';
$error_message = '';
$success_message = '';

// Validation du cours sélectionné
$course_check = false;
$course_info = null;

// Aucun débogage nécessaire
$debug_info = "";

if ($course_id) {
    // Débogage
    error_log("manageGrades.php - Vérification des droits d'accès - Teacher ID: $teacher_id, Course ID: $course_id, Class ID: $class_id");
    
    $has_access = false;
    
    // Si un ID de classe spécifique est fourni dans l'URL
    if ($class_id) {
        // Débogage
        error_log("manageGrades.php - Vérification stricte des droits d'accès - Teacher ID: $teacher_id, Course ID: $course_id, Class ID: $class_id");
        
        // Vérifier l'accès direct avec la classe spécifiée (utiliser CONVERT pour éviter les problèmes de collation)
        $direct_course = db_fetch_row(
            "SELECT 1 FROM course 
             WHERE CONVERT(id USING utf8mb4) = CONVERT(? USING utf8mb4) 
             AND CONVERT(teacherid USING utf8mb4) = CONVERT(? USING utf8mb4) 
             AND CONVERT(classid USING utf8mb4) = CONVERT(? USING utf8mb4)",
            [$course_id, $teacher_id, $class_id],
            'sss'
        );
        
        // Vérifier l'accès via student_teacher_course avec la classe spécifiée
        $stc_assignment = db_fetch_row(
            "SELECT 1 FROM student_teacher_course 
             WHERE CONVERT(teacher_id USING utf8mb4) = CONVERT(? USING utf8mb4) 
             AND CONVERT(course_id USING utf8mb4) = CONVERT(? USING utf8mb4) 
             AND CONVERT(class_id USING utf8mb4) = CONVERT(? USING utf8mb4)",
            [$teacher_id, $course_id, $class_id],
            'sss'
        );
        
        // Accès accordé si l'une des deux vérifications est positive
        $has_access = ($direct_course || $stc_assignment);
        
        // Débogage des résultats
        error_log("manageGrades.php - Résultat de la vérification stricte: " . ($has_access ? "Accès autorisé" : "Accès refusé"));
        error_log("manageGrades.php - Direct course: " . ($direct_course ? "Oui" : "Non") . ", STC assignment: " . ($stc_assignment ? "Oui" : "Non"));
    } else {
        // Si aucun ID de classe n'est spécifié, récupérer les classes potentielles
        $class_assignments = db_fetch_all(
            "SELECT DISTINCT class_id FROM student_teacher_course 
             WHERE teacher_id = ? AND course_id = ?",
            [$teacher_id, $course_id],
            'ss'
        );
        
        // Vérifier l'accès direct (via course.teacherid)
        $direct_course = db_fetch_row(
            "SELECT classid FROM course WHERE id = ? AND teacherid = ?",
            [$course_id, $teacher_id],
            'ss'
        );
        
        // Vérifier l'accès via course.teacherid
        if ($direct_course) {
            $has_access = true;
            $class_id = $direct_course['classid'];
        } 
        // Sinon, vérifier l'accès via student_teacher_course
        elseif (!empty($class_assignments)) {
            $has_access = true;
            // Utiliser la première classe assignée
            $class_id = $class_assignments[0]['class_id'];
        }
    }
    
    error_log("manageGrades.php - Résultat de la vérification: " . ($has_access ? "Accès autorisé" : "Accès refusé"));
    
    if (!$has_access) {
        $error_message = "Vous n'avez pas accès à ce cours.";
        $course_id = '';
        $course_check = false;
        error_log("manageGrades.php - Accès refusé - Redirection vers la page d'erreur");
    } else {
        // Récupérer les informations du cours en tenant compte de la classe spécifiée
        if ($class_id) {
            // Vérification stricte : récupérer le cours uniquement si l'enseignant y est assigné pour cette classe spécifique
            // Essayer d'abord via student_teacher_course avec la classe spécifiée
            $course_info = db_fetch_row(
                "SELECT c.*, cl.name as class_name, stc.class_id as classid 
                 FROM course c 
                 JOIN student_teacher_course stc ON CONVERT(c.id USING utf8mb4) = CONVERT(stc.course_id USING utf8mb4)
                 JOIN class cl ON CONVERT(stc.class_id USING utf8mb4) = CONVERT(cl.id USING utf8mb4)
                 WHERE CONVERT(c.id USING utf8mb4) = CONVERT(? USING utf8mb4)
                   AND CONVERT(stc.teacher_id USING utf8mb4) = CONVERT(? USING utf8mb4)
                   AND CONVERT(stc.class_id USING utf8mb4) = CONVERT(? USING utf8mb4)
                 LIMIT 1",
                [$course_id, $teacher_id, $class_id],
                'sss'
            );
            
            // Si aucun résultat via student_teacher_course, essayer via l'assignation directe dans course
            if (!$course_info) {
                $course_info = db_fetch_row(
                    "SELECT c.*, cl.name as class_name 
                     FROM course c 
                     JOIN class cl ON CONVERT(c.classid USING utf8mb4) = CONVERT(cl.id USING utf8mb4)
                     WHERE CONVERT(c.id USING utf8mb4) = CONVERT(? USING utf8mb4)
                       AND CONVERT(c.teacherid USING utf8mb4) = CONVERT(? USING utf8mb4)
                       AND CONVERT(c.classid USING utf8mb4) = CONVERT(? USING utf8mb4)",
                    [$course_id, $teacher_id, $class_id],
                    'sss'
                );
            }
            
            // Débogage
            error_log("manageGrades.php - Récupération du cours avec classe spécifiée - Résultat: " . ($course_info ? "Trouvé" : "Non trouvé"));
        } else {
            // Si aucune classe spécifiée, utiliser la méthode standard
            $course_info = db_fetch_row(
                "SELECT c.*, cl.name as class_name 
                 FROM course c 
                 JOIN class cl ON CONVERT(c.classid USING utf8mb4) = CONVERT(cl.id USING utf8mb4)
                 WHERE CONVERT(c.id USING utf8mb4) = CONVERT(? USING utf8mb4)
                   AND CONVERT(c.teacherid USING utf8mb4) = CONVERT(? USING utf8mb4)",
                [$course_id, $teacher_id],
                'ss'
            );
            
            // Si le cours n'est pas trouvé directement, vérifier les assignations via student_teacher_course
            if (!$course_info) {
                $course_info = db_fetch_row(
                    "SELECT c.*, cl.name as class_name, stc.class_id as classid 
                     FROM course c 
                     JOIN student_teacher_course stc ON CONVERT(c.id USING utf8mb4) = CONVERT(stc.course_id USING utf8mb4)
                     JOIN class cl ON CONVERT(stc.class_id USING utf8mb4) = CONVERT(cl.id USING utf8mb4)
                     WHERE CONVERT(c.id USING utf8mb4) = CONVERT(? USING utf8mb4)
                       AND CONVERT(stc.teacher_id USING utf8mb4) = CONVERT(? USING utf8mb4)
                     LIMIT 1",
                    [$course_id, $teacher_id],
                    'ss'
                );
            }
        }
        
        if ($course_info) {
            $course_check = true;
        } else {
            $error_message = "Informations du cours introuvables.";
            $course_id = '';
        }
    }
}

// Récupération des élèves et leurs notes
$students = [];
$students_data = [];

if ($course_check) {
    // Récupérer tous les élèves inscrits au cours pour la classe spécifiée
    if (isset($course_info['classid'])) {
        // Débogage
        error_log("manageGrades.php - Récupération des élèves pour Course ID: $course_id, Class ID: {$course_info['classid']}");
        
        // Débogage : Afficher les paramètres de recherche
        error_log("manageGrades.php - Paramètres de recherche - Course ID: $course_id, Teacher ID: $teacher_id, Class ID: {$course_info['classid']}");
        
        // SOLUTION ULTRA STRICTE : Ne récupérer que les élèves qui sont inscrits au cours pour la classe spécifiée
        // Cette requête garantit que seuls les élèves ayant une association explicite avec ce cours, cet enseignant et cette classe sont récupérés
        // Utilisation de CONVERT pour résoudre les problèmes de collation
        $query = "SELECT DISTINCT s.*, stc.class_id as stc_class_id 
             FROM students s
             JOIN student_teacher_course stc ON CONVERT(s.id USING utf8mb4) = CONVERT(stc.student_id USING utf8mb4)
             WHERE CONVERT(stc.course_id USING utf8mb4) = CONVERT(? USING utf8mb4) 
               AND CONVERT(stc.teacher_id USING utf8mb4) = CONVERT(? USING utf8mb4) 
               AND CONVERT(stc.class_id USING utf8mb4) = CONVERT(? USING utf8mb4)
             GROUP BY s.id";
        
        error_log("manageGrades.php - Requête SQL ultra stricte: $query");
        error_log("manageGrades.php - Paramètres: " . json_encode([$course_id, $teacher_id, $course_info['classid']]));
        
        $students_data = db_fetch_all(
            $query . " ORDER BY s.name",
            [$course_id, $teacher_id, $course_info['classid']],
            'sss'
        );
        
        // Débogage : Afficher les élèves trouvés
        error_log("manageGrades.php - Nombre d'élèves trouvés: " . count($students_data));
        foreach ($students_data as $student) {
            error_log("manageGrades.php - Élève VALIDÉ - ID: {$student['id']}, Nom: {$student['name']}, Class ID: {$student['stc_class_id']}");
        }
        
        // Si aucun élève n'est trouvé via student_teacher_course, nous ne devons pas utiliser de méthode de secours
        // car cela pourrait afficher des élèves qui ne sont pas réellement inscrits à ce cours/classe
        if (empty($students_data)) {
            // Débogage : Méthodes alternatives
            error_log("manageGrades.php - Aucun élève trouvé via student_teacher_course, essai des méthodes alternatives");
            
            // 1. Vérifier si des élèves ont été spécifiquement assignés à cette combinaison cours/classe
            $query1 = "SELECT DISTINCT s.*, scc.class_id as source_class_id 
                 FROM students s
                 JOIN student_class_course scc ON s.id = scc.student_id
                 WHERE CONVERT(scc.class_id USING utf8mb4) = CONVERT(? USING utf8mb4) 
                   AND CONVERT(scc.course_id USING utf8mb4) = CONVERT(? USING utf8mb4)";
                   
            error_log("manageGrades.php - Méthode 1 (student_class_course) - Requête: $query1");
            error_log("manageGrades.php - Méthode 1 - Paramètres: " . json_encode([$course_info['classid'], $course_id]));
            
            $students_data = db_fetch_all(
                $query1 . " ORDER BY s.name",
                [$course_info['classid'], $course_id],
                'ss'
            );
            
            // Débogage des résultats
            if (!empty($students_data)) {
                error_log("manageGrades.php - Élèves trouvés via student_class_course: " . count($students_data));
                foreach ($students_data as $student) {
                    error_log("manageGrades.php - Élève trouvé via student_class_course - ID: {$student['id']}, Nom: {$student['name']}, Class ID: {$student['source_class_id']}");
                }
            } else {
                error_log("manageGrades.php - Aucun élève trouvé via student_class_course");
            }
            
            // 2. Si toujours aucun élève, essayer avec la table d'inscription des élèves (student_enrollment)
            if (empty($students_data)) {
                $query2 = "SELECT DISTINCT s.*, se.class_id as source_class_id 
                     FROM students s
                     JOIN student_enrollment se ON s.id = se.student_id
                     WHERE CONVERT(se.class_id USING utf8mb4) = CONVERT(? USING utf8mb4)";
                     
                error_log("manageGrades.php - Méthode 2 (student_enrollment) - Requête: $query2");
                error_log("manageGrades.php - Méthode 2 - Paramètres: " . json_encode([$course_info['classid']]));
                
                $students_data = db_fetch_all(
                    $query2 . " ORDER BY s.name",
                    [$course_info['classid']],
                    's'
                );
                
                // Débogage des résultats
                if (!empty($students_data)) {
                    error_log("manageGrades.php - Élèves trouvés via student_enrollment: " . count($students_data));
                    foreach ($students_data as $student) {
                        error_log("manageGrades.php - Élève trouvé via student_enrollment - ID: {$student['id']}, Nom: {$student['name']}, Class ID: {$student['source_class_id']}");
                    }
                } else {
                    error_log("manageGrades.php - Aucun élève trouvé via student_enrollment");
                }
            }
            
            // 3. En dernier recours, utiliser le champ classid de la table students
            if (empty($students_data)) {
                $query3 = "SELECT *, classid as source_class_id FROM students 
                     WHERE CONVERT(classid USING utf8mb4) = CONVERT(? USING utf8mb4)";
                     
                error_log("manageGrades.php - Méthode 3 (students.classid) - Requête: $query3");
                error_log("manageGrades.php - Méthode 3 - Paramètres: " . json_encode([$course_info['classid']]));
                
                $students_data = db_fetch_all(
                    $query3 . " ORDER BY name",
                    [$course_info['classid']],
                    's'
                );
                
                // Débogage des résultats
                if (!empty($students_data)) {
                    error_log("manageGrades.php - Élèves trouvés via students.classid: " . count($students_data));
                    foreach ($students_data as $student) {
                        error_log("manageGrades.php - Élève trouvé via students.classid - ID: {$student['id']}, Nom: {$student['name']}, Class ID: {$student['source_class_id']}");
                    }
                } else {
                    error_log("manageGrades.php - Aucun élève trouvé via students.classid");
                }
            }
            
            error_log("manageGrades.php - Utilisation de méthodes alternatives pour récupérer les élèves de la classe {$course_info['classid']} pour le cours $course_id");
        }
    } else {
        // Cas improbable : aucune classe spécifiée
        error_log("manageGrades.php - ERREUR : Aucune classe spécifiée pour le cours $course_id");
        $students_data = [];
    }
    
    // Récupérer les coefficients des notes pour la classe
    $grade_coefficients = [];
    if (isset($course_info['classid'])) {
        $coefficients_query = "SELECT * FROM grade_coefficients WHERE class_id = ?";
        $coefficients_result = db_fetch_all($coefficients_query, [$course_info['classid']], 's');
        
        foreach ($coefficients_result as $coef) {
            $grade_type = $coef['grade_type'];
            $grade_number = $coef['grade_number'];
            $key = $grade_type . ($grade_number > 0 ? $grade_number : '');
            $grade_coefficients[$key] = (float)$coef['coefficient'];
        }
    }
    
    // Si aucun coefficient n'est défini, utiliser les valeurs par défaut
    if (empty($grade_coefficients)) {
        $grade_coefficients = [
            'devoir1' => 1.0,
            'devoir2' => 1.0,
            'examen' => 2.0
        ];
    }
    
    // Récupérer les notes existantes pour chaque élève
    foreach ($students_data as $student) {
        $student_grades = [];
        
        // Utiliser la classe correcte pour récupérer les notes
        $grades = db_fetch_all(
            "SELECT * FROM student_teacher_course 
             WHERE CONVERT(student_id USING utf8mb4) = CONVERT(? USING utf8mb4) 
             AND CONVERT(teacher_id USING utf8mb4) = CONVERT(? USING utf8mb4) 
             AND CONVERT(course_id USING utf8mb4) = CONVERT(? USING utf8mb4) 
             AND CONVERT(class_id USING utf8mb4) = CONVERT(? USING utf8mb4) 
             AND CONVERT(semester USING utf8mb4) = CONVERT(? USING utf8mb4)
             ORDER BY grade_type, grade_number",
            [$student['id'], $teacher_id, $course_id, $course_info['classid'], $selected_semester],
            'sssss'
        );
        
        foreach ($grades as $grade) {
            // Ne pas inclure les notes qui ont été supprimées (mises à NULL)
            if ($grade['grade'] !== null) {
                $key = $grade['grade_type'];
                if ($grade['grade_number'] > 0) {
                    $key .= $grade['grade_number'];
                }
                $student_grades[$key] = $grade;
            }
        }
        
        $students[] = [
            'student_id' => $student['id'],
            'student_name' => $student['name'],
            'grades' => $student_grades
        ];
    }
}

// Traitement du formulaire de notes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_grades'])) {
    try {
        foreach ($_POST['grades'] as $student_id => $grades) {
            foreach ($grades as $type => $grade) {
                if (is_numeric($grade)) {
                    // Déterminer le type et le numéro de la note
                    $grade_type = '';
                    $grade_number = 0;
                    
                    if (strpos($type, 'devoir') === 0) {
                        $grade_type = 'devoir';
                        $grade_number = substr($type, 6);
                    } elseif ($type === 'examen') {
                        $grade_type = 'examen';
                        $grade_number = 1;
                    }
                    
                    if ($grade_type) {
                        // Vérifier si la note existe déjà
                        $existing_grade = db_fetch_row(
                            "SELECT * FROM student_teacher_course 
                             WHERE CONVERT(student_id USING utf8mb4) = CONVERT(? USING utf8mb4) 
                             AND CONVERT(teacher_id USING utf8mb4) = CONVERT(? USING utf8mb4) 
                             AND CONVERT(course_id USING utf8mb4) = CONVERT(? USING utf8mb4) 
                             AND CONVERT(class_id USING utf8mb4) = CONVERT(? USING utf8mb4) 
                             AND CONVERT(grade_type USING utf8mb4) = CONVERT(? USING utf8mb4) 
                             AND grade_number = ? 
                             AND CONVERT(semester USING utf8mb4) = CONVERT(? USING utf8mb4)",
                            [$student_id, $teacher_id, $course_id, $course_info['classid'], $grade_type, $grade_number, $selected_semester],
                            'sssssds'
                        );
                        
                        if ($existing_grade) {
                            // Mettre à jour la note existante
                            db_execute(
                                "UPDATE student_teacher_course 
                                 SET grade = ?,
                                     updated_at = NOW() 
                                 WHERE id = ?",
                                [$grade, $existing_grade['id']],
                                'ds'
                            );
                        } else {
                            // Insérer une nouvelle note
                            db_execute(
                                "INSERT INTO student_teacher_course 
                                 (student_id, teacher_id, course_id, class_id, grade_type, grade_number, grade, semester, created_at, updated_at) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                                [$student_id, $teacher_id, $course_id, $course_info['classid'], $grade_type, $grade_number, $grade, $selected_semester],
                                'sssssdss'
                            );
                        }
                    }
                }
            }
        }
        $success_message = "Les notes ont été enregistrées avec succès.";
    } catch (Exception $e) {
        $error_message = "Une erreur est survenue lors de l'enregistrement des notes: " . $e->getMessage();
    }
}

// Traitement du formulaire de mise à jour d'une note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_grade'])) {
    try {
        $grade_id = isset($_POST['edit_grade_id']) ? $_POST['edit_grade_id'] : '';
        $grade_value = isset($_POST['edit_grade_value']) ? $_POST['edit_grade_value'] : '';
        
        if ($grade_id && is_numeric($grade_value)) {
            // Vérifier que la note appartient bien à cet enseignant
            $grade_check = db_fetch_row(
                "SELECT * FROM student_teacher_course WHERE id = ? AND CONVERT(teacher_id USING utf8mb4) = CONVERT(? USING utf8mb4)",
                [$grade_id, $teacher_id],
                'is'
            );
            
            if ($grade_check) {
                db_execute(
                    "UPDATE student_teacher_course SET grade = ?, updated_at = NOW() WHERE id = ?",
                    [$grade_value, $grade_id],
                    'di'
                );
                $success_message = "La note a été mise à jour avec succès.";
            } else {
                $error_message = "Vous n'avez pas accès à cette note.";
            }
        } else {
            $error_message = "Données invalides pour la mise à jour de la note.";
        }
    } catch (Exception $e) {
        $error_message = "Une erreur est survenue lors de la mise à jour de la note: " . $e->getMessage();
    }
}

// Traitement de la suppression d'une note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_grade'])) {
    try {
        $grade_id = isset($_POST['delete_grade_id']) ? $_POST['delete_grade_id'] : '';
        
        if ($grade_id) {
            // Vérifier que la note appartient bien à cet enseignant
            $grade_check = db_fetch_row(
                "SELECT * FROM student_teacher_course WHERE id = ? AND CONVERT(teacher_id USING utf8mb4) = CONVERT(? USING utf8mb4)",
                [$grade_id, $teacher_id],
                'is'
            );
            
            if ($grade_check) {
                // Mettre à jour l'enregistrement pour supprimer uniquement la note au lieu de supprimer l'association complète
                db_execute(
                    "UPDATE student_teacher_course SET grade = NULL WHERE id = ?",
                    [$grade_id],
                    'i'
                );
                $success_message = "La note a été supprimée avec succès.";
            } else {
                $error_message = "Vous n'avez pas accès à cette note.";
            }
        } else {
            $error_message = "Données invalides pour la suppression de la note.";
        }
    } catch (Exception $e) {
        $error_message = "Une erreur est survenue lors de la suppression de la note: " . $e->getMessage();
    }
}
?>
<?php
// Commencer à capturer le contenu dans une variable
ob_start();
?>

<div class="row mb-4">
    <div class="col">
        <h1 class="h3">Gestion des Notes</h1>
        <p class="text-muted">Gérez les notes de vos élèves pour le cours sélectionné.</p>
    </div>
</div>

<?php if ($error_message): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<?php if ($success_message): ?>
    <div class="alert alert-success" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
    </div>
<?php endif; ?>

<!-- Affichage des informations de débogage -->
<?php echo $debug_info; ?>
        
        <?php if ($course_check): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($course_info['name']); ?></h5>
                        <span class="badge bg-primary"><?php echo htmlspecialchars($course_info['class_name']); ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <form method="GET" class="d-flex align-items-center">
                                <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($course_id); ?>">
                                <label for="semester" class="me-2">Semestre:</label>
                                <select id="semester" name="semester" class="form-select me-2" style="width: auto;">
                                    <option value="1" <?php echo $selected_semester === '1' ? 'selected' : ''; ?>>Semestre 1</option>
                                    <option value="2" <?php echo $selected_semester === '2' ? 'selected' : ''; ?>>Semestre 2</option>
                                    <option value="3" <?php echo $selected_semester === '3' ? 'selected' : ''; ?>>Semestre 3</option>
                                </select>
                                <button type="submit" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-filter me-1"></i>Filtrer
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <?php if (!empty($students)): ?>
                        <form method="POST">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Élève</th>
                                            <th>Devoir 1</th>
                                            <th>Devoir 2</th>
                                            <th>Examen</th>
                                            <th>Moyenne</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $student): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                                <td>
                                                    <input type="number" 
                                                           name="grades[<?php echo $student['student_id']; ?>][devoir1]" 
                                                           value="<?php echo isset($student['grades']['devoir1']) ? $student['grades']['devoir1']['grade'] : ''; ?>" 
                                                           class="form-control" 
                                                           min="0" 
                                                           max="20" 
                                                           step="0.01">
                                                </td>
                                                <td>
                                                    <input type="number" 
                                                           name="grades[<?php echo $student['student_id']; ?>][devoir2]" 
                                                           value="<?php echo isset($student['grades']['devoir2']) ? $student['grades']['devoir2']['grade'] : ''; ?>" 
                                                           class="form-control" 
                                                           min="0" 
                                                           max="20" 
                                                           step="0.01">
                                                </td>
                                                <td>
                                                    <input type="number" 
                                                           name="grades[<?php echo $student['student_id']; ?>][examen]" 
                                                           value="<?php echo isset($student['grades']['examen']) ? $student['grades']['examen']['grade'] : ''; ?>" 
                                                           class="form-control" 
                                                           min="0" 
                                                           max="20" 
                                                           step="0.01">
                                                </td>
                                                <td>
                                                    <?php
                                                    // Récupérer toutes les notes de l'élève
                                                    $student_grades = $student['grades'];
                                                    
                                                    // Vérifier si la table des coefficients spécifiques aux classes existe
                                                    $check_table = db_fetch_row(
                                                        "SHOW TABLES LIKE 'class_course_coefficients'"
                                                    );
                                                    
                                                    // Récupérer le coefficient spécifique à la classe si disponible
                                                    if ($check_table && isset($class_id)) {
                                                        $class_coefficient = db_fetch_row(
                                                            "SELECT coefficient FROM class_course_coefficients WHERE course_id = ? AND class_id = ?",
                                                            [$course_id, $class_id],
                                                            'ss'
                                                        );
                                                        
                                                        // Utiliser le coefficient spécifique à la classe s'il existe, sinon utiliser celui du cours
                                                        $course_coefficient = isset($class_coefficient['coefficient']) ? 
                                                            (float)$class_coefficient['coefficient'] : 
                                                            (isset($course_info['coefficient']) ? (float)$course_info['coefficient'] : 1.0);
                                                    } else {
                                                        // Utiliser le coefficient général du cours
                                                        $course_coefficient = isset($course_info['coefficient']) ? (float)$course_info['coefficient'] : 1.0;
                                                    }
                                                    
                                                    $has_grades = !empty($student_grades);
                                                    
                                                    if ($has_grades) {
                                                        $weighted_sum = 0;
                                                        $total_coefficient = 0;
                                                        
                                                        // Calculer la moyenne pondérée en utilisant les coefficients
                                                        foreach ($student_grades as $grade_key => $grade_data) {
                                                            $grade_value = (float)$grade_data['grade'];
                                                            
                                                            // Utiliser le coefficient spécifique ou la valeur par défaut
                                                            $coefficient = isset($grade_coefficients[$grade_key]) ? $grade_coefficients[$grade_key] : 1.0;
                                                            
                                                            $weighted_sum += $grade_value * $coefficient;
                                                            $total_coefficient += $coefficient;
                                                        }
                                                        
                                                        // Calculer la moyenne pondérée
                                                        $average = $total_coefficient > 0 ? $weighted_sum / $total_coefficient : 0;
                                                        
                                                        // Afficher la moyenne avec le coefficient de la matière
                                                        echo number_format($average, 2) . '/20';
                                                        echo ' <span class="text-muted small">(Coef. ' . $course_coefficient . ')</span>';
                                                    } else {
                                                        echo '-';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" name="submit_grades" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Enregistrer les notes
                                </button>
                            </div>
                        </form>
                        
                        <!-- Historique des notes -->
                        <div class="mt-5">
                            <h4 class="h5 mb-3">Historique des notes</h4>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Élève</th>
                                            <th>Type</th>
                                            <th>Numéro</th>
                                            <th>Note</th>
                                            <th>Dernière mise à jour</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $student): ?>
                                            <?php foreach ($student['grades'] as $type => $grade): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                                    <td>
                                                        <?php 
                                                        echo $grade['grade_type'] === 'devoir' ? 'Devoir' : 'Examen'; 
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        echo $grade['grade_type'] === 'devoir' ? $grade['grade_number'] : '-'; 
                                                        ?>
                                                    </td>
                                                    <td><?php echo isset($grade['grade']) && $grade['grade'] !== null ? number_format((float)$grade['grade'], 2) . '/20' : '-'; ?></td>
                                                    <td><?php echo isset($grade['updated_at']) && $grade['updated_at'] ? date('d/m/Y H:i', strtotime($grade['updated_at'])) : date('d/m/Y H:i'); ?></td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <button type="button" 
                                                                    onclick="openEditModal('<?php echo isset($grade['id']) ? $grade['id'] : ''; ?>', '<?php echo isset($grade['grade']) ? $grade['grade'] : '0'; ?>')" 
                                                                    class="btn btn-outline-primary">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button type="button" 
                                                                    onclick="openDeleteModal('<?php echo isset($grade['id']) ? $grade['id'] : ''; ?>')" 
                                                                    class="btn btn-outline-danger">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info" role="alert">
                            <i class="fas fa-info-circle me-2"></i>Aucun élève n'est inscrit à ce cours pour le moment.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info" role="alert">
                <i class="fas fa-info-circle me-2"></i>Veuillez sélectionner un cours pour commencer.
            </div>
        <?php endif; ?>
        
        <!-- Modal pour modifier une note -->
        <div class="modal fade" id="editGradeModal" tabindex="-1" aria-labelledby="editGradeModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editGradeModalLabel">Modifier la note</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" id="editGradeForm">
                        <div class="modal-body">
                            <input type="hidden" id="editGradeId" name="edit_grade_id">
                            <div class="mb-3">
                                <label for="editGradeValue" class="form-label">Note</label>
                                <input type="number" 
                                       id="editGradeValue" 
                                       name="edit_grade_value" 
                                       min="0" 
                                       max="20" 
                                       step="0.01" 
                                       class="form-control">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" name="update_grade" class="btn btn-primary">Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Modal pour supprimer une note -->
        <div class="modal fade" id="deleteGradeModal" tabindex="-1" aria-labelledby="deleteGradeModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteGradeModalLabel">Confirmer la suppression</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" id="deleteGradeForm">
                        <div class="modal-body">
                            <input type="hidden" id="deleteGradeId" name="delete_grade_id">
                            <p>Êtes-vous sûr de vouloir supprimer cette note ? Cette action est irréversible.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" name="delete_grade" class="btn btn-danger">Supprimer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

<script>
    // Modal pour éditer une note
    function openEditModal(gradeId, gradeValue) {
        document.getElementById('editGradeId').value = gradeId;
        document.getElementById('editGradeValue').value = gradeValue;
        
        // Utiliser l'API Bootstrap pour ouvrir la modal
        var myModal = new bootstrap.Modal(document.getElementById('editGradeModal'));
        myModal.show();
    }
    
    // Modal pour supprimer une note
    function openDeleteModal(gradeId) {
        document.getElementById('deleteGradeId').value = gradeId;
        
        // Utiliser l'API Bootstrap pour ouvrir la modal
        var myModal = new bootstrap.Modal(document.getElementById('deleteGradeModal'));
        myModal.show();
    }
</script>

<?php
// Récupérer le contenu capturé et l'assigner à la variable $content
$content = ob_get_clean();

// Inclure le template qui utilisera la variable $content
include('templates/layout.php');
?>