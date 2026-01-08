<?php
// Inclure les fichiers nécessaires
include_once('main.php');
require_once('../../vendor/autoload.php'); // Pour PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['login_id'])) {
    header("Location: ../../index.php");
    exit();
}

$check = $_SESSION['login_id'];
$admin_name = $login_session;

// Récupérer les classes disponibles
$classes = [];
$sql = "SELECT * FROM class WHERE created_by = ? OR created_by = '21' ORDER BY name, section";
$stmt = $link->prepare($sql);
$stmt->bind_param("s", $check);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
}

// Récupérer les parents disponibles
$parents = [];
$sql = "SELECT id, fathername FROM parents WHERE created_by = ? ORDER BY fathername";
$stmt = $link->prepare($sql);
$stmt->bind_param("s", $check);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $parents[] = $row;
    }
}

$content = <<<CONTENT
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <!-- En-tête -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3><i class="fas fa-file-excel text-success me-2"></i>Importation d'Étudiants depuis Excel</h3>
                <a href="addStudent.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Retour
                </a>
            </div>

            <!-- Instructions -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Instructions</h5>
                </div>
                <div class="card-body">
                    <ol class="mb-3">
                        <li>Téléchargez le modèle Excel ci-dessous</li>
                        <li>Remplissez les informations des étudiants dans le fichier</li>
                        <li>Assurez-vous que les ID Parent et Classe existent dans le système</li>
                        <li>Téléversez le fichier complété</li>
                    </ol>
                    
                    <div class="alert alert-warning">
                        <strong><i class="fas fa-exclamation-triangle me-2"></i>Important :</strong>
                        <ul class="mb-0">
                            <li>Les ID étudiants doivent être uniques</li>
                            <li>Les ID Parent doivent correspondre à des parents existants</li>
                            <li>Les Classe doivent correspondre à des classes existantes (utilisez l'ID de la classe)</li>
                            <li>Le format de date doit être : AAAA-MM-JJ (ex: 2010-05-15)</li>
                            <li>Le genre doit être : Male ou Female</li>
                        </ul>
                    </div>

                    <a href="downloadStudentTemplate.php" class="btn btn-success">
                        <i class="fas fa-download me-2"></i>Télécharger le modèle Excel
                    </a>
                </div>
            </div>

            <!-- Listes de référence -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="fas fa-users me-2"></i>Parents Disponibles</h6>
                        </div>
                        <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>ID Parent</th>
                                        <th>Nom</th>
                                    </tr>
                                </thead>
                                <tbody>
CONTENT;

foreach($parents as $parent) {
    $content .= <<<CONTENT
                                    <tr>
                                        <td><code>{$parent['id']}</code></td>
                                        <td>{$parent['fathername']}</td>
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
                            <h6 class="mb-0"><i class="fas fa-school me-2"></i>Classes Disponibles</h6>
                        </div>
                        <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>ID Classe</th>
                                        <th>Nom - Section</th>
                                    </tr>
                                </thead>
                                <tbody>
CONTENT;

foreach($classes as $class) {
    $content .= <<<CONTENT
                                    <tr>
                                        <td><code>{$class['id']}</code></td>
                                        <td>{$class['name']} - {$class['section']}</td>
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

            <!-- Formulaire d'importation -->
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
                            <i class="fas fa-file-import me-2"></i>Importer les étudiants
                        </button>
                    </form>
                </div>
            </div>

            <!-- Zone de résultats -->
            <div id="importResults" class="mt-4"></div>
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
    
    // Afficher un indicateur de chargement
    const resultsDiv = document.getElementById('importResults');
    resultsDiv.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin me-2"></i>Importation en cours...</div>';
});
</script>
CONTENT;

include('templates/layout.php');

// Traitement de l'importation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import'])) {
    try {
        if (!isset($_FILES['excelFile']) || $_FILES['excelFile']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Erreur lors du téléversement du fichier");
        }

        $skipErrors = isset($_POST['skipErrors']);
        $filePath = $_FILES['excelFile']['tmp_name'];
        
        // Charger le fichier Excel
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        // Vérifier l'en-tête
        $header = array_shift($rows);
        
        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        $admin_id = $_SESSION['login_id'];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2; // +2 car on a enlevé l'en-tête et Excel commence à 1
            
            // Ignorer les lignes vides
            if (empty(array_filter($row))) {
                continue;
            }

            try {
                // Extraire les données selon l'ordre du fichier Excel
                $stuId = trim($row[0]);           // Colonne A: ID Étudiant
                $stuName = trim($row[1]);         // Colonne B: Nom Complet
                $plainPassword = trim($row[2]);   // Colonne C: Mot de passe
                $stuPhone = trim($row[3]);        // Colonne D: Téléphone
                $stuEmail = trim($row[4]);        // Colonne E: Email
                $stugender = trim($row[5]);       // Colonne F: Genre
                $stuDOB = trim($row[6]);          // Colonne G: Date de naissance
                // $row[7] est la date d'admission - ignorée car générée automatiquement
                $stuAddress = trim($row[8]);      // Colonne I: Adresse
                $stuParentId = trim($row[9]);     // Colonne J: ID Parent
                $stuClassId = trim($row[10]);     // Colonne K: Classe
                // $row[11] est la photo - ignorée pour l'instant

                // Validation basique
                if (empty($stuId) || empty($stuName) || empty($plainPassword)) {
                    throw new Exception("ID, Nom et Mot de passe sont obligatoires");
                }

                // Vérifier si l'ID existe déjà
                $check_sql = "SELECT userid FROM users WHERE userid = ?";
                $check_stmt = $link->prepare($check_sql);
                $check_stmt->bind_param("s", $stuId);
                $check_stmt->execute();
                if ($check_stmt->get_result()->num_rows > 0) {
                    throw new Exception("ID étudiant déjà existant");
                }

                // Vérifier la classe
                $check_class = "SELECT id FROM class WHERE id = ?";
                $class_stmt = $link->prepare($check_class);
                $class_stmt->bind_param("s", $stuClassId);
                $class_stmt->execute();
                if ($class_stmt->get_result()->num_rows === 0) {
                    throw new Exception("Classe inexistante (ID: $stuClassId)");
                }

                // Vérifier le parent
                $check_parent = "SELECT id FROM parents WHERE id = ?";
                $parent_stmt = $link->prepare($check_parent);
                $parent_stmt->bind_param("s", $stuParentId);
                $parent_stmt->execute();
                if ($parent_stmt->get_result()->num_rows === 0) {
                    throw new Exception("Parent inexistant (ID: $stuParentId)");
                }

                // Hasher le mot de passe
                $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
                $stuAddmissionDate = date('Y-m-d');

                // Début de la transaction
                $link->begin_transaction();

                // Insertion dans users
                $sql = "INSERT INTO users (userid, password, usertype) VALUES (?, ?, 'student')";
                $stmt = $link->prepare($sql);
                $stmt->bind_param("ss", $stuId, $plainPassword);
                $stmt->execute();

                // Insertion dans students
                $sql = "INSERT INTO students (id, name, password, phone, email, sex, dob, addmissiondate, address, parentid, classid, created_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $link->prepare($sql);
                $stmt->bind_param("ssssssssssss", 
                    $stuId, $stuName, $hashedPassword, $stuPhone, $stuEmail, 
                    $stugender, $stuDOB, $stuAddmissionDate, $stuAddress, 
                    $stuParentId, $stuClassId, $admin_id
                );
                $stmt->execute();

                $link->commit();
                $successCount++;

            } catch (Exception $e) {
                $link->rollback();
                $errorCount++;
                $errors[] = "Ligne $lineNumber ($stuId - $stuName): " . $e->getMessage();
                
                if (!$skipErrors) {
                    break;
                }
            }
        }

        // Afficher les résultats
        echo "<div class='container py-4'><div class='row justify-content-center'><div class='col-md-10'>";
        
        if ($successCount > 0) {
            echo "<div class='alert alert-success'>";
            echo "<h5><i class='fas fa-check-circle me-2'></i>Importation réussie !</h5>";
            echo "<p><strong>$successCount</strong> étudiant(s) importé(s) avec succès.</p>";
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

        echo "<a href='manageStudent.php' class='btn btn-primary'><i class='fas fa-list me-2'></i>Voir les étudiants</a> ";
        echo "<a href='importStudents.php' class='btn btn-secondary'><i class='fas fa-redo me-2'></i>Nouvelle importation</a>";
        echo "</div></div></div>";

    } catch (Exception $e) {
        echo "<div class='container py-4'><div class='row justify-content-center'><div class='col-md-10'>";
        echo "<div class='alert alert-danger'>";
        echo "<h5><i class='fas fa-times-circle me-2'></i>Erreur</h5>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
        echo "<a href='importStudents.php' class='btn btn-secondary'><i class='fas fa-arrow-left me-2'></i>Retour</a>";
        echo "</div></div></div>";
    }
    exit;
}
?>
