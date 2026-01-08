<?php
include_once('main.php');
require_once('../../vendor/autoload.php');
require_once('../../db/config.php');

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_SESSION['login_id'])) {
    header("Location: ../../index.php");
    exit();
}

$check = $_SESSION['login_id'];
$admin_name = $login_session;
$conn = getDbConnection();

// Fonction pour générer l'ID parent
function generateParentId($conn, $mothername) {
    $mother_prefix = strtoupper(substr(trim($mothername), 0, 3));
    $sql = "SELECT id FROM parents WHERE id LIKE ? ORDER BY id DESC LIMIT 1";
    $pattern = $mother_prefix . '-PA%';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastId = $row['id'];
        $number = intval(substr($lastId, strrpos($lastId, '-') + 3)) + 1;
    } else {
        $number = 1;
    }
    
    return $mother_prefix . '-PA' . str_pad($number, 3, '0', STR_PAD_LEFT);
}

$content = <<<CONTENT
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3><i class="fas fa-file-excel text-success me-2"></i>Importation de Parents depuis Excel</h3>
                <a href="addParent.php" class="btn btn-secondary">
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
                        <li>Remplissez les informations des parents</li>
                        <li>Téléversez le fichier complété</li>
                    </ol>
                    
                    <div class="alert alert-warning">
                        <strong><i class="fas fa-exclamation-triangle me-2"></i>Important :</strong>
                        <ul class="mb-0">
                            <li>L'ID parent est généré automatiquement basé sur le nom de la mère</li>
                            <li>Le nom de la mère est obligatoire</li>
                        </ul>
                    </div>

                    <a href="downloadParentTemplate.php" class="btn btn-success">
                        <i class="fas fa-download me-2"></i>Télécharger le modèle Excel
                    </a>
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
                            <i class="fas fa-file-import me-2"></i>Importer les parents
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
                $password = trim($row[0]);
                $fathername = trim($row[1]);
                $mothername = trim($row[2]);
                $fatherphone = trim($row[3]);
                $motherphone = trim($row[4]);
                $address = trim($row[5]);

                if (empty($password) || empty($fathername) || empty($mothername) || empty($fatherphone)) {
                    throw new Exception("Mot de passe, nom du père, nom de la mère et téléphone du père sont obligatoires");
                }

                $parent_id = generateParentId($conn, $mothername);

                $conn->begin_transaction();

                $sql = "INSERT INTO users (userid, password, usertype) VALUES (?, ?, 'parent')";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ss", $parent_id, $password);
                $stmt->execute();

                $sql = "INSERT INTO parents (id, password, fathername, mothername, fatherphone, motherphone, address, created_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssss", $parent_id, $password, $fathername, $mothername, $fatherphone, $motherphone, $address, $admin_id);
                $stmt->execute();

                $conn->commit();
                $successCount++;

            } catch (Exception $e) {
                $conn->rollback();
                $errorCount++;
                $errors[] = "Ligne $lineNumber ($fathername - $mothername): " . $e->getMessage();
                
                if (!$skipErrors) {
                    break;
                }
            }
        }

        echo "<div class='container py-4'><div class='row justify-content-center'><div class='col-md-10'>";
        
        if ($successCount > 0) {
            echo "<div class='alert alert-success'>";
            echo "<h5><i class='fas fa-check-circle me-2'></i>Importation réussie !</h5>";
            echo "<p><strong>$successCount</strong> parent(s) importé(s) avec succès.</p>";
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

        echo "<a href='manageParent.php' class='btn btn-primary'><i class='fas fa-list me-2'></i>Voir les parents</a> ";
        echo "<a href='importParents.php' class='btn btn-secondary'><i class='fas fa-redo me-2'></i>Nouvelle importation</a>";
        echo "</div></div></div>";

    } catch (Exception $e) {
        echo "<div class='container py-4'><div class='row justify-content-center'><div class='col-md-10'>";
        echo "<div class='alert alert-danger'>";
        echo "<h5><i class='fas fa-times-circle me-2'></i>Erreur</h5>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
        echo "<a href='importParents.php' class='btn btn-secondary'><i class='fas fa-arrow-left me-2'></i>Retour</a>";
        echo "</div></div></div>";
    }
    exit;
}
?>
