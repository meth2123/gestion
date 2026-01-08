<?php
// Inclure les fichiers nécessaires
include_once('main.php');
require_once('../../vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\IOFactory;

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['login_id'])) {
    header("Location: ../../index.php");
    exit();
}

$check = $_SESSION['login_id'];
$admin_name = $login_session;

$content = <<<CONTENT
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <!-- En-tête -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3><i class="fas fa-file-excel text-success me-2"></i>Importation d'Enseignants depuis Excel</h3>
                <a href="addTeacher.php" class="btn btn-secondary">
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
                        <li>Remplissez les informations des enseignants dans le fichier</li>
                        <li>Téléversez le fichier complété</li>
                    </ol>
                    
                    <div class="alert alert-warning">
                        <strong><i class="fas fa-exclamation-triangle me-2"></i>Important :</strong>
                        <ul class="mb-0">
                            <li>L'ID enseignant est généré automatiquement (format: TE-XXX-9999)</li>
                            <li>Les emails doivent être uniques</li>
                            <li>Le format de date doit être : AAAA-MM-JJ (ex: 1985-05-15)</li>
                            <li>Le genre doit être : male ou female (en minuscules)</li>
                            <li>Le salaire doit être un nombre (ex: 250000)</li>
                        </ul>
                    </div>

                    <a href="downloadTeacherTemplate.php" class="btn btn-success">
                        <i class="fas fa-download me-2"></i>Télécharger le modèle Excel
                    </a>
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
                            <i class="fas fa-file-import me-2"></i>Importer les enseignants
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
                $name = trim($row[0]);          // Colonne A: Nom
                $email = trim($row[1]);         // Colonne B: Email
                $phone = trim($row[2]);         // Colonne C: Téléphone
                $gender = trim($row[3]);        // Colonne D: Genre
                $dob = trim($row[4]);           // Colonne E: Date de naissance
                $password = trim($row[5]);      // Colonne F: Mot de passe
                $hiredate = trim($row[6]);      // Colonne G: Date d'embauche
                $salary = trim($row[7]);        // Colonne H: Salaire
                $address = trim($row[8]);       // Colonne I: Adresse

                // Validation basique
                if (empty($name) || empty($email) || empty($password)) {
                    throw new Exception("Nom, Email et Mot de passe sont obligatoires");
                }

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Format d'email invalide");
                }

                if (!is_numeric($salary)) {
                    throw new Exception("Le salaire doit être un nombre");
                }

                // Vérifier si l'email existe déjà
                $check_sql = "SELECT id FROM teachers WHERE email = ?";
                $check_stmt = $link->prepare($check_sql);
                $check_stmt->bind_param("s", $email);
                $check_stmt->execute();
                if ($check_stmt->get_result()->num_rows > 0) {
                    throw new Exception("Email déjà existant");
                }

                // Générer un ID unique pour l'enseignant
                $teacher_id = 'TE-' . strtoupper(substr($name, 0, 3)) . '-' . rand(1000, 9999);

                // Hasher le mot de passe
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                // Début de la transaction
                $link->begin_transaction();

                // Insertion dans users
                $sql = "INSERT INTO users (userid, password, usertype) VALUES (?, ?, 'teacher')";
                $stmt = $link->prepare($sql);
                $stmt->bind_param("ss", $teacher_id, $hashedPassword);
                $stmt->execute();

                // Insertion dans teachers
                $sql = "INSERT INTO teachers (id, name, password, phone, email, sex, dob, address, hiredate, salary, created_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $link->prepare($sql);
                $stmt->bind_param("sssssssssds", 
                    $teacher_id, $name, $password, $phone, $email, 
                    $gender, $dob, $address, $hiredate, $salary, $admin_id
                );
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

        // Afficher les résultats
        echo "<div class='container py-4'><div class='row justify-content-center'><div class='col-md-10'>";
        
        if ($successCount > 0) {
            echo "<div class='alert alert-success'>";
            echo "<h5><i class='fas fa-check-circle me-2'></i>Importation réussie !</h5>";
            echo "<p><strong>$successCount</strong> enseignant(s) importé(s) avec succès.</p>";
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

        echo "<a href='manageTeacher.php' class='btn btn-primary'><i class='fas fa-list me-2'></i>Voir les enseignants</a> ";
        echo "<a href='importTeachers.php' class='btn btn-secondary'><i class='fas fa-redo me-2'></i>Nouvelle importation</a>";
        echo "</div></div></div>";

    } catch (Exception $e) {
        echo "<div class='container py-4'><div class='row justify-content-center'><div class='col-md-10'>";
        echo "<div class='alert alert-danger'>";
        echo "<h5><i class='fas fa-times-circle me-2'></i>Erreur</h5>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
        echo "<a href='importTeachers.php' class='btn btn-secondary'><i class='fas fa-arrow-left me-2'></i>Retour</a>";
        echo "</div></div></div>";
    }
    exit;
}
?>
