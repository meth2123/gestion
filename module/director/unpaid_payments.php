<?php
// Restriction d'accès : uniquement directeur
session_start();
if (!isset($_SESSION['director_id'])) {
    header('Location: ../../login.php?error=unauthorized');
    exit();
}

require_once(__DIR__ . '/../../db/config.php');
require_once(__DIR__ . '/../../service/mysqlcon.php');
require_once('../../service/db_utils.php');
require_once(__DIR__ . '/../../school_config.php');

// Vérifier la connexion
global $link;
if ($link === null || !$link) {
    die('Erreur de connexion à la base de données. Vérifiez les variables d\'environnement.');
}

// Utiliser l'admin_id lié au directeur
$director_id = $_SESSION['director_id'];
$sql = "SELECT created_by FROM director WHERE userid = ?";
$stmt = $link->prepare($sql);
$stmt->bind_param("s", $director_id);
$stmt->execute();
$result = $stmt->get_result();
$admin_row = $result->fetch_assoc();
$admin_id = $admin_row['created_by'];

// --- Liste des élèves en retard de paiement (adapté pour directeur) ---

// Fonctions utilitaires (copie adaptée)
function getCurrentSchoolYear() {
    $currentMonth = date('n');
    $currentYear = date('Y');
    if ($currentMonth >= 1 && $currentMonth <= 6) return (string)($currentYear - 1);
    return (string)$currentYear;
}
function getCurrentSchoolMonth() { return date('n'); }
function getClasses() {
    global $admin_id;
    return db_fetch_all("SELECT DISTINCT c.* FROM class c INNER JOIN students s ON c.id = s.classid WHERE s.created_by = ? ORDER BY c.name", [$admin_id], 's');
}
function getUnpaidStudents($month = null, $year = null, $class_id = null, $status = 'all') {
    global $admin_id;
    if ($month === null) $month = getCurrentSchoolMonth();
    if ($year === null) $year = getCurrentSchoolYear();
    
    // Calculer le nombre de mois en retard par rapport au mois sélectionné
    $current_month = (int)date('n');
    $current_year = (int)date('Y');
    
    // S'assurer que $month et $year sont des entiers
    $month = (int)$month;
    $year = (int)$year;
    
    // Calculer la différence en mois
    $selected_timestamp = mktime(0, 0, 0, $month, 1, $year);
    $current_timestamp = mktime(0, 0, 0, $current_month, 1, $current_year);
    $months_behind = (($current_year - $year) * 12) + ($current_month - $month);
    
    $params = [$admin_id, $month, $year];
    $types = 'sii';
    
    $sql = "SELECT 
        s.id, 
        s.name as student_name, 
        c.name as class_name,
        ? as months_behind
    FROM students s 
    INNER JOIN class c ON s.classid = c.id 
    WHERE s.created_by = ? 
    AND NOT EXISTS (
        SELECT 1 FROM payment p 
        WHERE p.studentid = s.id 
        AND p.month = ? 
        AND p.year = ?
    )";
    
    if ($class_id) { 
        $sql .= " AND s.classid = ?"; 
        $params[] = $class_id; 
        $types .= 's'; 
    }
    
    // Filtrer par statut de retard
    if ($status === 'overdue') {
        // Seulement ceux qui ont au moins un mois en retard
        $sql .= " AND ? > 0";
        $params[] = $months_behind;
        $types .= 'i';
    } elseif ($status === 'critical') {
        // Ceux qui ont 3 mois ou plus en retard
        $sql .= " AND ? >= 3";
        $params[] = $months_behind;
        $types .= 'i';
    }
    
    $sql .= " ORDER BY c.name, s.name";
    
    // Ajouter le paramètre months_behind au début
    array_unshift($params, $months_behind);
    $types = 'i' . $types;
    
    return db_fetch_all($sql, $params, $types);
}

