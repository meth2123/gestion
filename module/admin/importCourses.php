<?php
include_once('main.php');
require_once('../../vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_SESSION['login_id'])) {
    header("Location: ../../index.php");
    exit();
}

$check = $_SESSION['login_id'];
$admin_name = $login_session;

// Récupérer les classes disponibles
$classes = [];
$sql = "SELECT id, name FROM class WHERE created_by = ? ORDER BY name";
$stmt = $link->prepare($sql);
$stmt->bind_param("s", $check);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
}

// Récupérer les enseignants disponibles
$teachers = [];
$sql = "SELECT id, name FROM teachers WHERE created_by = ? ORDER BY name";
$stmt = $link->prepare($sql);
$stmt->bind_param("s", $check);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $teachers[] = $row;
    }
}

$content = <<<CONTENT
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3><i class="fas fa-file-excel text-success me-2"></i>Importation de Cours depuis Excel</h3>
                <a href="addCourse.php" class="btn btn-secondary">
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
                        <li>Remplissez les informations des cours</li>
                        <li>Assurez-vous que les ID Classe et ID Enseignant existent</li>
                        <li>Téléversez le fichier complété</li>
                    </ol>
                    
                    <div class="alert alert-warning">
                        <strong><i class="fas fa-exclamation-triangle me-2"></i>Important :</strong>
                        <ul class="mb-0">
                            <li>Les ID Classe doivent correspondre à des classes existantes</li>
                            <li>Les ID Enseignant doivent correspondre à des enseignants existants</li>
                        </ul>
                    </div>

                    <a href="downloadCourseTemplate.php" class="btn btn-success">
                        <i class="fas fa-download me-2"></i>Télécharger le modèle Excel
                    </a>
                </div>
            </div>

            <!-- Listes de référence -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="fas fa-school me-2"></i>Classes Disponibles</h6>
                        </div>
                        <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>ID Classe</th>
                                        <th>Nom</th>
                                    </tr>
                                </thead>
                                <tbody>
CONTENT;

foreach($classes as $class) {
    $content .= <<<CONTENT
                                    <tr>
                                        <td><code>{$class['id']}</code></td>
                                        <td>{$class['name']}</td>
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
                            <h6 class="mb-0"><i class="fas fa-chalkboard-teacher me-2"></i>Enseignants Disponibles</h6>
                        </div>
                        <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>ID Enseignant</th>
                                        <th>Nom</th>
                                    </tr>
                                </thead>
                                <tbody>
CONTENT;

foreach($teachers as $teacher) {
    $content .= <<<CONTENT
                                    <tr>
                                        <td><code>{$teacher['id']}</code></td>
                                        <td>{$teacher['name']}</td>
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
                            <i class="fas fa-file-import me-2"></i>Importer les cours
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
        $admin_id = $_SESSION['login_id'];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2;
            
            if (empty(array_filter($row))) {
                continue;
            }

            try {
                $courseName = trim($row[0]);     // Colonne A: Nom du cours
                $classId = trim($row[1]);        // Colonne B: ID Classe
                // Colonne C: Nom Classe (ignorée)
                $teacherId = trim($row[3]);      // Colonne D: ID Enseignant
                // Colonne E: Nom Enseignant (ignorée)

                if (empty($courseName) || empty($classId) || empty($teacherId)) {
                    throw new Exception("Nom du cours, ID Classe et ID Enseignant sont obligatoires");
                }

                // Vérifier si la classe existe
                $check_class = "SELECT id FROM class WHERE id = ?";
                $class_stmt = $link->prepare($check_class);
                $class_stmt->bind_param("s", $classId);
                $class_stmt->execute();
                if ($class_stmt->get_result()->num_rows === 0) {
                    throw new Exception("Classe inexistante (ID: $classId)");
                }

                // Vérifier si l'enseignant existe
                $check_teacher = "SELECT id FROM teachers WHERE id = ?";
                $teacher_stmt = $link->prepare($check_teacher);
                $teacher_stmt->bind_param("s", $teacherId);
                $teacher_stmt->execute();
                if ($teacher_stmt->get_result()->num_rows === 0) {
                    throw new Exception("Enseignant inexistant (ID: $teacherId)");
                }

                // Insertion du cours
                $sql = "INSERT INTO course (name, classid, teacherid, created_by) VALUES (?, ?, ?, ?)";
                $stmt = $link->prepare($sql);
                $stmt->bind_param("ssss", $courseName, $classId, $teacherId, $admin_id);
                $stmt->execute();

                $successCount++;

            } catch (Exception $e) {
                $errorCount++;
                $errors[] = "Ligne $lineNumber ($courseName): " . $e->getMessage();
                
                if (!$skipErrors) {
                    break;
                }
            }
        }

        echo "<div class='container py-4'><div class='row justify-content-center'><div class='col-md-10'>";
        
        if ($successCount > 0) {
            echo "<div class='alert alert-success'>";
            echo "<h5><i class='fas fa-check-circle me-2'></i>Importation réussie !</h5>";
            echo "<p><strong>$successCount</strong> cours importé(s) avec succès.</p>";
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

        echo "<a href='course.php' class='btn btn-primary'><i class='fas fa-list me-2'></i>Voir les cours</a> ";
        echo "<a href='importCourses.php' class='btn btn-secondary'><i class='fas fa-redo me-2'></i>Nouvelle importation</a>";
        echo "</div></div></div>";

    } catch (Exception $e) {
        echo "<div class='container py-4'><div class='row justify-content-center'><div class='col-md-10'>";
        echo "<div class='alert alert-danger'>";
        echo "<h5><i class='fas fa-times-circle me-2'></i>Erreur</h5>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
        echo "<a href='importCourses.php' class='btn btn-secondary'><i class='fas fa-arrow-left me-2'></i>Retour</a>";
        echo "</div></div></div>";
    }
    exit;
}
?>
