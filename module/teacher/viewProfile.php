<?php
include_once('main.php');
include_once('../../service/db_utils.php');

// Vérification de la session
if (!isset($_SESSION['teacher_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Définir la variable check pour le template layout.php
$check = $_SESSION['teacher_id'];

// Récupération des informations de l'enseignant
$teacher = db_fetch_row(
    "SELECT * FROM teachers WHERE id = ?",
    [$check],
    's'
);

if (!$teacher) {
    $content = '<div class="alert alert-danger" role="alert">
                <h4 class="alert-heading">Erreur!</h4>
                <p>Enseignant non trouvé.</p>
              </div>';
    include('templates/layout.php');
    exit();
}

// Récupérer les cours enseignés par cet enseignant
// 1. Récupérer les cours assignés directement via course.teacherid
$direct_courses = db_fetch_all(
    "SELECT c.name as course_name, cl.name as class_name 
     FROM course c 
     JOIN class cl ON c.classid = cl.id 
     WHERE c.teacherid = ? 
     ORDER BY cl.name, c.name",
    [$check],
    's'
);

// 2. Récupérer les cours assignés via student_teacher_course
$stc_courses = db_fetch_all(
    "SELECT DISTINCT c.name as course_name, cl.name as class_name 
     FROM student_teacher_course stc 
     JOIN course c ON stc.course_id = c.id 
     JOIN class cl ON stc.class_id = cl.id 
     WHERE stc.teacher_id = ? 
     ORDER BY cl.name, c.name",
    [$check],
    's'
);

// Fusionner les deux résultats et éliminer les doublons
$courses = array_merge($direct_courses, $stc_courses);

// Créer un tableau temporaire pour stocker les cours uniques
$unique_courses = [];
$unique_keys = [];

// Parcourir tous les cours et ne garder que les combinaisons uniques de cours et classe
foreach ($courses as $course) {
    $key = $course['course_name'] . '_' . $course['class_name'];
    if (!in_array($key, $unique_keys)) {
        $unique_keys[] = $key;
        $unique_courses[] = $course;
    }
}

// Remplacer le tableau original par le tableau sans doublons
$courses = $unique_courses;

// Préparation du contenu pour le template
$content = '<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="row g-0">
                    <!-- Photo de profil -->
                    <div class="col-md-4 bg-light d-flex flex-column justify-content-center align-items-center p-4 text-center">
                        <div class="mb-3">
                            <img src="../../source/teacher.png" 
                                 alt="Photo de ' . htmlspecialchars($teacher['name']) . '" 
                                 class="rounded-circle img-thumbnail" style="width: 150px; height: 150px; object-fit: cover;">
                        </div>
                        <h3 class="h4 mb-1">' . htmlspecialchars($teacher['name']) . '</h3>
                        <p class="text-muted">Enseignant</p>
                        <div class="d-grid gap-2 mt-3">
                            <a href="updateTeacher.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-user-edit me-2"></i>Modifier mon profil
                            </a>
                        </div>
                    </div>
                    
                    <!-- Informations du profil -->
                    <div class="col-md-8 p-4">
                        <h4 class="card-title border-bottom pb-2 mb-3">Informations personnelles</h4>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <h6 class="text-muted mb-1">ID</h6>
                                    <p>' . htmlspecialchars($teacher['id']) . '</p>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <h6 class="text-muted mb-1">Téléphone</h6>
                                    <p>' . htmlspecialchars($teacher['phone']) . '</p>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <h6 class="text-muted mb-1">Email</h6>
                                    <p>' . htmlspecialchars($teacher['email']) . '</p>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <h6 class="text-muted mb-1">Genre</h6>
                                    <p>' . htmlspecialchars($teacher['sex']) . '</p>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <h6 class="text-muted mb-1">Date de naissance</h6>
                                    <p>' . htmlspecialchars($teacher['dob']) . '</p>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <h6 class="text-muted mb-1">Date d\'embauche</h6>
                                    <p>' . htmlspecialchars($teacher['hiredate']) . '</p>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <h6 class="text-muted mb-1">Salaire</h6>
                                    <p>' . number_format((float)$teacher['salary'], 2, ',', ' ') . ' €</p>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <h6 class="text-muted mb-1">Adresse</h6>
                                    <p>' . htmlspecialchars($teacher['address']) . '</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';

// Afficher les cours enseignés
if (!empty($courses)) {
    $content .= '<div class="col-md-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">Mes cours</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Cours</th>
                                <th>Classe</th>
                            </tr>
                        </thead>
                        <tbody>';
    
    foreach ($courses as $course) {
        $content .= '<tr>
                        <td>' . htmlspecialchars($course['course_name']) . '</td>
                        <td>' . htmlspecialchars($course['class_name']) . '</td>
                    </tr>';
    }
    
    $content .= '</tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>';
}

$content .= '</div>'; // Fermeture de la row principale

// Inclure le template
include('templates/layout.php');
?>