// Filtres
$selected_month = isset($_GET['month']) ? $_GET['month'] : getCurrentSchoolMonth();
// Années scolaires manuelles
try {
    $school_years = db_fetch_all("SELECT year FROM school_years ORDER BY year DESC");
    if (!$school_years) $school_years = [];
} catch (Exception $e) {
    $school_years = [];
    $school_years_error = "Erreur : la table 'school_years' n'existe pas. <a href='school_years.php' class='btn btn-warning btn-sm ms-2'>Créer/Configurer</a>";
}
$selected_year = isset($_GET['year']) ? $_GET['year'] : ($school_years[0]['year'] ?? getCurrentSchoolYear());
$selected_class = isset($_GET['class_id']) ? $_GET['class_id'] : null;
$selected_status = isset($_GET['status']) ? $_GET['status'] : 'all'; // all, overdue, critical
$classes = getClasses();
$unpaid_students = getUnpaidStudents($selected_month, $selected_year, $selected_class, $selected_status);
$months = [1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',10=>'Octobre',11=>'Novembre',12=>'Décembre'];

ob_start();
?>
<div class="container py-4">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0"><i class="fas fa-exclamation-circle text-danger me-2"></i>Élèves en retard de paiement pour <?= htmlspecialchars($months[$selected_month] ?? 'Mois inconnu') ?> <?= htmlspecialchars($selected_year) ?></h2>
                <a href="payment.php" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-2"></i>Retour paiements</a>
            </div>
            <form method="GET" class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label small text-muted">Classe</label>
                    <select name="class_id" class="form-select">
                        <option value="">Toutes les classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['id'] ?>" <?= ($selected_class == $class['id']) ? 'selected' : '' ?>><?= htmlspecialchars($class['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Mois</label>
                    <select name="month" class="form-select">
                        <?php foreach ($months as $num=>$nom): ?>
                            <option value="<?= $num ?>" <?= ($selected_month == $num) ? 'selected' : '' ?>><?= $nom ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Année</label>
                    <div class="d-flex align-items-center gap-2">
                        <?php if (isset($school_years_error)): ?>
                            <div class="alert alert-warning py-2 mb-0">
                                <?= $school_years_error ?>
                            </div>
                        <?php else: ?>
                            <select name="year" class="form-select">
                                <?php foreach ($school_years as $y): ?>
                                    <option value="<?= htmlspecialchars($y['year']) ?>" <?= ($selected_year == $y['year']) ? 'selected' : '' ?>><?= htmlspecialchars($y['year']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Statut</label>
                    <select name="status" class="form-select">
                        <option value="all" <?= ($selected_status == 'all') ? 'selected' : '' ?>>Tous</option>
                        <option value="overdue" <?= ($selected_status == 'overdue') ? 'selected' : '' ?>>En retard</option>
                        <option value="critical" <?= ($selected_status == 'critical') ? 'selected' : '' ?>>Critique (3+ mois)</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-1"></i>Filtrer</button>
                    <a href="school_years.php" class="btn btn-outline-secondary"><i class="fas fa-cog"></i></a>
                </div>
            </form>
            <div class="alert alert-info mb-3">
                <i class="fas fa-info-circle me-2"></i>
                <strong><?= count($unpaid_students) ?></strong> élève(s) en retard de paiement trouvé(s)
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>Élève</th>
                            <th>Classe</th>
                            <th>Mois en retard</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($unpaid_students)): ?>
                            <?php foreach ($unpaid_students as $student): ?>
                                <?php
                                $months_behind = $student['months_behind'] ?? 0;
                                $status_class = 'success';
                                $status_text = 'À jour';
                                if ($months_behind >= 3) {
                                    $status_class = 'danger';
                                    $status_text = 'Critique';
                                } elseif ($months_behind >= 1) {
                                    $status_class = 'warning';
                                    $status_text = 'En retard';
                                }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($student['student_name']) ?></td>
                                    <td><?= htmlspecialchars($student['class_name']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $status_class ?>">
                                            <?= $months_behind ?> mois
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $status_class ?>">
                                            <?= $status_text ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    <i class="fas fa-check-circle fa-2x mb-2 text-success"></i><br>
                                    Aucun élève en retard de paiement pour cette période
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include('templates/layout.php');
