<?php
include_once('main.php');
include_once('includes/auth_check.php');
include_once('includes/admin_actions.php');
include_once('includes/admin_utils.php');

// L'ID de l'administrateur est déjà défini dans auth_check.php
// $admin_id = $_SESSION['login_id'];

// Function to generate a unique staff ID
function generateUniqueStaffId($link) {
    // Prefix for all staff IDs
    $prefix = "STF";
    
    // Get the last numeric part
    $last_id_sql = "SELECT id FROM staff WHERE id LIKE 'STF%' ORDER BY id DESC LIMIT 1";
    $result = $link->query($last_id_sql);
    
    if ($result && $result->num_rows > 0) {
        $last_id = $result->fetch_assoc()['id'];
        // Extract the numeric part (positions 3-6)
        $numeric_part = intval(substr($last_id, 3, 3));
        $numeric_part++;
    } else {
        $numeric_part = 1;
    }
    
    // Generate random letters (3 uppercase letters)
    $letters = '';
    for ($i = 0; $i < 3; $i++) {
        $letters .= chr(rand(65, 90)); // ASCII codes for A-Z
    }
    
    // Format the numeric part to be 3 digits with leading zeros
    $formatted_number = str_pad($numeric_part, 3, '0', STR_PAD_LEFT);
    
    // Combine all parts
    $new_id = $prefix . $formatted_number . $letters;
    
    // Verify uniqueness and try again if necessary
    $check_sql = "SELECT id FROM staff WHERE id = ?";
    $check_stmt = $link->prepare($check_sql);
    $check_stmt->bind_param("s", $new_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        // If ID exists (very unlikely), try again
        return generateUniqueStaffId($link);
    }
    
    return $new_id;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $sex = $_POST['sex'] ?? '';
    $dob = $_POST['dob'] ?? '';
    $hiredate = date('Y-m-d'); // Current date as hire date
    $salary = $_POST['salary'] ?? '';
    $password = $_POST['password'] ?? '';

    // Start transaction
    $link->begin_transaction();

    try {
        // Generate unique staff ID
        $staff_id = generateUniqueStaffId($link);

        // Insert into staff table
        $insert_sql = "INSERT INTO staff (id, name, email, phone, address, sex, dob, hiredate, salary, created_by) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = $link->prepare($insert_sql);
        $insert_stmt->bind_param("ssssssssds", 
            $staff_id, $name, $email, $phone, $address, $sex, $dob, $hiredate, $salary, $admin_id);
        
        if (!$insert_stmt->execute()) {
            throw new Exception("Erreur lors de l'ajout du membre du personnel");
        }

        // Insert into users table
        $insert_user_sql = "INSERT INTO users (userid, password, usertype) VALUES (?, ?, 'staff')";
        $insert_user_stmt = $link->prepare($insert_user_sql);
        $insert_user_stmt->bind_param("ss", $staff_id, $password);
        
        if (!$insert_user_stmt->execute()) {
            throw new Exception("Erreur lors de la création du compte utilisateur");
        }

        $link->commit();
        header("Location: manageStaff.php?success=" . urlencode("Membre du personnel ajouté avec succès. ID de connexion: " . $staff_id));
        exit;
    } catch (Exception $e) {
        $link->rollback();
    }
}

$content = '
<div class="container py-4">
    <!-- Bouton d\'importation Excel -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-info d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-file-excel me-2"></i>
                    <strong>Importation en masse</strong> - Téléversez un fichier Excel pour ajouter plusieurs membres du personnel à la fois
                </div>
                <a href="importStaff.php" class="btn btn-success">
                    <i class="fas fa-upload me-2"></i>Importer depuis Excel
                </a>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-8">
            ' . (isset($error) ? '<div class="alert alert-danger mb-4">' . htmlspecialchars($error) . '</div>' : '') . '
            
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Ajouter un Membre du Personnel</h4>
                    <a href="manageStaff.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-2"></i>Retour à la liste
                    </a>
                </div>
                
                <div class="card-body">
                    <div class="alert alert-info mb-4">
                        <div class="d-flex">
                            <div class="me-3">
                                <i class="fas fa-info-circle fs-4"></i>
                            </div>
                            <div>
                                <p class="mb-0"><strong>Note:</strong> Un ID unique sera généré automatiquement au format STF001XXX, où XXX sont des lettres aléatoires. Cet ID servira d\'identifiant de connexion.</p>
                            </div>
                        </div>
                    </div>

                    <form method="POST" id="staffForm" class="needs-validation" novalidate>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Nom*</label>
                                <input type="text" name="name" id="name" required class="form-control">
                                <div class="invalid-feedback">Veuillez entrer un nom</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email*</label>
                                <input type="email" name="email" id="email" required class="form-control">
                                <div class="invalid-feedback">Veuillez entrer un email valide</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Téléphone*</label>
                                <input type="tel" name="phone" id="phone" required class="form-control">
                                <div class="invalid-feedback">Veuillez entrer un numéro de téléphone</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="address" class="form-label">Adresse*</label>
                                <input type="text" name="address" id="address" required class="form-control">
                                <div class="invalid-feedback">Veuillez entrer une adresse</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="sex" class="form-label">Genre*</label>
                                <select name="sex" id="sex" required class="form-select">
                                    <option value="">Sélectionner</option>
                                    <option value="M">Masculin</option>
                                    <option value="F">Féminin</option>
                                </select>
                                <div class="invalid-feedback">Veuillez sélectionner un genre</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="dob" class="form-label">Date de naissance*</label>
                                <input type="date" name="dob" id="dob" required class="form-control">
                                <div class="invalid-feedback">Veuillez entrer une date de naissance</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="salary" class="form-label">Salaire*</label>
                                <div class="input-group">
                                    <input type="number" name="salary" id="salary" required step="0.01" min="0" class="form-control">
                                    <span class="input-group-text">FCFA</span>
                                    <div class="invalid-feedback">Veuillez entrer un salaire valide</div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Mot de passe*</label>
                                <div class="input-group">
                                    <input type="password" name="password" id="password" required class="form-control">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword" onclick="togglePasswordVisibility()">
                                        <i class="fas fa-eye" id="password-toggle-icon"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">Veuillez entrer un mot de passe</div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <small class="text-muted">* Champs obligatoires</small>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus-circle me-2"></i>Ajouter le membre du personnel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>';

// Ajouter le script JavaScript séparément, pas dans la chaîne PHP
$js_content = '
<script>
// Fonction pour basculer la visibilité du mot de passe
function togglePasswordVisibility() {
    const passwordInput = document.getElementById("password");
    const toggleIcon = document.getElementById("password-toggle-icon");
    
    if (passwordInput.type === "password") {
        passwordInput.type = "text";
        toggleIcon.classList.remove("fa-eye");
        toggleIcon.classList.add("fa-eye-slash");
    } else {
        passwordInput.type = "password";
        toggleIcon.classList.remove("fa-eye-slash");
        toggleIcon.classList.add("fa-eye");
    }
}

// Validation du formulaire
(function() {
    "use strict";
    
    // Récupérer tous les formulaires auxquels nous voulons appliquer des styles de validation Bootstrap personnalisés
    const forms = document.querySelectorAll(".needs-validation");
    
    // Boucle pour empêcher la soumission et appliquer la validation
    Array.from(forms).forEach(function(form) {
        form.addEventListener("submit", function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add("was-validated");
        }, false);
    });
})();
</script>
';

// Inclure le contenu et le script JavaScript dans le layout
include('templates/layout.php');

// Ajouter le script JavaScript après le layout
echo $js_content;
?>
