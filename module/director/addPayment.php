<?php
// Ajout de paiement adapté pour le module directeur
require_once(__DIR__ . '/check_director_access.php');
require_once('../../db/config.php');
require_once('../../service/db_utils.php');

// Récupérer l'admin_id lié au directeur
$director_id = $_SESSION['userid'] ?? $_SESSION['login_id'] ?? null;
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
$admin_id = $admin_row['created_by'] ?? $director_id;

// Récupérer les classes
$classes_sql = "SELECT DISTINCT c.id, c.name 
                FROM class c 
                INNER JOIN students s ON c.id = s.classid 
                WHERE s.created_by = ? 
                ORDER BY c.name";
$stmt = $link->prepare($classes_sql);
$stmt->bind_param("s", $admin_id);
$stmt->execute();
$classes_result = $stmt->get_result();

// Liste des élèves créés par cet admin (école du directeur) avec leur classe
$sql = "SELECT s.id, s.name, s.classid, c.name as class_name 
        FROM students s 
        LEFT JOIN class c ON s.classid = c.id 
        WHERE s.created_by = ? 
        ORDER BY c.name, s.name";
$stmt = $link->prepare($sql);
$stmt->bind_param("s", $admin_id);
$stmt->execute();
$students_result = $stmt->get_result();

// Stocker tous les étudiants dans un tableau pour le filtrage JavaScript
$all_students = [];
while ($student = $students_result->fetch_assoc()) {
    $all_students[] = $student;
}

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    try {
        // Validate input
        $student_id = trim($_POST['student_id']);
        $amount = floatval($_POST['amount']);
        $month = intval($_POST['month']);
        $year = intval($_POST['year']);

        // Basic validation
        if (empty($student_id) || $amount <= 0 || $month < 1 || $month > 12 || $year < 2000) {
            throw new Exception("Veuillez saisir des informations de paiement valides");
        }

        // Verify student exists and belongs to this admin
        $check_student = "SELECT id FROM students WHERE id = ? AND created_by = ?";
        $stmt = $link->prepare($check_student);
        $stmt->bind_param("ss", $student_id, $admin_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            throw new Exception("ID étudiant non trouvé ou non autorisé");
        }

        // Insert payment using prepared statement with created_by
        $sql = "INSERT INTO payment (studentid, amount, month, year, created_by) VALUES (?, ?, ?, ?, ?)";
        $stmt = $link->prepare($sql);
        $stmt->bind_param("sdiis", $student_id, $amount, $month, $year, $admin_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Erreur lors du traitement du paiement");
        }

        $success_message = "Paiement traité avec succès";
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un Paiement</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="JS/login_logout.js"></script>
		</head>
<body class="bg-gray-100">
    <!-- Header -->
    <div class="bg-white shadow-md">
        <div class="container mx-auto px-4 py-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <img src="../../source/logo.jpg" class="h-16 w-16 object-contain mr-4" alt="School Management System"/>
                    <h1 class="text-2xl font-bold text-gray-800">Système de Gestion Scolaire</h1>
                </div>
                <div class="flex items-center">
                    <span class="mr-4">Bonjour, <?php echo htmlspecialchars($_SESSION['name'] ?? 'Directeur');?> </span>
                    <a href="../../logout.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-sign-out-alt mr-2"></i>Déconnexion
                    </a>
                </div>
            </div>
        </div>
    </div>
    <!-- Navigation -->
    <nav class="bg-white shadow-md mt-4">
        <div class="container mx-auto px-4">
            <div class="flex space-x-4 py-4">
                <a href="index.php" class="text-gray-600 hover:text-blue-500 px-3 py-2 rounded-md">
                    <i class="fas fa-home mr-2"></i>Accueil
                </a>
                <a href="payment.php" class="text-gray-600 hover:text-blue-500 px-3 py-2 rounded-md">
                    <i class="fas fa-list mr-2"></i>Liste des Paiements
                </a>
                <a href="deletePayment.php" class="text-gray-600 hover:text-blue-500 px-3 py-2 rounded-md">
                    <i class="fas fa-trash mr-2"></i>Supprimer un Paiement
                </a>
            </div>
        </div>
    </nav>
    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-3xl mx-auto">
            <!-- Breadcrumb -->
            <nav class="mb-6" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2 text-sm text-gray-600">
                    <li><a href="index.php" class="hover:text-blue-500"><i class="fas fa-home"></i></a></li>
                    <li><i class="fas fa-chevron-right text-gray-400"></i></li>
                    <li><a href="payment.php" class="hover:text-blue-500">Paiements</a></li>
                    <li><i class="fas fa-chevron-right text-gray-400"></i></li>
                    <li class="text-gray-900 font-medium">Ajouter un paiement</li>
                </ol>
            </nav>
            
            <div class="bg-white rounded-xl shadow-lg p-8 border border-gray-100">
                <div class="flex items-center justify-between mb-8 pb-4 border-b border-gray-200">
                    <h2 class="text-3xl font-bold text-gray-800">
                        <i class="fas fa-plus-circle mr-3 text-blue-500"></i>
                        Ajouter un Paiement
                    </h2>
                    <a href="payment.php" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-xl"></i>
                    </a>
                </div>
                <?php if ($success_message): ?>
                    <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 text-green-700 rounded-lg shadow-sm">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-3 text-xl"></i>
                            <span class="font-medium"><?php echo htmlspecialchars($success_message); ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-lg shadow-sm">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-3 text-xl"></i>
                            <span class="font-medium"><?php echo htmlspecialchars($error_message); ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                <form action="" method="post" class="space-y-6">
                    <!-- Filtre par classe -->
                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-100">
                        <label for="class_filter" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-filter mr-2 text-blue-600"></i>Filtrer par classe
                        </label>
                        <select id="class_filter" 
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 bg-white py-2.5">
                            <option value="">Toutes les classes</option>
                            <?php 
                            $classes_result->data_seek(0); // Réinitialiser le pointeur
                            while ($class = $classes_result->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($class['id']); ?>">
                                    <?php echo htmlspecialchars($class['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <!-- Sélection de l'étudiant -->
                    <div>
                        <label for="student_id" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-user-graduate mr-2 text-blue-600"></i>Étudiant <span class="text-red-500">*</span>
                        </label>
                        <select id="student_id" name="student_id" required
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 bg-white py-2.5">
                            <option value="">Sélectionnez un étudiant</option>
                            <?php foreach ($all_students as $student): ?>
                                <option value="<?php echo htmlspecialchars($student['id']); ?>" 
                                        data-class="<?php echo htmlspecialchars($student['classid'] ?? ''); ?>"
                                        data-class-name="<?php echo htmlspecialchars($student['class_name'] ?? 'Sans classe'); ?>">
                                    <?php echo htmlspecialchars($student['name']) . ' - ' . htmlspecialchars($student['class_name'] ?? 'Sans classe'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="mt-2 text-sm text-gray-600 font-medium" id="student_count">
                            <i class="fas fa-info-circle mr-1"></i>
                            <?php echo count($all_students); ?> étudiant(s) disponible(s)
                        </p>
                    </div>
                    <div>
                        <label for="amount" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-money-bill-wave mr-2 text-green-600"></i>Montant <span class="text-red-500">*</span>
                        </label>
                        <div class="mt-1 relative rounded-lg shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <span class="text-gray-600 sm:text-sm font-bold">€</span>
                            </div>
                            <input type="number" name="amount" id="amount" required step="0.01" min="0"
                                   class="pl-10 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 bg-white py-2.5"
                                   placeholder="0.00">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="month" class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-calendar-alt mr-2 text-purple-600"></i>Mois <span class="text-red-500">*</span>
                            </label>
                            <select id="month" name="month" required
                                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 bg-white py-2.5">
                                <?php
                                $months = [
                                    1 => 'Janvier', 2 => 'Février', 3 => 'Mars',
                                    4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
                                    7 => 'Juillet', 8 => 'Août', 9 => 'Septembre',
                                    10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
                                ];
                                foreach ($months as $num => $name): ?>
                                    <option value="<?php echo $num; ?>" <?php echo date('n') == $num ? 'selected' : ''; ?> >
                                        <?php echo htmlspecialchars($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="year" class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-calendar mr-2 text-purple-600"></i>Année scolaire <span class="text-red-500">*</span>
                            </label>
                            <?php
                            try {
                                $school_years = db_fetch_all("SELECT year FROM school_years ORDER BY year DESC");
                                if (!$school_years) $school_years = [];
                            } catch (Exception $e) {
                                $school_years = [];
                            }
                            ?>
                            <div class="flex gap-2 items-end">
                                <select id="year" name="year" required
                                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 bg-white py-2.5">
                                    <?php foreach ($school_years as $y): ?>
                                        <option value="<?= htmlspecialchars($y['year']) ?>" <?= (date('Y') == $y['year']) ? 'selected' : '' ?>><?= htmlspecialchars($y['year']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <a href="school_years.php" class="inline-flex items-center px-3 py-2.5 bg-gray-200 hover:bg-gray-300 rounded-lg transition shadow-sm" title="Configurer les années scolaires">
                                    <i class="fas fa-cog"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 pt-6 mt-6 border-t border-gray-200">
                        <a href="payment.php" class="bg-gray-500 hover:bg-gray-600 text-white px-8 py-3 rounded-lg transition duration-200 shadow-md hover:shadow-lg font-medium">
                            <i class="fas fa-times mr-2"></i>
                            Annuler
                        </a>
                        <button type="submit" name="submit"
                                class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white px-8 py-3 rounded-lg transition duration-200 shadow-md hover:shadow-lg font-medium">
                            <i class="fas fa-save mr-2"></i>
                            Enregistrer le Paiement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    // Filtrage dynamique des étudiants par classe
    document.addEventListener('DOMContentLoaded', function() {
        const classFilter = document.getElementById('class_filter');
        const studentSelect = document.getElementById('student_id');
        const studentCount = document.getElementById('student_count');
        const allOptions = Array.from(studentSelect.options).slice(1); // Exclure l'option par défaut
        
        function filterStudents() {
            const selectedClass = classFilter.value;
            let visibleCount = 0;
            
            // Réinitialiser la liste
            studentSelect.innerHTML = '<option value="">Sélectionnez un étudiant</option>';
            
            // Filtrer les options
            allOptions.forEach(option => {
                const optionClass = option.getAttribute('data-class');
                if (!selectedClass || optionClass === selectedClass) {
                    studentSelect.appendChild(option.cloneNode(true));
                    visibleCount++;
                }
            });
            
            // Mettre à jour le compteur
            studentCount.textContent = visibleCount + ' étudiant(s) disponible(s)';
            
            // Réinitialiser la sélection si l'étudiant sélectionné n'est plus visible
            if (studentSelect.value && !Array.from(studentSelect.options).some(opt => opt.value === studentSelect.value && opt.value !== '')) {
                studentSelect.value = '';
            }
        }
        
        classFilter.addEventListener('change', filterStudents);
        
        // Initialiser le compteur
        filterStudents();
    });
    </script>
</body>
</html>
<?php
// Close database connection
if (isset($stmt)) $stmt->close();
if (isset($link)) $link->close();
?>
