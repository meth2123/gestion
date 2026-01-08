<?php
require_once(__DIR__ . '/check_director_access.php');
require_once('../../db/config.php');
require_once('../../service/db_utils.php');

// Récupérer l'admin_id lié au directeur
$director_id = $_SESSION['userid'];
$link = $link ?? $conn ?? getDbConnection();
$sql = "SELECT created_by FROM director WHERE userid = ?";
$stmt = $link->prepare($sql);
$stmt->bind_param("s", $director_id);
$stmt->execute();
$result = $stmt->get_result();
$admin_row = $result->fetch_assoc();
$admin_id = $admin_row['created_by'];

// Liste des enseignants et personnel créés par cet admin
$teachers = db_fetch_all("SELECT id, name FROM teachers WHERE created_by = ? ORDER BY name", [$admin_id], 's');
$staff = db_fetch_all("SELECT id, name FROM staff WHERE created_by = ? ORDER BY name", [$admin_id], 's');

// Récupérer années scolaires
try {
    $school_years = db_fetch_all("SELECT year FROM school_years ORDER BY year DESC");
    if (!$school_years) $school_years = [];
} catch (Exception $e) {
    $school_years = [];
}
$months = [
    1 => 'Janvier', 2 => 'Février', 3 => 'Mars',
    4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
    7 => 'Juillet', 8 => 'Août', 9 => 'Septembre',
    10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
];
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_salary'])) {
    $employee_type = $_POST['employee_type'];
    $employee_id = $_POST['employee_id'];
    $month = intval($_POST['month']);
    $year = $_POST['year'];
    $base_salary = floatval($_POST['base_salary']);
    $days_present = intval($_POST['days_present']);
    $days_absent = intval($_POST['days_absent']);
    $final_salary = floatval($_POST['final_salary']);
    
    if ($employee_type === 'teacher') {
        $sql = "INSERT INTO teacher_salary_history (teacher_id, month, year, base_salary, days_present, days_absent, final_salary, payment_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
        $stmt = $link->prepare($sql);
        $stmt->bind_param('sisdiiis', $employee_id, $month, $year, $base_salary, $days_present, $days_absent, $final_salary, $admin_id);
    } else {
        $sql = "INSERT INTO staff_salary_history (staff_id, month, year, base_salary, days_present, days_absent, final_salary, payment_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
        $stmt = $link->prepare($sql);
        $stmt->bind_param('sisdiiis', $employee_id, $month, $year, $base_salary, $days_present, $days_absent, $final_salary, $admin_id);
    }
    if ($stmt->execute()) {
        $success_message = "Salaire payé avec succès.";
    } else {
        $error_message = "Erreur lors du paiement du salaire.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payer un Salaire</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-wallet me-2"></i>Payer un Salaire</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($success_message): ?>
                            <div class="alert alert-success"> <i class="fas fa-check-circle me-1"></i><?= htmlspecialchars($success_message) ?></div>
                        <?php endif; ?>
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger"> <i class="fas fa-exclamation-circle me-1"></i><?= htmlspecialchars($error_message) ?></div>
                        <?php endif; ?>
                        <form method="POST" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Type d'employé</label>
                                <select name="employee_type" class="form-select" required onchange="updateEmployeeList(this.value)">
                                    <option value="teacher">Enseignant</option>
                                    <option value="staff">Personnel</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Employé</label>
                                <select name="employee_id" id="employeeSelect" class="form-select" required>
                                    <?php foreach ($teachers as $t): ?>
                                        <option value="<?= $t['id'] ?>" data-type="teacher">[Enseignant] <?= htmlspecialchars($t['name']) ?></option>
                                    <?php endforeach; ?>
                                    <?php foreach ($staff as $s): ?>
                                        <option value="<?= $s['id'] ?>" data-type="staff">[Personnel] <?= htmlspecialchars($s['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Mois</label>
                                <select name="month" class="form-select" required>
                                    <?php foreach ($months as $num=>$nom): ?>
                                        <option value="<?= $num ?>"><?= $nom ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Année</label>
                                <select name="year" class="form-select" required>
                                    <?php foreach ($school_years as $y): ?>
                                        <option value="<?= htmlspecialchars($y['year']) ?>"><?= htmlspecialchars($y['year']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Salaire de base (€)</label>
                                <input type="number" step="0.01" min="0" name="base_salary" class="form-control" required>
                            </div>
                            <div class="col-md-4">
    <label class="form-label">Jours de présence</label>
    <input type="number" name="days_present" id="days_present" class="form-control" readonly>
</div>
<div class="col-md-4">
    <label class="form-label">Jours d'absence</label>
    <input type="number" name="days_absent" id="days_absent" class="form-control" readonly>
</div>
<div class="col-md-4">
    <label class="form-label">Salaire final (€)</label>
    <input type="number" step="0.01" min="0" name="final_salary" id="final_salary" class="form-control" readonly>
</div>
<script>
// Récupération dynamique des présences/absences et calcul du salaire
function getDaysInMonth(month, year) {
    return new Date(year, month, 0).getDate();
}
async function fetchAttendance() {
    const type = document.querySelector('select[name="employee_type"]').value;
    const id = document.querySelector('select[name="employee_id"]').value;
    const month = document.querySelector('select[name="month"]').value;
    const year = document.querySelector('select[name="year"]').value;
    if (!id || !month || !year) return;
    let url = type === 'teacher' ? '../../module/admin/myattendanceteacherthismonth.php' : '../../module/admin/myattendancestaffthismonth.php';
    try {
        const resp = await fetch(url + '?id=' + encodeURIComponent(id) + '&month=' + encodeURIComponent(month) + '&year=' + encodeURIComponent(year));
        const data = await resp.json();
        let present = 0;
        if (data.records && Array.isArray(data.records)) {
            present = data.records.length;
        }
        const totalDays = getDaysInMonth(month, year);
        document.getElementById('days_present').value = present;
        document.getElementById('days_absent').value = Math.max(totalDays - present, 0);
        // Calcul automatique du salaire final
        const base = parseFloat(document.querySelector('input[name="base_salary"]').value) || 0;
        const finalSalary = base * present / totalDays;
        document.getElementById('final_salary').value = isNaN(finalSalary) ? '' : finalSalary.toFixed(2);
    } catch (e) {
        document.getElementById('days_present').value = '';
        document.getElementById('days_absent').value = '';
        document.getElementById('final_salary').value = '';
    }
}
document.querySelector('select[name="employee_type"]').addEventListener('change', fetchAttendance);
document.querySelector('select[name="employee_id"]').addEventListener('change', fetchAttendance);
document.querySelector('select[name="month"]').addEventListener('change', fetchAttendance);
document.querySelector('select[name="year"]').addEventListener('change', fetchAttendance);
document.querySelector('input[name="base_salary"]').addEventListener('input', fetchAttendance);
window.addEventListener('DOMContentLoaded', fetchAttendance);
</script>
                            <div class="col-12 d-grid">
                                <button type="submit" name="pay_salary" class="btn btn-success"><i class="fas fa-money-bill-wave me-1"></i>Payer le Salaire</button>
                            </div>
                        </form>
                        <script>
                            // Filtrage dynamique de la liste employé selon le type
                            function updateEmployeeList(type) {
                                let select = document.getElementById('employeeSelect');
                                for (let i = 0; i < select.options.length; i++) {
                                    let opt = select.options[i];
                                    opt.style.display = (opt.getAttribute('data-type') === type) ? '' : 'none';
                                }
                                select.value = '';
                            }
                        </script>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="salary.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Retour à la gestion des salaires</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
