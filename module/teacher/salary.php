<?php
include_once('main.php');
include_once('../../service/db_utils.php');

// Vérification de la session
if (!isset($_SESSION['teacher_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Définir la variable check pour le template layout.php
$check = $_SESSION['teacher_id'];
$teacher_id = $check;

// Récupérer les informations du professeur
$teacher_query = "SELECT * FROM teachers WHERE id = ?";
$teacher = db_fetch_row($teacher_query, [$teacher_id], 's');

// Récupérer l'historique des salaires avec conversion de type pour s'assurer que les valeurs sont correctement comparées
$salary_query = "SELECT * FROM teacher_salary_history 
                WHERE CONVERT(teacher_id USING utf8mb4) = CONVERT(? USING utf8mb4) 
                ORDER BY year DESC, month DESC";

$salary_data = db_fetch_all($salary_query, [$teacher_id], 's');

// Afficher les données pour le débogage
error_log("Teacher ID: $teacher_id, Nombre d'entrées de salaire trouvées: " . count($salary_data));

// Calculer le total des paiements avec conversion de type
$total_query = "SELECT SUM(final_salary) as total_amount 
                FROM teacher_salary_history 
                WHERE CONVERT(teacher_id USING utf8mb4) = CONVERT(? USING utf8mb4) 
                AND payment_date IS NOT NULL";

$total_result = db_fetch_row($total_query, [$teacher_id], 's');
$total_paid = $total_result ? $total_result['total_amount'] : 0;

// Fonction pour obtenir le nombre de jours dans un mois
function getDaysInMonth($month = null, $year = null) {
    // Si les paramètres ne sont pas fournis, utiliser le mois et l'année courants
    if ($month === null) $month = date('m');
    if ($year === null) $year = date('Y');
    
    // Utiliser date('t') qui retourne le nombre de jours dans un mois
    return date('t', mktime(0, 0, 0, $month, 1, $year));
}

// Calculer les statistiques du mois en cours
$current_month = date('m');
$current_year = date('Y');

// Convertir le mois en entier pour correspondre au type dans la base de données
$current_month_int = intval($current_month);

// 1. Vérifier d'abord si un paiement existe déjà pour ce mois
$current_month_query = "SELECT * FROM teacher_salary_history 
                       WHERE CONVERT(teacher_id USING utf8mb4) = CONVERT(? USING utf8mb4) 
                       AND month = ? AND year = ?";

$current_month_data = db_fetch_row($current_month_query, [$teacher_id, $current_month_int, $current_year], 'sis');

// 2. Si aucun paiement n'existe, calculer le salaire en fonction des présences
if (!$current_month_data) {
    // Obtenir le nombre de jours dans le mois
    $days_in_month = getDaysInMonth($current_month_int, $current_year);
    
    // Compter les jours de présence pour ce mois
    $attendance_query = "SELECT COUNT(*) as present_days 
                         FROM attendance 
                         WHERE CONVERT(attendedid USING utf8mb4) = CONVERT(? USING utf8mb4) 
                         AND MONTH(date) = ? 
                         AND YEAR(date) = ?";
    
    $attendance_data = db_fetch_row($attendance_query, [$teacher_id, $current_month_int, $current_year], 'sis');
    
    if ($attendance_data) {
        $days_present = $attendance_data['present_days'];
        $days_absent = $days_in_month - $days_present;
        
        // Calculer le salaire final en fonction des présences
        $base_salary = $teacher['salary'] ?? 0;
        $final_salary = round($base_salary * $days_present / $days_in_month);
        
        // Créer un objet avec les données calculées
        $current_month_data = [
            'month' => $current_month_int,
            'year' => $current_year,
            'base_salary' => $base_salary,
            'days_present' => $days_present,
            'days_absent' => $days_absent,
            'final_salary' => $final_salary,
            'payment_date' => null // Pas encore payé
        ];
    }
}

// Débogage pour le mois en cours
error_log("Recherche de données pour Teacher ID: $teacher_id, Mois: $current_month_int, Année: $current_year");

// Tableau des mois en français avec vérification de l'index
$month_names = [
    '01' => 'Janvier', '02' => 'Février', '03' => 'Mars',
    '04' => 'Avril', '05' => 'Mai', '06' => 'Juin',
    '07' => 'Juillet', '08' => 'Août', '09' => 'Septembre',
    '10' => 'Octobre', '11' => 'Novembre', '12' => 'Décembre'
];

// Pas de conversion, simple remplacement de la devise

// Fonction pour formater le mois
function formatMonth($month) {
    global $month_names;
    // S'assurer que le mois est sur 2 chiffres
    $month = str_pad($month, 2, '0', STR_PAD_LEFT);
    return $month_names[$month] ?? 'Mois inconnu';
}

// Préparation du contenu pour le template
$content = '';
$content .= '<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">Informations du Professeur</h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="p-4 rounded bg-primary bg-opacity-10 h-100">
                            <h6 class="text-primary mb-2">Salaire de Base</h6>
                            <p class="display-6 fw-bold mb-0">';
                                $base_salary = $teacher["salary"] ?? 0;
                                $content .= number_format((float)$base_salary, 2, ",", " ") . ' FCFA';
                            $content .= '</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-4 rounded bg-success bg-opacity-10 h-100">
                            <h6 class="text-success mb-2">Total des Paiements</h6>
                            <p class="display-6 fw-bold mb-0">';
                                $content .= number_format((float)$total_paid, 2, ",", " ") . ' FCFA';
                            $content .= '</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-4 rounded bg-info bg-opacity-10 h-100">
                            <h6 class="text-info mb-2">Mois en Cours</h6>
                            <p class="display-6 fw-bold mb-0">';
                                if ($current_month_data && isset($current_month_data["final_salary"])) {
                                    $current_salary = $current_month_data["final_salary"];
                                    $content .= number_format((float)$current_salary, 2, ",", " ") . ' FCFA';
                                } else {
                                    $content .= '<span class="text-muted fs-5">Non calculé</span>';
                                }
                            $content .= '</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Historique des Salaires</h5>
                <span class="badge bg-primary rounded-pill">' . count($salary_data) . ' entrées</span>
            </div>
            <div class="card-body">';

// Ajout du mois en cours dans l'historique s'il n'est pas déjà inclus
$current_month_in_history = false;
if ($current_month_data) {
    foreach ($salary_data as $salary) {
        if ($salary['month'] == $current_month_int && $salary['year'] == $current_year) {
            $current_month_in_history = true;
            break;
        }
    }
    
    // Si le mois en cours n'est pas dans l'historique, l'ajouter au début
    if (!$current_month_in_history) {
        array_unshift($salary_data, $current_month_data);
    }
}

if (!empty($salary_data)) {
    $content .= '<div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Mois/Année</th>
                    <th>Salaire de Base</th>
                    <th>Jours Présents</th>
                    <th>Jours Absents</th>
                    <th>Salaire Final</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($salary_data as $salary) {
        $content .= '<tr>
            <td>' . formatMonth($salary["month"]) . ' ' . $salary["year"] . '</td>
            <td>' . number_format((float)$salary["base_salary"], 2, ",", " ") . ' FCFA</td>
            <td>' . $salary["days_present"] . ' jours</td>
            <td>' . $salary["days_absent"] . ' jours</td>
            <td class="fw-bold">' . number_format((float)$salary["final_salary"], 2, ",", " ") . ' FCFA</td>
            <td>';
        
        if ($salary["payment_date"]) {
            $content .= '<span class="badge bg-success">Payé le ' . date("d/m/Y", strtotime($salary["payment_date"])) . '</span>';
        } else {
            $content .= '<span class="badge bg-danger">Non payé</span>';
        }
        
        $content .= '</td>
        </tr>';
    }
    
    $content .= '</tbody>
        </table>
    </div>';
} else {
    $content .= '<div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>Aucun historique de salaire trouvé.
    </div>';
}

$content .= '</div>
        </div>
    </div>
</div>';

// Inclure le template
include("templates/layout.php");

