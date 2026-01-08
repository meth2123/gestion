<?php
include_once('main.php');
include_once('includes/auth_check.php');
require_once('../../db/config.php');
include_once('../../service/db_utils.php');

// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// L'ID de l'administrateur est déjà défini dans auth_check.php
// $admin_id = $_SESSION['login_id'];

// Inclure la configuration des mois scolaires
require_once(__DIR__ . '/../../school_config.php');

// Fonction pour obtenir l'année scolaire actuelle
function getCurrentSchoolYear() {
    $currentMonth = date('n');
    $currentYear = date('Y');
    
    // Si nous sommes entre janvier et juin, l'année scolaire a commencé l'année précédente
    if ($currentMonth >= 1 && $currentMonth <= 6) {
        return (string)($currentYear - 1);
    }
    
    // Si nous sommes entre septembre et décembre, l'année scolaire commence cette année
    return (string)$currentYear;
}

// Fonction pour obtenir le mois courant de l'année scolaire
function getCurrentSchoolMonth() {
    return date('n');
}

// Fonction pour obtenir les classes
function getClasses() {
    global $admin_id;
    return db_fetch_all("
        SELECT DISTINCT c.* 
        FROM class c 
        INNER JOIN students s ON c.id = s.classid 
        WHERE s.created_by = ? 
        ORDER BY c.name", 
        [$admin_id], 
        's'
    );
}

// Fonction pour obtenir les élèves qui n'ont pas payé pour un mois donné
function getUnpaidStudents($month = null, $year = null, $class_id = null) {
    global $admin_id;
    
    // Si le mois n'est pas spécifié, utiliser le mois courant
    if ($month === null) {
        $month = getCurrentSchoolMonth();
    }
    
    // Si l'année n'est pas spécifiée, utiliser l'année scolaire courante
    if ($year === null) {
        $year = getCurrentSchoolYear();
        // Ajuster l'année pour les mois de janvier à juin
        if ($month >= 1 && $month <= 6) {
            $year += 1;
        }
    }
    
    $query = "
        SELECT 
            s.id, 
            s.name as student_name, 
            s.classid,
            c.name as class_name,
            CONCAT(p.fathername, ' / ', p.mothername) as parent_name,
            p.fatherphone as parent_phone,
            cpa.amount as payment_amount,
            (
                SELECT COUNT(*) 
                FROM payment 
                WHERE studentid = s.id AND month = ? AND year = ?
            ) as has_paid_current_month,
            (
                SELECT COUNT(*) 
                FROM payment 
                WHERE studentid = s.id
            ) as total_payments,
            (
                SELECT GROUP_CONCAT(CONCAT(p_months.month, '-', p_months.year))
                FROM (
                    SELECT '9' as month, ? as year UNION
                    SELECT '10' as month, ? as year UNION
                    SELECT '11' as month, ? as year UNION
                    SELECT '12' as month, ? as year UNION
                    SELECT '1' as month, ? as year UNION
                    SELECT '2' as month, ? as year UNION
                    SELECT '3' as month, ? as year UNION
                    SELECT '4' as month, ? as year UNION
                    SELECT '5' as month, ? as year UNION
                    SELECT '6' as month, ? as year
                ) p_months
                LEFT JOIN payment p ON 
                    p.studentid = s.id AND 
                    p.month = p_months.month AND 
                    p.year = p_months.year
                WHERE p.id IS NULL
            ) as unpaid_months
        FROM 
            students s
        JOIN 
            class c ON s.classid = c.id
        LEFT JOIN 
            parents p ON s.parentid = p.id
        LEFT JOIN 
            class_payment_amount cpa ON c.id = cpa.class_id
        WHERE 
            s.created_by = ?
            AND (
                SELECT COUNT(*) 
                FROM payment 
                WHERE studentid = s.id AND month = ? AND year = ?
            ) = 0
    ";
    
    // Utiliser l'année sélectionnée pour les mois de septembre à décembre
    // et l'année suivante pour les mois de janvier à juin (seconde moitié de l'année scolaire)
    $year_value = (string)$year;
    $next_year_value = (string)((int)$year + 1);
    
    // Année à utiliser pour le mois sélectionné (pour la vérification du paiement)
    $current_month_year = ($month >= 1 && $month <= 6) ? $next_year_value : $year_value;
    
    $params = [
        $month, $current_month_year, // Pour la vérification du paiement du mois courant
        $year_value, $year_value, $year_value, $year_value, // Pour les mois de septembre à décembre (année de début)
        $next_year_value, $next_year_value, $next_year_value, $next_year_value, $next_year_value, $next_year_value, // Pour les mois de janvier à juin (année suivante)
        $admin_id, // Pour filtrer par administrateur
        $month, $current_month_year // Pour la vérification finale du paiement du mois courant
    ];
    $types = 'sssssssssssssss'; // Tous les paramètres sont des chaînes de caractères
    
    if ($class_id !== null) {
        $query .= " AND s.classid = ?";
        $params[] = $class_id;
        $types .= 's';
    }
    
    $query .= " ORDER BY c.name, s.name";
    
    return db_fetch_all($query, $params, $types);
}

