<?php
include_once('main.php');
include_once('../../service/db_utils.php');

// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Vérification de la session
if (!isset($_SESSION['login_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Vérifier si l'utilisateur est un administrateur
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../../access_denied.php");
    exit();
}

// Récupérer l'ID de l'administrateur
$admin_id = $_SESSION['login_id'];

// Connexion à la base de données
require_once('../../db/config.php');
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$success_message = '';
$error_message = '';

// Traitement du formulaire d'assignation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_teacher'])) {
        $teacher_id = $_POST['teacher_id'] ?? '';
        $course_id = $_POST['course_id'] ?? '';
        $class_id = $_POST['class_id'] ?? '';
        $student_ids = $_POST['student_ids'] ?? [];
        
        if ($teacher_id && $course_id && $class_id && !empty($student_ids)) {
            try {
                // Supprimer les anciennes assignations pour cette combinaison
                db_query(
                    "DELETE FROM student_teacher_course 
                    WHERE teacher_id = ? AND course_id = ? AND class_id = ?",
                    [$teacher_id, $course_id, $class_id],
                    'sss'
                );
                
                // Insérer les nouvelles assignations
                foreach ($student_ids as $student_id) {
                    db_query(
                        "INSERT INTO student_teacher_course (student_id, teacher_id, course_id, class_id, created_at) 
                        VALUES (?, ?, ?, ?, NOW())",
                        [$student_id, $teacher_id, $course_id, $class_id],
                        'ssss'
                    );
                }
                
                $success_message = "L'enseignant a été assigné avec succès aux élèves pour ce cours et cette classe.";
            } catch (Exception $e) {
                $error_message = "Erreur lors de l'assignation: " . $e->getMessage();
            }
        } else {
            $error_message = "Veuillez sélectionner un enseignant, un cours, une classe et au moins un élève.";
        }
    }
}

// Récupérer la liste des classes
$classes = db_fetch_all(
    "SELECT id, name FROM class ORDER BY name",
    [],
    ''
);

// Récupérer la liste des enseignants
$teachers = db_fetch_all(
    "SELECT id, name FROM teachers ORDER BY name",
    [],
    ''
);

// Récupérer la liste des cours
$courses = db_fetch_all(
    "SELECT id, name FROM course ORDER BY name",
    [],
    ''
);

// Sélection de classe et récupération des élèves
$selected_class = $_GET['class_id'] ?? '';
$students = [];
$current_assignments = [];

if ($selected_class) {
    // Récupérer les élèves de la classe sélectionnée
    $students = db_fetch_all(
        "SELECT id, name FROM students WHERE classid = ? ORDER BY name",
        [$selected_class],
        's'
    );
    
    // Récupérer les assignations actuelles pour cette classe
    $current_assignments = db_fetch_all(
        "SELECT DISTINCT student_id, teacher_id, course_id, class_id 
         FROM student_teacher_course 
         WHERE class_id = ?",
        [$selected_class],
        's'
    );
}

// Préparation du contenu pour le template
$content = '<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Assignation des Enseignants aux Cours et Élèves</h1>
            </div>';

// Messages d'alerte
if ($success_message) {
    $content .= '<div class="alert alert-success alert-dismissible fade show" role="alert">
        ' . htmlspecialchars($success_message) . '
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
}

if ($error_message) {
    $content .= '<div class="alert alert-danger alert-dismissible fade show" role="alert">
        ' . htmlspecialchars($error_message) . '
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
}

// Formulaire de sélection de classe
$content .= '<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3">Sélectionner une classe</h2>
        <form method="GET">
            <div class="row">
                <div class="col-md-8">
                    <div class="form-group">
                        <select name="class_id" class="form-select" onchange="this.form.submit()">
                            <option value="">Sélectionner une classe</option>';

foreach ($classes as $class) {
    $selected = ($selected_class == $class['id']) ? 'selected' : '';
    $content .= '<option value="' . htmlspecialchars($class['id']) . '" ' . $selected . '>' . 
                htmlspecialchars($class['name']) . '</option>';
}

