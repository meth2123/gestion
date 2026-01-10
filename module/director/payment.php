<?php
// --- Démarrage de session et restriction d'accès ---
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] !== 'director') {
    header('Location: ../../login.php?error=Accès réservé au directeur');
    exit();
}

// --- Inclusion des fichiers nécessaires ---
require_once dirname(dirname(dirname(__FILE__))) . '/config.php';
require_once MYSQLCON_PATH;
require_once('../../service/db_utils.php');

// --- Récupérer l'admin_id lié au directeur ---
$director_id = $_SESSION['userid'];
global $link;
if ($link === null || !$link) {
    die('Erreur de connexion à la base de données. Vérifiez les variables d\'environnement Railway.');
}
$sql = "SELECT created_by FROM director WHERE userid = ?";
$stmt = $link->prepare($sql);
$stmt->bind_param("s", $director_id);
$stmt->execute();
$result = $stmt->get_result();
$admin_row = $result->fetch_assoc();

if (!$admin_row) {
    die("Aucun administrateur trouvé pour ce directeur.");
}
$admin_id = $admin_row['created_by'];

// --- Affichage des erreurs PHP ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Fonctions utilitaires ---
function getClasses() {
    global $admin_id;
    return db_fetch_all(
        "SELECT DISTINCT c.* 
         FROM class c 
         INNER JOIN students s ON c.id = s.classid 
         WHERE s.created_by = ? 
         ORDER BY c.name", 
        [$admin_id], 
        's'
    );
}

function getClassPaymentAmounts() {
    global $admin_id;
    return db_fetch_all("
        SELECT cpa.*, c.name as class_name 
        FROM class_payment_amount cpa 
        JOIN class c ON cpa.class_id = c.id 
        JOIN students s ON c.id = s.classid 
        WHERE s.created_by = ? 
        GROUP BY cpa.id, c.name 
        ORDER BY c.name",
        [$admin_id],
        's'
    );
}

function getPaymentHistory($filters = []) {
    global $admin_id;
    $query = "
        SELECT p.*, 
               s.name as student_name,
               c.name as class_name,
               CASE 
                   WHEN p.created_by LIKE 'ad-%' THEN 'Administration'
                   WHEN p.created_by LIKE 'pa-%' THEN 'Parent'
                   ELSE 'Autre'
               END as payment_source,
               a.name as admin_name
        FROM payment p
        JOIN students s ON p.studentid = s.id
        JOIN class c ON s.classid = c.id
        LEFT JOIN admin a ON p.created_by = a.id
        WHERE s.created_by = ?
    ";
    $params = [$admin_id];
    $types = 's';

    if (!empty($filters['class_id'])) {
        $query .= " AND s.classid = ?";
        $params[] = $filters['class_id'];
        $types .= 's';
    }

    if (!empty($filters['year'])) {
        $query .= " AND p.year = ?";
        $params[] = $filters['year'];
        $types .= 'i';
    }

    if (!empty($filters['month'])) {
        $query .= " AND p.month = ?";
        $params[] = $filters['month'];
        $types .= 'i';
    }

    $query .= " ORDER BY p.year DESC, p.month DESC, s.name ASC";

    return db_fetch_all($query, $params, $types);
}

// --- Traitement des actions POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'set_payment_amount') {
        $class_id = $_POST['class_id'];
        $amount = $_POST['amount'];

        $existing = db_fetch_row(
            "SELECT id FROM class_payment_amount WHERE class_id = ?",
            [$class_id],
            's'
        );

        if ($existing) {
            db_execute(
                "UPDATE class_payment_amount SET amount = ? WHERE class_id = ?",
                [$amount, $class_id],
                'ds'
            );
        } else {
            db_execute(
                "INSERT INTO class_payment_amount (class_id, amount) VALUES (?, ?)",
                [$class_id, $amount],
                'sd'
            );
        }
    }
}

