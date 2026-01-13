<?php
// Inclusion des fonctions stats admin pour le module directeur
require_once dirname(__DIR__) . '/admin/includes/dashboard_stats.php';

// Alias pour compatibilité avec l'index directeur
function get_total_payments() {
    global $link;
    if (!isset($_SESSION['userid'])) return 0;
    $director_id = $_SESSION['userid'];
    $sql = "SELECT created_by FROM director WHERE userid = ?";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("s", $director_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result || $result->num_rows === 0) return 0;
    $admin_row = $result->fetch_assoc();
    $admin_id = $admin_row['created_by'];
    $sql = "SELECT SUM(amount) as total FROM payment WHERE studentid IN (SELECT id FROM students WHERE created_by = ?)";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("s", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : ['total' => 0];
    $total = (is_null($row['total']) || $row['total'] === false) ? 0 : $row['total'];
    return number_format($total, 0, ',', ' ');
}


function get_total_students() {
    global $link;
    if (!isset($_SESSION['userid'])) return 0;
    $director_id = $_SESSION['userid'];
    $sql = "SELECT created_by FROM director WHERE userid = ?";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("s", $director_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result || $result->num_rows === 0) return 0;
    $admin_row = $result->fetch_assoc();
    $admin_id = $admin_row['created_by'];
    $sql = "SELECT COUNT(*) as count FROM students WHERE created_by = ?";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("s", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : ['count' => 0];
    return $row['count'];
}

function get_total_teachers() {
    global $link;
    if (!isset($_SESSION['userid'])) return 0;
    $director_id = $_SESSION['userid'];
    $sql = "SELECT created_by FROM director WHERE userid = ?";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("s", $director_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result || $result->num_rows === 0) return 0;
    $admin_row = $result->fetch_assoc();
    $admin_id = $admin_row['created_by'];
    $sql = "SELECT COUNT(*) as count FROM teachers WHERE created_by = ?";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("s", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : ['count' => 0];
    return $row['count'];
}

function get_total_classes() {
    global $link;
    if (!isset($_SESSION['userid'])) return 0;
    $director_id = $_SESSION['userid'];
    $sql = "SELECT created_by FROM director WHERE userid = ?";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("s", $director_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result || $result->num_rows === 0) return 0;
    $admin_row = $result->fetch_assoc();
    $admin_id = $admin_row['created_by'];
    $sql = "SELECT COUNT(*) as count FROM class WHERE created_by = ?";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("s", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : ['count' => 0];
    return $row['count'];
}

function get_total_payments_current_month() {
    global $link;
    if (!isset($_SESSION['userid'])) return 0;
    $director_id = $_SESSION['userid'];
    $sql = "SELECT created_by FROM director WHERE userid = ?";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("s", $director_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result || $result->num_rows === 0) return 0;
    $admin_row = $result->fetch_assoc();
    $admin_id = $admin_row['created_by'];
    $month = date('n');
    $year = date('Y');
    $school_year = ($month >= 1 && $month <= 6) ? $year - 1 : $year;
    $current_month_year = ($month >= 1 && $month <= 6) ? $year : $school_year;
    $sql = "SELECT SUM(amount) as total FROM payment WHERE month = ? AND year = ? AND studentid IN (SELECT id FROM students WHERE created_by = ?)";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("sss", $month, $current_month_year, $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : ['total' => 0];
    $total = (is_null($row['total']) || $row['total'] === false) ? 0 : $row['total'];
    return number_format($total, 0, ',', ' ');
}

function get_total_expected_payments_current_month() {
    global $link;
    if (!isset($_SESSION['userid'])) return 0;
    $director_id = $_SESSION['userid'];
    $sql = "SELECT created_by FROM director WHERE userid = ?";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("s", $director_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result || $result->num_rows === 0) return 0;
    $admin_row = $result->fetch_assoc();
    $admin_id = $admin_row['created_by'];
    $month = date('n');
    $year = date('Y');
    $school_year = ($month >= 1 && $month <= 6) ? $year - 1 : $year;
    $current_month_year = ($month >= 1 && $month <= 6) ? $year : $school_year;
    // On suppose que chaque élève doit payer selon sa classe (class_payment_amount)
    $sql = "SELECT SUM(cpa.amount) as total FROM students s JOIN class_payment_amount cpa ON s.classid = cpa.class_id WHERE s.created_by = ?";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("s", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : ['total' => 0];
    $total = (is_null($row['total']) || $row['total'] === false) ? 0 : $row['total'];
    return number_format($total, 0, ',', ' ');
}

function get_total_unpaid_current_month() {
    // Total attendu - total payé (mois courant)
    $expected = (float)str_replace([' ', ','], ['', '.'], get_total_expected_payments_current_month());
    $paid = (float)str_replace([' ', ','], ['', '.'], get_total_payments_current_month());
    return number_format(max($expected - $paid, 0), 0, ',', ' ');
}

function get_total_expected_payments_year() {
    global $link;
    if (!isset($_SESSION['userid'])) return 0;
    $director_id = $_SESSION['userid'];
    $sql = "SELECT created_by FROM director WHERE userid = ?";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("s", $director_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result || $result->num_rows === 0) return 0;
    $admin_row = $result->fetch_assoc();
    $admin_id = $admin_row['created_by'];
    // On suppose 10 mois de scolarité (sept-juin)
    $sql = "SELECT SUM(cpa.amount) as total FROM students s JOIN class_payment_amount cpa ON s.classid = cpa.class_id WHERE s.created_by = ?";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("s", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : ['total' => 0];
    $total = $row['total'] * 10;
    return number_format($total, 0, ',', ' ');
}

function get_total_unpaid_year() {
    // Total attendu année - total payé année
    global $link;
    if (!isset($_SESSION['userid'])) return 0;
    $director_id = $_SESSION['userid'];
    $sql = "SELECT created_by FROM director WHERE userid = ?";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("s", $director_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result || $result->num_rows === 0) return 0;
    $admin_row = $result->fetch_assoc();
    $admin_id = $admin_row['created_by'];
    $sql = "SELECT SUM(amount) as total FROM payment WHERE studentid IN (SELECT id FROM students WHERE created_by = ?)";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("s", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : ['total' => 0];
    $total_paid = $row['total'];
    $expected = (float)str_replace([' ', ','], ['', '.'], get_total_expected_payments_year());
    return number_format(max($expected - $total_paid, 0), 0, ',', ' ');
}

function get_unpaid_payments() {
    global $link;
    // Récupérer l'admin créateur pour ce directeur
    if (!isset($_SESSION['userid'])) return 0;
    $director_id = $_SESSION['userid'];
    $sql = "SELECT created_by FROM director WHERE userid = ?";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("s", $director_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result || $result->num_rows === 0) return 0;
    $admin_row = $result->fetch_assoc();
    $admin_id = $admin_row['created_by'];

    // Mois et année scolaire courants
    $month = date('n');
    $year = date('Y');
    // Ajustement année scolaire (sept-juin)
    $school_year = ($month >= 1 && $month <= 6) ? $year - 1 : $year;
    $current_month_year = ($month >= 1 && $month <= 6) ? $year : $school_year;

    // Compter les élèves sans paiement pour ce mois
    $sql = "SELECT COUNT(*) AS count FROM students s
        WHERE s.created_by = ?
        AND NOT EXISTS (
            SELECT 1 FROM payment p WHERE p.studentid = s.id AND p.month = ? AND p.year = ?
        )";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("sss", $admin_id, $month, $current_month_year);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : ['count' => 0];
    return $row['count'];
}

function get_total_salaries() {
    global $link;
    $result = $link->query("SHOW TABLES LIKE 'salary'");
    if (!$result || $result->num_rows == 0) return 0;
    if (!isset($_SESSION['userid'])) return 0;
    $director_id = $_SESSION['userid'];
    $sql = "SELECT created_by FROM director WHERE userid = ?";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("s", $director_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result || $result->num_rows === 0) return 0;
    $admin_row = $result->fetch_assoc();
    $admin_id = $admin_row['created_by'];
    $sql = "SELECT SUM(amount) as total FROM salary WHERE staffid IN (SELECT id FROM staff WHERE created_by = ?)";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("s", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : ['total' => 0];
    $total = (is_null($row['total']) || $row['total'] === false) ? 0 : $row['total'];
    return number_format($total, 0, ',', ' ');
}

function get_unpaid_salaries() {
    // Obsolète : utiliser get_unpaid_salaries_current_month()
    return get_unpaid_salaries_current_month();
}

function get_total_salaries_current_month() {
    global $link;
    if (!isset($_SESSION['userid'])) return 0;
    $director_id = $_SESSION['userid'];
    $sql = "SELECT created_by FROM director WHERE userid = ?";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("s", $director_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result || $result->num_rows === 0) return 0;
    $admin_row = $result->fetch_assoc();
    $admin_id = $admin_row['created_by'];

    $month = date('n');
    $year = date('Y');
    $days_in_month = date('t');
    $total = 0;

    // Enseignants
    $sql = "SELECT COALESCE(th.final_salary, ROUND(t.salary * COUNT(DATE(a.datetime)) / ?)) AS to_pay
            FROM teachers t
            LEFT JOIN attendance a ON t.id = a.attendedid AND MONTH(a.datetime) = ? AND YEAR(a.datetime) = ? AND a.person_type = 'teacher'
            LEFT JOIN teacher_salary_history th ON t.id = th.teacher_id AND th.month = ? AND th.year = ?
            WHERE t.created_by = ?
            GROUP BY t.id";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("iiiiis", $days_in_month, $month, $year, $month, $year, $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $total += $row['to_pay'];
    }

    // Personnel
    $sql = "SELECT COALESCE(sh.final_salary, ROUND(s.salary * COUNT(DATE(a.datetime)) / ?)) AS to_pay
            FROM staff s
            LEFT JOIN attendance a ON s.id = a.attendedid AND MONTH(a.datetime) = ? AND YEAR(a.datetime) = ? AND a.person_type = 'staff'
            LEFT JOIN staff_salary_history sh ON s.id = sh.staff_id AND sh.month = ? AND sh.year = ?
            WHERE s.created_by = ?
            GROUP BY s.id";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("iiiiis", $days_in_month, $month, $year, $month, $year, $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $total += $row['to_pay'];
    }

    return number_format($total, 0, ',', ' ');
}

function get_unpaid_salaries_current_month() {
    global $link;
    if (!isset($_SESSION['userid'])) return 0;
    $director_id = $_SESSION['userid'];
    $sql = "SELECT created_by FROM director WHERE userid = ?";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("s", $director_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result || $result->num_rows === 0) return 0;
    $admin_row = $result->fetch_assoc();
    $admin_id = $admin_row['created_by'];

    $month = date('n');
    $year = date('Y');
    $days_in_month = date('t');
    $total = 0;

    // Enseignants impayés
    $sql = "SELECT COALESCE(th.final_salary, ROUND(t.salary * COUNT(DATE(a.datetime)) / ?)) AS to_pay, th.payment_date
            FROM teachers t
            LEFT JOIN attendance a ON t.id = a.attendedid AND MONTH(a.datetime) = ? AND YEAR(a.datetime) = ? AND a.person_type = 'teacher'
            LEFT JOIN teacher_salary_history th ON t.id = th.teacher_id AND th.month = ? AND th.year = ?
            WHERE t.created_by = ?
            GROUP BY t.id";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("iiiiis", $days_in_month, $month, $year, $month, $year, $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (!$row['payment_date']) $total += $row['to_pay'];
    }

    // Personnel impayé
    $sql = "SELECT COALESCE(sh.final_salary, ROUND(s.salary * COUNT(DATE(a.datetime)) / ?)) AS to_pay, sh.payment_date
            FROM staff s
            LEFT JOIN attendance a ON s.id = a.attendedid AND MONTH(a.datetime) = ? AND YEAR(a.datetime) = ? AND a.person_type = 'staff'
            LEFT JOIN staff_salary_history sh ON s.id = sh.staff_id AND sh.month = ? AND sh.year = ?
            WHERE s.created_by = ?
            GROUP BY s.id";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("iiiiis", $days_in_month, $month, $year, $month, $year, $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (!$row['payment_date']) $total += $row['to_pay'];
    }

    return number_format($total, 0, ',', ' ');
}

