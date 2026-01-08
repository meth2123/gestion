<?php
include_once('main.php');
require_once('../../vendor/autoload.php');
include_once('includes/auth_check.php');

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_SESSION['login_id'])) {
    header("Location: ../../index.php");
    exit();
}

$admin_id = $_SESSION['login_id'];
$admin_name = $login_session;

// Fonction pour générer un ID unique pour le personnel
function generateStaffId($link) {
    $prefix = "STF";
    $last_id_sql = "SELECT id FROM staff WHERE id LIKE 'STF%' ORDER BY id DESC LIMIT 1";
    $result = $link->query($last_id_sql);
    
    if ($result && $result->num_rows > 0) {
        $last_id = $result->fetch_assoc()['id'];
        $numeric_part = intval(substr($last_id, 3, 3));
        $numeric_part++;
    } else {
        $numeric_part = 1;
    }
    
    $letters = '';
    for ($i = 0; $i < 3; $i++) {
        $letters .= chr(rand(65, 90));
    }
    
    $formatted_number = str_pad($numeric_part, 3, '0', STR_PAD_LEFT);
    $new_id = $prefix . $formatted_number . $letters;
    
    $check_sql = "SELECT id FROM staff WHERE id = ?";
    $check_stmt = $link->prepare($check_sql);
    $check_stmt->bind_param("s", $new_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        return generateStaffId($link);
    }
    
    return $new_id;
}

$content = <<<CONTENT
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3><i class="fas fa-file-excel text-success me-2"></i>Importation de Personnel depuis Excel</h3>
                <a href="addStaff.php" class="btn btn-secondary">
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
                        <li>Remplissez les informations du personnel</li>
                        <li>Téléversez le fichier complété</li>
                    </ol>
                    
                    <div class="alert alert-warning">
                        <strong><i class="fas fa-exclamation-triangle me-2"></i>Important :</strong>
                        <ul class="mb-0">
                            <li>L'ID du personnel est généré automatiquement (format: STF001XXX)</li>
                            <li>Tous les champs sont obligatoires sauf l'adresse</li>
                            <li>Le genre doit être : male ou female</li>
                            <li>Les dates au format : AAAA-MM-JJ</li>
                        </ul>
                    </div>

                    <a href="downloadStaffTemplate.php" class="btn btn-success">
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
                            <i class="fas fa-file-import me-2"></i>Importer le personnel
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
                $name = trim($row[0]);          // Colonne A: Nom
                $email = trim($row[1]);         // Colonne B: Email
                $phone = trim($row[2]);         // Colonne C: Téléphone
                $address = trim($row[3]);       // Colonne D: Adresse
                $sex = trim($row[4]);           // Colonne E: Genre
                $dob = trim($row[5]);           // Colonne F: Date de naissance
                $salary = trim($row[6]);        // Colonne G: Salaire
                $password = trim($row[7]);      // Colonne H: Mot de passe

                if (empty($name) || empty($email) || empty($phone) || empty($sex) || empty($dob) || empty($salary) || empty($password)) {
                    throw new Exception("Tous les champs sont obligatoires sauf l'adresse");
                }

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Format d'email invalide");
                }

                if (!is_numeric($salary)) {
                    throw new Exception("Le salaire doit être un nombre");
                }

                // Vérifier si l'email existe déjà
                $check_sql = "SELECT id FROM staff WHERE email = ?";
                $check_stmt = $link->prepare($check_sql);
                $check_stmt->bind_param("s", $email);
                $check_stmt->execute();
                if ($check_stmt->get_result()->num_rows > 0) {
                    throw new Exception("Email déjà existant");
                }

                // Générer un ID unique
                $staff_id = generateStaffId($link);
                $hiredate = date('Y-m-d');

                // Début de la transaction
                $link->begin_transaction();

                // Insertion dans staff
                $sql = "INSERT INTO staff (id, name, email, phone, address, sex, dob, hiredate, salary, created_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $link->prepare($sql);
                $stmt->bind_param("ssssssssds", $staff_id, $name, $email, $phone, $address, $sex, $dob, $hiredate, $salary, $admin_id);
                $stmt->execute();

                // Insertion dans users
                $sql = "INSERT INTO users (userid, password, usertype) VALUES (?, ?, 'staff')";
                $stmt = $link->prepare($sql);
                $stmt->bind_param("ss", $staff_id, $password);
                $stmt->execute();

                $link->commit();
                $successCount++;

            } catch (Exception $e) {
                $link->rollback();
                $errorCount++;
                $errors[] = "Ligne $lineNumber ($name - $email): " . $e->getMessage();
                
                if (!$skipErrors) {
                    break;
                }
            }
        }

        echo "<div class='container py-4'><div class='row justify-content-center'><div class='col-md-10'>";
        
        if ($successCount > 0) {
            echo "<div class='alert alert-success'>";
            echo "<h5><i class='fas fa-check-circle me-2'></i>Importation réussie !</h5>";
            echo "<p><strong>$successCount</strong> membre(s) du personnel importé(s) avec succès.</p>";
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

        echo "<a href='manageStaff.php' class='btn btn-primary'><i class='fas fa-list me-2'></i>Voir le personnel</a> ";
        echo "<a href='importStaff.php' class='btn btn-secondary'><i class='fas fa-redo me-2'></i>Nouvelle importation</a>";
        echo "</div></div></div>";

    } catch (Exception $e) {
        echo "<div class='container py-4'><div class='row justify-content-center'><div class='col-md-10'>";
        echo "<div class='alert alert-danger'>";
        echo "<h5><i class='fas fa-times-circle me-2'></i>Erreur</h5>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
        echo "<a href='importStaff.php' class='btn btn-secondary'><i class='fas fa-arrow-left me-2'></i>Retour</a>";
        echo "</div></div></div>";
    }
    exit;
}
?>