$content .= '</select>
                    </div>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">Afficher</button>
                </div>
            </div>
        </form>
    </div>
</div>';

// Formulaire d'assignation si une classe est sélectionnée
if ($selected_class && !empty($students)) {
    $content .= '<div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Assigner un enseignant à un cours pour les élèves</h2>
            <form method="POST">
                <input type="hidden" name="class_id" value="' . htmlspecialchars($selected_class) . '">
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="teacher-select" class="form-label">Enseignant</label>
                        <select name="teacher_id" id="teacher-select" class="form-select" required>
                            <option value="">Sélectionner un enseignant</option>';
                            
    foreach ($teachers as $teacher) {
        $content .= '<option value="' . htmlspecialchars($teacher['id']) . '">' . 
                  htmlspecialchars($teacher['name']) . '</option>';
    }
    
    $content .= '</select>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="course-select" class="form-label">Cours</label>
                        <select name="course_id" id="course-select" class="form-select" required>
                            <option value="">Sélectionner un cours</option>';
                            
    foreach ($courses as $course) {
        $content .= '<option value="' . htmlspecialchars($course['id']) . '">' . 
                  htmlspecialchars($course['name']) . '</option>';
    }
    
    $content .= '</select>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Élèves</label>
                    <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                        <div class="row">';
                        
    foreach ($students as $student) {
        $content .= '<div class="col-md-4 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="student_ids[]" 
                                   value="' . htmlspecialchars($student['id']) . '" id="student-' . htmlspecialchars($student['id']) . '">
                            <label class="form-check-label" for="student-' . htmlspecialchars($student['id']) . '">
                                ' . htmlspecialchars($student['name']) . '
                            </label>
                        </div>
                    </div>';
    }
    
    $content .= '</div>
                    </div>
                </div>
                
                <div class="d-grid">
                    <button type="submit" name="assign_teacher" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Enregistrer les assignations
                    </button>
                </div>
            </form>
        </div>
    </div>';
    
    // Tableau des assignations actuelles
    $content .= '<div class="card shadow-sm">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Assignations actuelles</h2>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Élève</th>
                            <th>Enseignant</th>
                            <th>Cours</th>
                        </tr>
                    </thead>
                    <tbody>';
    
    if (empty($current_assignments)) {
        $content .= '<tr>
                        <td colspan="3" class="text-center py-4 text-muted">
                            Aucune assignation trouvée pour cette classe.
                        </td>
                    </tr>';
    } else {
        foreach ($current_assignments as $assignment) {
            $student_result = db_fetch_row(
                "SELECT name FROM students WHERE id = ?",
                [$assignment['student_id']],
                's'
            );
            $student_name = $student_result ? $student_result['name'] : 'Non défini';
            
            $teacher_result = db_fetch_row(
                "SELECT name FROM teachers WHERE id = ?",
                [$assignment['teacher_id']],
                's'
            );
            $teacher_name = $teacher_result ? $teacher_result['name'] : 'Non défini';
            
            $course_result = db_fetch_row(
                "SELECT name FROM course WHERE id = ?",
                [$assignment['course_id']],
                's'
            );
            $course_name = $course_result ? $course_result['name'] : 'Non défini';
            
            $content .= '<tr>
                        <td>' . htmlspecialchars($student_name) . '</td>
                        <td>' . htmlspecialchars($teacher_name) . '</td>
                        <td>' . htmlspecialchars($course_name) . '</td>
                    </tr>';
        }
    }
    
    $content .= '</tbody>
                </table>
            </div>
        </div>
    </div>';
} elseif ($selected_class && empty($students)) {
    $content .= '<div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>Aucun élève trouvé dans cette classe.
    </div>';
}

$content .= '</div>
    </div>
</div>';

// Inclure le template
include('templates/layout.php');
?>