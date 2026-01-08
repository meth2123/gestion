<?php
include_once('main.php');
include_once('includes/admin_utils.php');

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isset($_SESSION['login_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

$admin_id = $_SESSION['login_id'];
$student_id = isset($_GET['id']) ? trim($_GET['id']) : '';

if (empty($student_id)) {
    header('Location: updateStudent.php?error=' . urlencode('ID étudiant manquant'));
    exit;
}

// Récupérer la liste des classes disponibles
$classes = [];
$sql_classes = "SELECT id, name, section FROM class WHERE created_by = ? OR created_by = '21' ORDER BY name, section";
$stmt_classes = $link->prepare($sql_classes);
$stmt_classes->bind_param("s", $admin_id);
$stmt_classes->execute();
$result_classes = $stmt_classes->get_result();
if ($result_classes) {
    while ($row = $result_classes->fetch_assoc()) {
        $classes[] = $row;
    }
}

// Récupérer les informations de l'étudiant
$sql = "SELECT * FROM students WHERE id = ? AND created_by = ?";
$stmt = $link->prepare($sql);

if (!$stmt) {
    header('Location: updateStudent.php?error=' . urlencode('Erreur de préparation: ' . $link->error));
    exit;
}

$stmt->bind_param("ss", $student_id, $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: updateStudent.php?error=' . urlencode('Étudiant non trouvé ou vous n\'avez pas les droits pour le modifier'));
    exit;
}

$student = $result->fetch_assoc();

$content = '
<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h4 class="card-title mb-0">Modifier l\'étudiant: ' . htmlspecialchars($student['name']) . '</h4>
                </div>
                <div class="card-body">
                    <!-- Message de statut -->
                    <div id="statusMessage" class="alert d-none mb-4"></div>
                    
                    <form action="updateStudentProcess.php" method="post" enctype="multipart/form-data" class="row g-3">
                        <input type="hidden" name="id" value="' . htmlspecialchars($student['id']) . '">
                        <input type="hidden" name="created_by" value="' . htmlspecialchars($admin_id) . '">
                        
                        <div class="col-md-6">
                            <label for="name" class="form-label">Nom</label>
                            <input type="text" class="form-control" id="name" name="name" value="' . htmlspecialchars($student['name']) . '">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="password" class="form-label">Mot de passe (laisser vide pour ne pas changer)</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Nouveau mot de passe">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="' . htmlspecialchars($student['phone']) . '">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="' . htmlspecialchars($student['email']) . '">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="gender" class="form-label">Genre</label>
                            <select class="form-select" id="gender" name="gender">
                                <option value="Male" ' . ($student['sex'] == 'Male' ? 'selected' : '') . '>Masculin</option>
                                <option value="Female" ' . ($student['sex'] == 'Female' ? 'selected' : '') . '>Féminin</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="dob" class="form-label">Date de naissance</label>
                            <input type="date" class="form-control" id="dob" name="dob" value="' . htmlspecialchars($student['dob']) . '">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="addmissiondate" class="form-label">Date d\'admission</label>
                            <input type="date" class="form-control" id="addmissiondate" name="addmissiondate" value="' . htmlspecialchars($student['addmissiondate']) . '">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="address" class="form-label">Adresse</label>
                            <input type="text" class="form-control" id="address" name="address" value="' . htmlspecialchars($student['address']) . '">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="parentid" class="form-label">ID Parent</label>
                            <input type="text" class="form-control" id="parentid" name="parentid" value="' . htmlspecialchars($student['parentid']) . '">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="classid" class="form-label">Classe</label>
                            <select class="form-select" id="classid" name="classid">
                                <option value="">Sélectionner une classe</option>';

foreach($classes as $class) {
    $selected = ($student['classid'] == $class['id']) ? 'selected' : '';
    $content .= '
                                <option value="' . htmlspecialchars($class['id']) . '" ' . $selected . '>
                                    ' . htmlspecialchars($class['name']) . ' - ' . htmlspecialchars($class['section']) . '
                                </option>';
}

$content .= '
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="photo" class="form-label">Photo</label>
                            <div class="d-flex align-items-center">
                                <img src="../images/' . htmlspecialchars($student['id']) . '.jpg" alt="' . htmlspecialchars($student['name']) . '" class="me-3" style="width: 100px; height: 100px; object-fit: cover; border-radius: 50%;">
                                <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                            </div>
                        </div>
                        
                        <div class="col-12 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Enregistrer les modifications
                            </button>
                            <a href="updateStudent.php" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-arrow-left me-2"></i>Retour
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Afficher le message de succès ou d\'erreur si présent dans l\'URL
function showMessage(message, type = "success") {
    const statusMessage = document.getElementById("statusMessage");
    statusMessage.className = `alert alert-${type === "success" ? "success" : "danger"} mb-4`;
    statusMessage.textContent = message;
    statusMessage.classList.remove("d-none");
    
    setTimeout(() => {
        statusMessage.classList.add("d-none");
    }, 5000);
}

const urlParams = new URLSearchParams(window.location.search);
if (urlParams.has("success")) {
    showMessage("L\'étudiant a été mis à jour avec succès!");
}
if (urlParams.has("error")) {
    showMessage(decodeURIComponent(urlParams.get("error")), "error");
}
</script>';

include('templates/layout.php');
?>
