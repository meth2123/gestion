<?php
include_once('main.php');
require_once('../../vendor/autoload.php');
include_once('includes/auth_check.php');
include_once('../../service/db_utils.php');

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_SESSION['login_id'])) {
    header("Location: ../../index.php");
    exit();
}

$admin_id = $_SESSION['login_id'];
$admin_name = $login_session;

// Récupérer les classes disponibles
$classes = db_fetch_all(
    "SELECT * FROM class WHERE created_by = ? ORDER BY name",
    [$admin_id],
    's'
);

// Récupérer les cours disponibles
$courses = db_fetch_all(
    "SELECT id, name FROM course WHERE created_by = ? ORDER BY name",
    [$admin_id],
    's'
);

// Récupérer les enseignants disponibles
$teachers = db_fetch_all(
    "SELECT id, name FROM teachers WHERE created_by = ? ORDER BY name",
    [$admin_id],
    's'
);

// Récupérer les étudiants disponibles
$students = db_fetch_all(
    "SELECT id, name, classid FROM students WHERE created_by = ? ORDER BY name",
    [$admin_id],
    's'
);

$content = <<<CONTENT
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3><i class="fas fa-file-excel text-success me-2"></i>Importation de Notes depuis Excel</h3>
                <a href="manageGrades.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Retour
                </a>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Instructions</h5>
                </div>
                <div class="card-body">
                    <ol class="mb-3">
                        <li>Téléchargez le modèle Excel</li>
                        <li>Remplissez les notes pour chaque étudiant</li>
                        <li>Consultez les listes ci-dessous pour les ID</li>
                        <li>Téléversez le fichier complété</li>
                    </ol>
                    
                    <div class="alert alert-warning">
                        <strong><i class="fas fa-exclamation-triangle me-2"></i>Important :</strong>
                        <ul class="mb-0">
                            <li>Les ID Étudiant, Enseignant, Cours et Classe doivent exister</li>
                            <li>La note doit être entre 0 et 20</li>
                            <li>Le type doit être : devoir ou examen</li>
                            <li>Le semestre doit être : 1 ou 2</li>
                        </ul>
                    </div>

                    <a href="downloadGradeTemplate.php" class="btn btn-success">
                        <i class="fas fa-download me-2"></i>Télécharger le modèle Excel
                    </a>
                </div>
            </div>

            <!-- Listes de référence -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="fas fa-user-graduate me-2"></i>Étudiants Disponibles</h6>
                        </div>
                        <div class="card-body" style="max-height: 250px; overflow-y: auto;">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>ID Étudiant</th>
                                        <th>Nom</th>
                                    </tr>
                                </thead>
                                <tbody>
CONTENT;

foreach($students as $student) {
    $content .= <<<CONTENT
                                    <tr>
                                        <td><code>{$student['id']}</code></td>
                                        <td>{$student['name']}</td>
                                    </tr>
CONTENT;
}

$content .= <<<CONTENT
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="fas fa-book me-2"></i>Cours Disponibles</h6>
                        </div>
                        <div class="card-body" style="max-height: 250px; overflow-y: auto;">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>ID Cours</th>
                                        <th>Nom</th>
                                    </tr>
                                </thead>
                                <tbody>
CONTENT;

foreach($courses as $course) {
    $content .= <<<CONTENT
                                    <tr>
                                        <td><code>{$course['id']}</code></td>
                                        <td>{$course['name']}</td>
                                    </tr>
CONTENT;
}

$content .= <<<CONTENT
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-upload me-2"></i>Téléverser le fichier Excel</h5>
                </div>
                <div class="card-body">
                    <form action="" method="post" enctype="multipart/form-data" id="importForm">
                        <div class="mb-3">
                            <label for="excelFile" class="form-label">Fichier Excel (.xlsx, .xls)</label>
                            <input type="file" class="form-control" id="excelFile" name="excelFile" 
                                   accept=".xlsx,.xls" required>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="skipErrors" name="skipErrors" checked>
                            <label class="form-check-label" for="skipErrors">
                                Ignorer les lignes avec erreurs et continuer l'importation
                            </label>
                        </div>

                        <button type="submit" name="import" class="btn btn-primary">
                            <i class="fas fa-file-import me-2"></i>Importer les notes
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('importForm').addEventListener('submit', function(e) {
    const fileInput = document.getElementById('excelFile');
    if (fileInput.files.length === 0) {
        e.preventDefault();
        alert('Veuillez sélectionner un fichier Excel');
        return false;
    }
});
</script>
CONTENT;

