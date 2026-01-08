<?php
include_once('main.php');
require_once('../../vendor/autoload.php');
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
    "SELECT DISTINCT c.* 
     FROM class c 
     INNER JOIN students s ON c.id = s.classid 
     WHERE s.created_by = ? 
     ORDER BY c.name",
    [$admin_id],
    's'
);

// Récupérer tous les cours disponibles
$courses = db_fetch_all(
    "SELECT id, name, classid FROM course WHERE created_by = ? ORDER BY name",
    [$admin_id],
    's'
);

$content = <<<CONTENT
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3><i class="fas fa-file-excel text-success me-2"></i>Importation de Coefficients depuis Excel</h3>
                <a href="manageCoefficients.php" class="btn btn-secondary">
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
                        <li>Remplissez les coefficients pour chaque cours et classe</li>
                        <li>Consultez les listes ci-dessous pour les ID</li>
                        <li>Téléversez le fichier complété</li>
                    </ol>
                    
                    <div class="alert alert-warning">
                        <strong><i class="fas fa-exclamation-triangle me-2"></i>Important :</strong>
                        <ul class="mb-0">
                            <li>Les ID Classe et ID Cours doivent exister dans le système</li>
                            <li>Le coefficient doit être un nombre positif (ex: 1, 2, 3, 5, 6, 7, 1.5, 2.5)</li>
                            <li>Un même cours peut avoir des coefficients différents selon les classes</li>
                        </ul>
                    </div>

                    <a href="downloadCoefficientTemplate.php" class="btn btn-success">
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
                            <h6 class="mb-0"><i class="fas fa-book me-2"></i>Cours Disponibles</h6>
                        </div>
                        <div class="card-body" style="max-height: 300px; overflow-y: auto;">
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
                            <i class="fas fa-file-import me-2"></i>Importer les coefficients
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

        // Vérifier si la table existe, sinon la créer
        $check_table = db_fetch_row("SHOW TABLES LIKE 'class_course_coefficients'");
        if (!$check_table) {
            db_query(
                "CREATE TABLE class_course_coefficients (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    class_id VARCHAR(50) NOT NULL,
                    course_id INT NOT NULL,
                    coefficient DECIMAL(3,1) NOT NULL DEFAULT 1.0,
                    created_by VARCHAR(50) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_class_course (class_id, course_id)
                )"
            );
        }

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2;
            
            if (empty(array_filter($row))) {
                continue;
            }

            try {
                $courseId = trim($row[0]);       // Colonne A: ID Cours
                $classId = trim($row[1]);        // Colonne B: ID Classe
                $coefficient = trim($row[2]);    // Colonne C: Coefficient

                if (empty($classId) || empty($courseId) || empty($coefficient)) {
                    throw new Exception("Tous les champs sont obligatoires");
                }

                if (!is_numeric($coefficient) || $coefficient <= 0) {
                    throw new Exception("Le coefficient doit être un nombre positif");
                }

                // Vérifier que la classe existe
                $class_check = db_fetch_row(
                    "SELECT 1 FROM students WHERE classid = ? AND created_by = ? LIMIT 1",
                    [$classId, $admin_id],
                    'ss'
                );
                if (!$class_check) {
                    throw new Exception("Classe inexistante ou non accessible (ID: $classId)");
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

                // Insérer ou mettre à jour le coefficient
                db_query(
                    "INSERT INTO class_course_coefficients (class_id, course_id, coefficient, created_by)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE coefficient = ?, updated_at = NOW()",
                    [$classId, $courseId, $coefficient, $admin_id, $coefficient],
                    'sidsd'
                );

                $successCount++;

            } catch (Exception $e) {
                $errorCount++;
                $errors[] = "Ligne $lineNumber (Classe: $classId, Cours: $courseId): " . $e->getMessage();
                
                if (!$skipErrors) {
                    break;
                }
            }
        }

        echo "<div class='container py-4'><div class='row justify-content-center'><div class='col-md-10'>";
        
        if ($successCount > 0) {
            echo "<div class='alert alert-success'>";
            echo "<h5><i class='fas fa-check-circle me-2'></i>Importation réussie !</h5>";
            echo "<p><strong>$successCount</strong> coefficient(s) importé(s) avec succès.</p>";
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

        echo "<a href='manageCoefficients.php' class='btn btn-primary'><i class='fas fa-list me-2'></i>Voir les coefficients</a> ";
        echo "<a href='importCoefficients.php' class='btn btn-secondary'><i class='fas fa-redo me-2'></i>Nouvelle importation</a>";
        echo "</div></div></div>";

    } catch (Exception $e) {
        echo "<div class='container py-4'><div class='row justify-content-center'><div class='col-md-10'>";
        echo "<div class='alert alert-danger'>";
        echo "<h5><i class='fas fa-times-circle me-2'></i>Erreur</h5>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
        echo "<a href='importCoefficients.php' class='btn btn-secondary'><i class='fas fa-arrow-left me-2'></i>Retour</a>";
        echo "</div></div></div>";
    }
    exit;
}
?>