// Récupérer les filtres
$selected_month = isset($_GET['month']) ? $_GET['month'] : getCurrentSchoolMonth();
$selected_year = isset($_GET['year']) ? $_GET['year'] : getCurrentSchoolYear();
$selected_class = isset($_GET['class_id']) ? $_GET['class_id'] : null;

// S'assurer que les valeurs sont des chaînes
$selected_month = (string)$selected_month;
$selected_year = (string)$selected_year;

// Récupérer les données
$classes = getClasses();
$unpaid_students = getUnpaidStudents($selected_month, $selected_year, $selected_class);
$months = $school_months;

// Début du contenu
ob_start();
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Élèves en retard de paiement</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Tableau de bord</a></li>
        <li class="breadcrumb-item"><a href="payment.php">Paiements</a></li>
        <li class="breadcrumb-item active">Retards de paiement</li>
    </ol>
    
    <!-- Filtres -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Filtres</h2>
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="month" class="form-label">Mois</label>
                    <select id="month" name="month" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($months as $month_num => $month_name): ?>
                            <option value="<?php echo $month_num; ?>" <?php echo $selected_month == $month_num ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($month_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="year" class="form-label">Année</label>
                    <select id="year" name="year" class="form-select" onchange="this.form.submit()">
                        <?php 
                        $current_year = (int)date('Y');
                        // Créer un tableau d'années scolaires de 2020 à l'année actuelle + 5 ans
                        $years = array();
                        for ($y = 2020; $y <= $current_year + 5; $y++) {
                            $years[$y] = $y . '-' . ($y + 1);
                        }
                        
                        foreach ($years as $year_value => $year_label): 
                        ?>
                            <option value="<?php echo $year_value; ?>" <?php echo $selected_year == (string)$year_value ? 'selected' : ''; ?>>
                                <?php echo $year_label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="class_id" class="form-label">Classe</label>
                    <select id="class_id" name="class_id" class="form-select" onchange="this.form.submit()">
                        <option value="">Toutes les classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo htmlspecialchars($class['id']); ?>" <?php echo $selected_class === $class['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Liste des élèves en retard de paiement -->
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Élèves en retard de paiement pour <?php echo htmlspecialchars($months[$selected_month] ?? 'Mois inconnu'); ?> <?php echo htmlspecialchars($selected_year ?? ''); ?></h2>
                
                <?php if (!empty($unpaid_students)): ?>
                <div>
                    <button class="btn btn-sm btn-outline-primary" onclick="window.print()">
                        <i class="fas fa-print me-1"></i>Imprimer
                    </button>
                    <a href="#" class="btn btn-sm btn-outline-success" onclick="exportToExcel()">
                        <i class="fas fa-file-excel me-1"></i>Exporter
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (empty($unpaid_students)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    Tous les élèves ont payé pour ce mois.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="unpaidTable">
                        <thead class="table-light">
                            <tr>
                                <th>Élève</th>
                                <th>Classe</th>
                                <th>Parent</th>
                                <th>Contact</th>
                                <th>Montant dû</th>
                                <th>Mois impayés</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unpaid_students as $student): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['class_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['parent_name'] ?? 'Non assigné'); ?></td>
                                    <td>
                                        <?php if (!empty($student['parent_phone'])): ?>
                                            <a href="tel:<?php echo htmlspecialchars($student['parent_phone']); ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($student['parent_phone']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Non disponible</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($student['payment_amount'])): ?>
                                            <?php echo number_format($student['payment_amount'], 2); ?> FCFA
                                        <?php else: ?>
                                            <span class="text-danger">Non défini</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if (!empty($student['unpaid_months'])) {
                                            $unpaid_months_array = explode(',', $student['unpaid_months']);
                                            $filtered_unpaid_months = [];
                                            $formatted_months = [];
                                            foreach ($unpaid_months_array as $month_year) {
                                                if (strpos($month_year, '-') !== false) {
                                                    list($month, $year) = explode('-', $month_year);
                                                    if (isset($months[$month])) {
                                                        $filtered_unpaid_months[] = $month_year;
                                                        $formatted_months[] = $months[$month] . ' ' . $year;
                                                    }
                                                }
                                            }
                                            echo '<span class="badge bg-danger">' . count($filtered_unpaid_months) . ' mois</span> ';
                                            echo '<span class="small text-muted">' . implode(', ', $formatted_months) . '</span>';
                                        } else {
                                            echo '<span class="text-success">Aucun</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <a href="payment.php?action=add_payment&student_id=<?php echo $student['id']; ?>&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-plus-circle me-1"></i>Enregistrer paiement
                                        </a>
                                        <?php if (!empty($student['parent_phone'])): ?>
                                            <button class="btn btn-sm btn-outline-info mt-1" onclick="sendSMS('<?php echo htmlspecialchars($student['id'] ?? ''); ?>', '<?php echo htmlspecialchars($student['parent_phone'] ?? ''); ?>', '<?php echo htmlspecialchars($student['student_name'] ?? ''); ?>', '<?php echo htmlspecialchars($months[$selected_month] ?? 'Mois inconnu'); ?>', '<?php echo htmlspecialchars($selected_year ?? ''); ?>', '<?php echo htmlspecialchars($student['payment_amount'] ?? ''); ?>')">
                                                <i class="fas fa-sms me-1"></i>Envoyer rappel
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function exportToExcel() {
    // Créer un élément temporaire pour le téléchargement
    var a = document.createElement('a');
    
    // Récupérer la table
    var table = document.getElementById('unpaidTable');
    
    // Créer une chaîne CSV
    var csv = [];
    var rows = table.querySelectorAll('tr');
    
    for (var i = 0; i < rows.length; i++) {
        var row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (var j = 0; j < cols.length - 1; j++) { // Ignorer la colonne Actions
            // Nettoyer le texte (supprimer les balises HTML)
            var text = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, ' ').trim();
            row.push('"' + text + '"');
        }
        
        csv.push(row.join(','));
    }
    
    // Télécharger le fichier CSV
    var csvFile = new Blob([csv.join('\n')], {type: 'text/csv'});
    a.href = URL.createObjectURL(csvFile);
    a.download = 'retards_paiement_<?php echo $months[$selected_month] . '_' . $selected_year; ?>.csv';
    a.click();
}

function sendSMS(studentId, phone, studentName, month, year, amount) {
    if (confirm('Voulez-vous envoyer un SMS de rappel au parent de ' + studentName + ' pour le paiement de ' + month + ' ' + year + ' ?')) {
        // Afficher un indicateur de chargement
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Envoi en cours...';
        btn.disabled = true;
        
        // Envoyer la requête AJAX
        $.ajax({
            url: 'send_payment_reminder.php',
            type: 'POST',
            data: {
                student_id: studentId,
                phone: phone,
                student_name: studentName,
                month: month,
                year: year,
                amount: amount
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Afficher un message de succès
                    showAlert('success', '<i class="fas fa-check-circle me-2"></i>SMS envoyé avec succès au ' + phone);
                } else {
                    // Afficher un message d'erreur
                    showAlert('danger', '<i class="fas fa-exclamation-circle me-2"></i>Erreur lors de l\'envoi du SMS: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                // Afficher un message d'erreur
                showAlert('danger', '<i class="fas fa-exclamation-circle me-2"></i>Erreur lors de l\'envoi du SMS: ' + error);
            },
            complete: function() {
                // Rétablir le bouton
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        });
    }
}

function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-' + type + ' alert-dismissible fade show';
    alertDiv.innerHTML = message + 
        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    
    const container = document.querySelector('.container-fluid');
    container.insertBefore(alertDiv, container.firstChild);
    
    // Faire défiler vers le haut pour voir l'alerte
    window.scrollTo({ top: 0, behavior: 'smooth' });
    
    // Supprimer automatiquement l'alerte après 5 secondes
    setTimeout(function() {
        alertDiv.classList.remove('show');
        setTimeout(function() {
            alertDiv.remove();
        }, 150);
    }, 5000);
}
</script>

<?php
$content = ob_get_clean();
include('templates/layout.php');
?>