include('templates/layout.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import'])) {
    try {
        if (!isset($_FILES['excelFile']) || $_FILES['excelFile']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Erreur lors du téléversement du fichier");
        }

        $skipErrors = isset($_POST['skipErrors']);
        $filePath = $_FILES['excelFile']['tmp_name'];
        
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        $header = array_shift($rows);
        
        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2;
            
            if (empty(array_filter($row))) {
                continue;
            }

            try {
                $studentId = trim($row[0]);      // Colonne A: ID Étudiant
                $teacherId = trim($row[1]);      // Colonne B: ID Enseignant
                $courseId = trim($row[2]);       // Colonne C: ID Cours
                $classId = trim($row[3]);        // Colonne D: ID Classe
                $gradeType = trim($row[4]);      // Colonne E: Type (devoir/examen)
                $gradeValue = trim($row[5]);     // Colonne F: Note
                $semester = trim($row[6]);       // Colonne G: Semestre

                if (empty($studentId) || empty($teacherId) || empty($courseId) || empty($classId) || empty($gradeType) || empty($gradeValue) || empty($semester)) {
                    throw new Exception("Tous les champs sont obligatoires");
                }

                if (!is_numeric($gradeValue) || $gradeValue < 0 || $gradeValue > 20) {
                    throw new Exception("La note doit être entre 0 et 20");
                }

                if (!in_array($gradeType, ['devoir', 'examen'])) {
                    throw new Exception("Le type doit être 'devoir' ou 'examen'");
                }

                if (!in_array($semester, ['1', '2'])) {
                    throw new Exception("Le semestre doit être 1 ou 2");
                }

                // Vérifier que l'étudiant existe
                $student_check = db_fetch_row(
                    "SELECT id FROM students WHERE id = ? AND created_by = ?",
                    [$studentId, $admin_id],
                    'ss'
                );
                if (!$student_check) {
                    throw new Exception("Étudiant inexistant (ID: $studentId)");
                }

                // Vérifier que l'enseignant existe
                $teacher_check = db_fetch_row(
                    "SELECT id FROM teachers WHERE id = ? AND created_by = ?",
                    [$teacherId, $admin_id],
                    'ss'
                );
                if (!$teacher_check) {
                    throw new Exception("Enseignant inexistant (ID: $teacherId)");
                }

                // Vérifier que le cours existe
                $course_check = db_fetch_row(
                    "SELECT id FROM course WHERE id = ? AND created_by = ?",
                    [$courseId, $admin_id],
                    'is'
                );
                if (!$course_check) {
                    throw new Exception("Cours inexistant (ID: $courseId)");
                }

                // Vérifier que la classe existe
                $class_check = db_fetch_row(
                    "SELECT id FROM class WHERE id = ? AND created_by = ?",
                    [$classId, $admin_id],
                    'ss'
                );
                if (!$class_check) {
                    throw new Exception("Classe inexistante (ID: $classId)");
                }

                // Déterminer le numéro de la note
                $gradeNumber = 1;
                if ($gradeType === 'devoir') {
                    // Compter combien de devoirs existent déjà pour cet étudiant/cours/semestre
                    $existing = db_fetch_row(
                        "SELECT MAX(CAST(SUBSTRING_INDEX(grade_type, '_', -1) AS UNSIGNED)) as max_num 
                        FROM student_teacher_course 
                        WHERE student_id = ? AND teacher_id = ? AND course_id = ? AND class_id = ? 
                        AND semester = ? AND grade_type LIKE 'devoir_%'",
                        [$studentId, $teacherId, $courseId, $classId, $semester],
                        'ssisi'
                    );
                    $gradeNumber = ($existing && $existing['max_num']) ? $existing['max_num'] + 1 : 1;
                }

                $gradeTypeColumn = $gradeType === 'examen' ? 'examen' : 'devoir_' . $gradeNumber;

                // Insérer ou mettre à jour la note
                db_query(
                    "INSERT INTO student_teacher_course 
                    (student_id, teacher_id, course_id, class_id, grade_type, grade_value, semester, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE grade_value = ?, updated_at = NOW()",
                    [$studentId, $teacherId, $courseId, $classId, $gradeTypeColumn, $gradeValue, $semester, $admin_id, $gradeValue],
                    'sssisdisi'
                );

                $successCount++;

            } catch (Exception $e) {
                $errorCount++;
                $errors[] = "Ligne $lineNumber (Étudiant: $studentId): " . $e->getMessage();
                
                if (!$skipErrors) {
                    break;
                }
            }
        }

        echo "<div class='container py-4'><div class='row justify-content-center'><div class='col-md-10'>";
        
        if ($successCount > 0) {
            echo "<div class='alert alert-success'>";
            echo "<h5><i class='fas fa-check-circle me-2'></i>Importation réussie !</h5>";
            echo "<p><strong>$successCount</strong> note(s) importée(s) avec succès.</p>";
            echo "</div>";
        }

        if ($errorCount > 0) {
            echo "<div class='alert alert-warning'>";
            echo "<h5><i class='fas fa-exclamation-triangle me-2'></i>Erreurs rencontrées</h5>";
            echo "<p><strong>$errorCount</strong> ligne(s) n'ont pas pu être importées :</p>";
            echo "<ul>";
            foreach ($errors as $error) {
                echo "<li>" . htmlspecialchars($error) . "</li>";
            }
            echo "</ul>";
            echo "</div>";
        }

        echo "<a href='manageGrades.php' class='btn btn-primary'><i class='fas fa-list me-2'></i>Voir les notes</a> ";
        echo "<a href='importGrades.php' class='btn btn-secondary'><i class='fas fa-redo me-2'></i>Nouvelle importation</a>";
        echo "</div></div></div>";

    } catch (Exception $e) {
        echo "<div class='container py-4'><div class='row justify-content-center'><div class='col-md-10'>";
        echo "<div class='alert alert-danger'>";
        echo "<h5><i class='fas fa-times-circle me-2'></i>Erreur</h5>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
        echo "<a href='importGrades.php' class='btn btn-secondary'><i class='fas fa-arrow-left me-2'></i>Retour</a>";
        echo "</div></div></div>";
    }
    exit;
}
?>