// --- Création de la table (sécurité) ---
db_execute("
    CREATE TABLE IF NOT EXISTS class_payment_amount (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_id VARCHAR(20) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (class_id) REFERENCES class(id) ON DELETE CASCADE,
        UNIQUE KEY unique_class (class_id)
    )
");

// --- Récupération des données ---
$classes = getClasses();
$paymentAmounts = getClassPaymentAmounts();
$paymentHistory = getPaymentHistory($_GET);

// --- Paiements du mois en cours ---
$conn = $link;
$sql = "SELECT p.*, s.name as student_name 
        FROM payment p 
        INNER JOIN students s ON p.studentid = s.id 
        WHERE p.created_by = ?
        ORDER BY p.id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

// --- Construction de la vue ---
ob_start();
?>

<div class="container py-4">
    <!-- Logout Button -->
    <div class="d-flex justify-content-end mb-3">
        <a href="logout.php" class="btn btn-outline-danger">
            <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
        </a>
    </div>
    <!-- Section 1: Paiements du Mois en Cours -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h4 mb-0"><i class="fas fa-money-bill-wave me-2 text-primary"></i>Paiements du Mois en Cours</h2>
                <a href="unpaid_payments.php" class="btn btn-danger"><i class="fas fa-exclamation-circle me-2"></i>Élèves en retard de paiement</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Étudiant</th>
                            <th>Montant</th>
                            <th>Mois</th>
                            <th>Année</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['id']) ?></td>
                                    <td><?= htmlspecialchars($row['student_name']) ?> (<?= htmlspecialchars($row['studentid']) ?>)</td>
                                    <td><?= number_format($row['amount'], 2) ?> FCFA</td>
                                    <td><?= htmlspecialchars($row['month']) ?></td>
                                    <td><?= htmlspecialchars($row['year']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center text-muted py-3"><i class="fas fa-info-circle me-2"></i>Aucun paiement trouvé pour ce mois</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Section 2: Configuration des montants -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h4 mb-4">Configuration des montants des paiements</h2>
            <form method="POST" class="mb-4">
                <input type="hidden" name="action" value="set_payment_amount">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="class_id" class="form-label">Classe</label>
                        <select id="class_id" name="class_id" required class="form-select">
                            <option value="">Sélectionner une classe</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?= htmlspecialchars($class['id']) ?>"><?= htmlspecialchars($class['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="amount" class="form-label">Montant mensuel (FCFA)</label>
                        <input type="number" id="amount" name="amount" step="0.01" min="0" required class="form-control">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-success"><i class="fas fa-save me-2"></i>Enregistrer</button>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr><th>Classe</th><th>Montant mensuel</th><th>Dernière mise à jour</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($paymentAmounts)): ?>
                            <?php foreach ($paymentAmounts as $amount): ?>
                                <tr>
                                    <td><?= htmlspecialchars($amount['class_name']) ?></td>
                                    <td><?= number_format($amount['amount'], 2) ?> FCFA</td>
                                    <td><?= date('d/m/Y H:i', strtotime($amount['updated_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">Aucun montant configuré</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Section 3: Historique des paiements -->
    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="h4 mb-4">Historique des paiements</h2>
            <!-- Filtres -->
            <form method="GET" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Classe</label>
                        <select name="class_id" class="form-select">
                            <option value="">Toutes les classes</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?= $class['id'] ?>" <?= (isset($_GET['class_id']) && $_GET['class_id'] == $class['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($class['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Année</label>
                        <select name="year" class="form-select">
                            <option value="">Toutes les années</option>
                            <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                                <option value="<?= $y ?>" <?= (isset($_GET['year']) && $_GET['year'] == $y) ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Mois</label>
                        <select name="month" class="form-select">
                            <option value="">Tous les mois</option>
                            <?php 
                            $mois = [1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',10=>'Octobre',11=>'Novembre',12=>'Décembre'];
                            foreach ($mois as $num => $nom): ?>
                                <option value="<?= $num ?>" <?= (isset($_GET['month']) && $_GET['month'] == $num) ? 'selected' : '' ?>><?= $nom ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-2"></i>Filtrer</button>
                    </div>
                </div>
            </form>

            <!-- Historique -->
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Étudiant</th><th>Classe</th><th>Mois</th><th>Année</th>
                            <th>Montant</th><th>Source</th><th>Effectué par</th><th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($paymentHistory)): ?>
                            <?php foreach ($paymentHistory as $payment): ?>
                                <?php
                                $badge = 'bg-secondary';
                                if ($payment['payment_source'] === 'Administration') {
                                    $badge = 'bg-primary';
                                } elseif ($payment['payment_source'] === 'Parent') {
                                    $badge = 'bg-success';
                                }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($payment['student_name']) ?></td>
                                    <td><?= htmlspecialchars($payment['class_name']) ?></td>
                                    <td><?= $mois[$payment['month']] ?? $payment['month'] ?></td>
                                    <td><?= $payment['year'] ?></td>
                                    <td><?= number_format($payment['amount'], 2) ?> FCFA</td>
                                    <td><span class="badge <?= $badge ?>"><?= $payment['payment_source'] ?></span></td>
                                    <td><?= $payment['admin_name'] ?? '-' ?></td>
                                    <td><?= isset($payment['created_at']) ? date('d/m/Y H:i', strtotime($payment['created_at'])) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center text-muted py-3">Aucun paiement trouvé</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Boutons -->
    <div class="d-flex justify-content-center mt-4">
        <a href="addPayment.php" class="btn btn-primary me-2"><i class="fas fa-plus-circle me-2"></i>Ajouter un Paiement</a>
        <a href="deletePayment.php" class="btn btn-danger"><i class="fas fa-trash me-2"></i>Supprimer un Paiement</a>
    </div>
</div>

<?php
// Fermer le statement (ne pas fermer $conn car il est partagé)
$stmt->close();

// Intégrer dans le layout
$content = ob_get_clean();
include('templates/layout.php');
