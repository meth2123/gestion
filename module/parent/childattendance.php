<?php
include_once('main.php');
include_once('../../service/mysqlcon.php');
include_once('../../service/db_utils.php');

// Récupération des informations du parent
$parent_info = db_fetch_row(
    "SELECT * FROM parents WHERE id = ?",
    [$check],
    's'
);

if (!$parent_info) {
    header("Location: ../../?error=parent_not_found");
    exit();
}

// Récupération de la liste des enfants
$children = db_fetch_all(
    "SELECT s.*, c.name as class_name 
     FROM students s 
     LEFT JOIN class c ON s.classid = c.id
     WHERE s.parentid = ? 
     ORDER BY s.name",
    [$check],
    's'
);

// Récupérer l'enfant sélectionné
$selected_child_id = $_GET['child_id'] ?? ($children[0]['id'] ?? '');
$selected_period = $_GET['period'] ?? 'thismonth'; // thismonth, all

// Récupérer les présences de l'enfant sélectionné
$attendances = [];
if ($selected_child_id) {
    // Vérifier que l'enfant appartient bien au parent
    $child_check = false;
    foreach ($children as $child) {
        if ($child['id'] === $selected_child_id) {
            $child_check = true;
            break;
        }
    }
    
    if ($child_check) {
        $query = "SELECT sa.*, c.name as course_name, cl.name as class_name
                  FROM student_attendance sa
                  JOIN course c ON sa.course_id = c.id
                  JOIN class cl ON sa.class_id = cl.id
                  WHERE CAST(sa.student_id AS CHAR) = CAST(? AS CHAR)";
        
        $params = [$selected_child_id];
        $types = 's';
        
        if ($selected_period === 'thismonth') {
            $query .= " AND MONTH(sa.datetime) = MONTH(CURRENT_DATE) 
                       AND YEAR(sa.datetime) = YEAR(CURRENT_DATE)";
        }
        
        $query .= " ORDER BY sa.datetime DESC";
        
        $attendances = db_fetch_all($query, $params, $types);
    }
}

// Calculer les statistiques
$stats = [
    'total' => 0,
    'present' => 0,
    'absent' => 0,
    'late' => 0,
    'excused' => 0
];

foreach ($attendances as $attendance) {
    $stats['total']++;
    $status = $attendance['status'] ?? 'present';
    if (isset($stats[$status])) {
        $stats[$status]++;
    }
}

ob_start();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Présences des Enfants</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .status-badge {
            font-size: 0.85em;
            padding: 4px 8px;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Header -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="../../source/logo.jpg" class="rounded-circle me-2" width="40" height="40" alt="Logo"/>
                Système de Gestion Scolaire
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text text-light me-3">Bonjour, <?= htmlspecialchars($parent_info['fathername']) ?></span>
                <a href="logout.php" class="btn btn-danger btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i>Déconnexion
                </a>
            </div>
        </div>
    </nav>

    <!-- Navigation -->
    <nav class="bg-white shadow-sm mb-4">
        <div class="container">
            <div class="nav nav-pills py-2">
                <a class="nav-link" href="index.php">
                    <i class="fas fa-home me-1"></i>Accueil
                </a>
                <a class="nav-link" href="checkchild.php">
                    <i class="fas fa-child me-1"></i>Informations Enfants
                </a>
                <a class="nav-link active" href="childattendance.php">
                    <i class="fas fa-calendar-check me-1"></i>Présences
                </a>
                <a class="nav-link" href="childreport.php">
                    <i class="fas fa-file-alt me-1"></i>Bulletins
                </a>
                <a class="nav-link" href="childpayment.php">
                    <i class="fas fa-money-bill-wave me-1"></i>Paiements
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <h2 class="h3 fw-bold mb-4"><i class="fas fa-calendar-check me-2"></i>Présences des Enfants</h2>

        <?php if (empty($children)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>Aucun enfant associé à votre compte.
            </div>
        <?php else: ?>
            <!-- Sélection de l'enfant et période -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-6">
                            <label for="child_id" class="form-label">Enfant</label>
                            <select name="child_id" id="child_id" class="form-select" onchange="this.form.submit()">
                                <?php foreach ($children as $child): ?>
                                    <option value="<?= htmlspecialchars($child['id']) ?>" 
                                            <?= $selected_child_id == $child['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($child['name']) ?> - <?= htmlspecialchars($child['class_name'] ?? 'Sans classe') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="period" class="form-label">Période</label>
                            <select name="period" id="period" class="form-select" onchange="this.form.submit()">
                                <option value="thismonth" <?= $selected_period === 'thismonth' ? 'selected' : '' ?>>Ce mois</option>
                                <option value="all" <?= $selected_period === 'all' ? 'selected' : '' ?>>Tout l'historique</option>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Statistiques -->
            <?php if ($selected_child_id && $stats['total'] > 0): ?>
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center border-primary">
                            <div class="card-body">
                                <h5 class="card-title text-primary"><?= $stats['total'] ?></h5>
                                <p class="card-text text-muted small">Total</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center border-success">
                            <div class="card-body">
                                <h5 class="card-title text-success"><?= $stats['present'] ?></h5>
                                <p class="card-text text-muted small">Présents</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center border-danger">
                            <div class="card-body">
                                <h5 class="card-title text-danger"><?= $stats['absent'] ?></h5>
                                <p class="card-text text-muted small">Absents</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center border-warning">
                            <div class="card-body">
                                <h5 class="card-title text-warning"><?= $stats['late'] + $stats['excused'] ?></h5>
                                <p class="card-text text-muted small">Retards/Excusés</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Liste des présences -->
            <?php if ($selected_child_id): ?>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Historique des présences</h5>
                        <?php if (!empty($attendances)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Heure</th>
                                            <th>Cours</th>
                                            <th>Classe</th>
                                            <th>Statut</th>
                                            <th>Commentaire</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($attendances as $attendance): ?>
                                            <?php
                                            $datetime = new DateTime($attendance['datetime']);
                                            $status = $attendance['status'] ?? 'present';
                                            $badge_class = [
                                                'present' => 'bg-success',
                                                'absent' => 'bg-danger',
                                                'late' => 'bg-warning',
                                                'excused' => 'bg-info'
                                            ];
                                            $status_text = [
                                                'present' => 'Présent',
                                                'absent' => 'Absent',
                                                'late' => 'En retard',
                                                'excused' => 'Excusé'
                                            ];
                                            ?>
                                            <tr>
                                                <td><?= $datetime->format('d/m/Y') ?></td>
                                                <td><?= $datetime->format('H:i') ?></td>
                                                <td><?= htmlspecialchars($attendance['course_name']) ?></td>
                                                <td><?= htmlspecialchars($attendance['class_name']) ?></td>
                                                <td>
                                                    <span class="badge <?= $badge_class[$status] ?? 'bg-secondary' ?> status-badge">
                                                        <?= $status_text[$status] ?? $status ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($attendance['comment'] ?? '-') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>Aucune présence enregistrée pour cette période.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
$content = ob_get_clean();
// Pour les parents, on n'utilise pas le layout.php comme les autres modules
echo $content;
?>
