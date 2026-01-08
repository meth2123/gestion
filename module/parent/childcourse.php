<?php
include_once('main.php');
include_once('../../service/db_utils.php');

// Vérification de l'ID de l'élève
$student_id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$student_id) {
    header("Location: checkchild.php?error=no_student_selected");
    exit();
}

// Vérification que l'élève appartient bien au parent connecté
$student = db_fetch_row(
    "SELECT s.*, c.name as class_name 
     FROM students s 
     LEFT JOIN class c ON s.classid = c.id
     WHERE s.id = ? AND s.parentid = ?",
    [$student_id, $check],
    'ss'
);

if (!$student) {
    header("Location: checkchild.php?error=unauthorized");
    exit();
}

// Récupérer le semestre sélectionné depuis l'URL ou utiliser 0 (tous) par défaut
$selected_semester = isset($_GET['semester']) ? intval($_GET['semester']) : 0;

// Vérifier si la table des coefficients spécifiques aux classes existe
$check_table = db_fetch_row(
    "SHOW TABLES LIKE 'class_course_coefficients'"
);

// Construction de la requête pour récupérer les notes
if ($check_table) {
    // Utiliser la table des coefficients spécifiques aux classes
    $grades_query = "
        SELECT DISTINCT
            c.name as course_name,
            COALESCE(ccc.coefficient, c.coefficient, 1) as course_coefficient,
            stc.grade_type,
            stc.grade_number,
            stc.grade,
            stc.semester,
            t.name as teacher_name,
            t.email as teacher_email,
            DATE_FORMAT(stc.created_at, '%d/%m/%Y') as grade_date
        FROM student_teacher_course stc
        JOIN course c ON stc.course_id = c.id
        JOIN teachers t ON stc.teacher_id = t.id
        LEFT JOIN class_course_coefficients ccc ON c.id = ccc.course_id AND ccc.class_id = stc.class_id
        WHERE stc.student_id = ?
        AND stc.class_id = ?
        AND stc.grade IS NOT NULL";
} else {
    // Fallback sur l'ancienne méthode
    $grades_query = "
        SELECT DISTINCT
            c.name as course_name,
            COALESCE(c.coefficient, 1) as course_coefficient,
            stc.grade_type,
            stc.grade_number,
            stc.grade,
            stc.semester,
            t.name as teacher_name,
            t.email as teacher_email,
            DATE_FORMAT(stc.created_at, '%d/%m/%Y') as grade_date
        FROM student_teacher_course stc
        JOIN course c ON stc.course_id = c.id
        JOIN teachers t ON stc.teacher_id = t.id
        WHERE stc.student_id = ?
        AND stc.class_id = ?
        AND stc.grade IS NOT NULL";
}

// Ajouter la condition de filtrage par semestre si nécessaire
if ($selected_semester > 0) {
    $grades_query .= " AND stc.semester = ?";
}

// Compléter la requête avec le tri
$grades_query .= " ORDER BY c.name, stc.semester, stc.grade_type, stc.grade_number, stc.created_at DESC";

// Récupérer la classe de l'étudiant
$student_class = db_fetch_row(
    "SELECT classid FROM students WHERE id = ?",
    [$student_id],
    's'
);

if (!$student_class || empty($student_class['classid'])) {
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <strong class="font-bold">Erreur !</strong>
            <span class="block sm:inline">Cet étudiant n\'a pas de classe assignée.</span>
          </div>';
    exit();
}

// Préparer les paramètres de la requête
$params = [$student_id, $student_class['classid']];
$types = 'ss';

// Ajouter le paramètre du semestre si nécessaire
if ($selected_semester > 0) {
    $params[] = $selected_semester;
    $types .= 'i';
}

$courses = db_fetch_all($grades_query, $params, $types);

// Fonction pour convertir une note en valeur numérique
function convertGradeToNumeric($grade) {
    // Si la note est déjà numérique, la retourner directement
    if (is_numeric($grade)) {
        return floatval($grade);
    }
    
    // Sinon, convertir la note alphabétique
    $grade = strtoupper(trim($grade));
    $gradeMap = [
        'A+' => 20, 'A' => 18, 'A-' => 16,
        'B+' => 15, 'B' => 14, 'B-' => 13,
        'C+' => 12, 'C' => 11, 'C-' => 10,
        'D+' => 9, 'D' => 8, 'D-' => 7,
        'F' => 0
    ];
    return $gradeMap[$grade] ?? 0;
}

// Organiser les cours par nom pour éviter les doublons dans le calcul de la moyenne
$course_data = [];
foreach ($courses as $course) {
    $course_name = $course['course_name'];
    if (!isset($course_data[$course_name])) {
        // S'assurer que le coefficient est correctement récupéré et converti en nombre
        $coefficient = isset($course['course_coefficient']) && !empty($course['course_coefficient']) 
            ? floatval($course['course_coefficient']) 
            : 1.0;
        
        $course_data[$course_name] = [
            'grades' => [],
            'coefficient' => $coefficient
        ];
    }
    
    if (!empty($course['grade'])) {
        // Convertir la note en valeur numérique
        $grade_value = convertGradeToNumeric($course['grade']);
        
        $course_data[$course_name]['grades'][] = [
            'value' => $grade_value,
            'type' => $course['grade_type'],
            'number' => $course['grade_number']
        ];
    }
}

// Calcul de la moyenne par matière puis de la moyenne générale pondérée
$total_weighted_sum = 0;
$total_coefficients = 0;
$course_averages = [];

foreach ($course_data as $course_name => $data) {
    if (!empty($data['grades'])) {
        $course_total = 0;
        $course_count = count($data['grades']);
        
        foreach ($data['grades'] as $grade) {
            $course_total += $grade['value'];
        }
        
        $course_average = $course_total / $course_count;
        $course_averages[$course_name] = $course_average;
        
        // Contribution pondérée à la moyenne générale
        $total_weighted_sum += $course_average * $data['coefficient'];
        $total_coefficients += $data['coefficient'];
    }
}

// Calcul de la moyenne générale pondérée
$average = $total_coefficients > 0 ? $total_weighted_sum / $total_coefficients : 0;

// La moyenne générale est déjà calculée ci-dessus

// Fonction pour déterminer l'appréciation en fonction de la note
function getAppreciation($note) {
    if ($note >= 16) return "Bien";
    if ($note >= 12) return "Assez bien";
    if ($note >= 9.99) return "Passable";
    return "Insuffisant";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cours et Notes - <?php echo htmlspecialchars($student['name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                    <span class="mr-4">Bonjour, <?php echo htmlspecialchars($login_session); ?></span>
                    <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition duration-200">
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
                <a href="checkchild.php" class="text-gray-600 hover:text-blue-500 px-3 py-2 rounded-md">
                    <i class="fas fa-child mr-2"></i>Mes Enfants
                </a>
                <a href="childattendance.php?id=<?php echo $student_id; ?>" class="text-gray-600 hover:text-blue-500 px-3 py-2 rounded-md">
                    <i class="fas fa-calendar-check mr-2"></i>Présences
                </a>
                <a href="childreport.php?id=<?php echo $student_id; ?>" class="text-gray-600 hover:text-blue-500 px-3 py-2 rounded-md">
                    <i class="fas fa-file-alt mr-2"></i>Bulletin
                </a>
                <a href="childpayment.php?id=<?php echo $student_id; ?>" class="text-gray-600 hover:text-blue-500 px-3 py-2 rounded-md">
                    <i class="fas fa-money-bill mr-2"></i>Paiements
                </a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <!-- En-tête de l'élève -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($student['name']); ?></h2>
                    <p class="text-gray-600"><?php echo htmlspecialchars($student['class_name']); ?></p>
                </div>
                
                <!-- Sélecteur de semestre -->
                <div class="mt-4 md:mt-0 md:mx-4">
                    <form action="" method="get" class="flex items-center">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($student_id); ?>">
                        <label for="semester" class="mr-2 text-gray-700">Semestre :</label>
                        <select name="semester" id="semester" class="form-select rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" onchange="this.form.submit()">
                            <option value="0" <?php echo $selected_semester == 0 ? 'selected' : ''; ?>>Tous les semestres</option>
                            <option value="1" <?php echo $selected_semester == 1 ? 'selected' : ''; ?>>Semestre 1</option>
                            <option value="2" <?php echo $selected_semester == 2 ? 'selected' : ''; ?>>Semestre 2</option>
                            <option value="3" <?php echo $selected_semester == 3 ? 'selected' : ''; ?>>Semestre 3</option>
                        </select>
                    </form>
                </div>
                
                <div class="mt-4 md:mt-0 text-right">
                    <div class="text-3xl font-bold <?php 
                        $avg_color = $average >= 16 ? 'text-green-600' : 
                                   ($average >= 12 ? 'text-blue-600' : 
                                   ($average >= 9.99 ? 'text-yellow-600' : 'text-red-600'));
                        echo $avg_color; 
                    ?>">
                        <?php echo number_format($average, 2); ?>/20
                    </div>
                    <div class="text-sm text-gray-600"><?php echo getAppreciation($average); ?></div>
                </div>
            </div>
        </div>

        <!-- Liste des cours -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Cours et Notes</h3>
            </div>
            
            <?php if (empty($courses)): ?>
                <div class="p-6 text-center text-gray-500">
                    Aucun cours n'est actuellement assigné à cet élève.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Cours
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Enseignant
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Note
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Équivalence
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($courses as $course): 
                                // Convertir la note alphabétique en valeur numérique
                                $grade_value = is_numeric($course['grade']) ? floatval($course['grade']) : convertGradeToNumeric($course['grade']);
                                
                                // Déterminer la couleur en fonction de la valeur numérique selon les nouveaux critères
                                $grade_color = $grade_value >= 16 ? 'text-green-600 bg-green-100' : 
                                             ($grade_value >= 12 ? 'text-blue-600 bg-blue-100' : 
                                             ($grade_value >= 9.99 ? 'text-yellow-600 bg-yellow-100' : 'text-red-600 bg-red-100'));
                            ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($course['course_name']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo htmlspecialchars($course['teacher_name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo htmlspecialchars($course['teacher_email']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php echo $grade_color; ?>">
                                            <?php echo htmlspecialchars($course['grade'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $course['grade'] ? number_format($grade_value, 2) . '/20' : 'N/A'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Légende des notes et moyenne générale détaillée -->
        <div class="mt-6 bg-white rounded-lg shadow-lg p-6">
            <div class="flex flex-col md:flex-row justify-between mb-6">
                <div>
                    <h4 class="text-lg font-semibold text-gray-900 mb-4">Légende des notes</h4>
                    <div class="grid grid-cols-2 md:grid-cols-2 gap-4">
                        <div class="flex items-center">
                            <span class="w-4 h-4 bg-green-100 rounded-full mr-2"></span>
                            <span class="text-sm text-gray-600">Bien (16-20/20)</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-4 h-4 bg-blue-100 rounded-full mr-2"></span>
                            <span class="text-sm text-gray-600">Assez bien (12-15.99/20)</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-4 h-4 bg-yellow-100 rounded-full mr-2"></span>
                            <span class="text-sm text-gray-600">Passable (9.99-12/20)</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-4 h-4 bg-red-100 rounded-full mr-2"></span>
                            <span class="text-sm text-gray-600">Insuffisant (0-9.99/20)</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-4 h-4 bg-gray-100 rounded-full mr-2"></span>
                            <span class="text-sm text-gray-600">N/A (Note non attribuée)</span>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 md:mt-0">
                    <h4 class="text-lg font-semibold text-gray-900 mb-4">Détail de la moyenne générale</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matière</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Moyenne</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Coefficient</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($course_averages as $course_name => $course_avg): 
                                    $coef = $course_data[$course_name]['coefficient'];
                                    $avg_color = $course_avg >= 16 ? 'text-green-600' : 
                                                ($course_avg >= 12 ? 'text-blue-600' : 
                                                ($course_avg >= 9.99 ? 'text-yellow-600' : 'text-red-600'));
                                ?>
                                <tr>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($course_name); ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm <?php echo $avg_color; ?>">
                                        <?php echo number_format($course_avg, 2); ?>/20
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500"><?php echo $coef; ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="bg-gray-50 font-medium">
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">Moyenne générale</td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm font-bold <?php 
                                        $avg_color = $average >= 16 ? 'text-green-600' : 
                                                   ($average >= 12 ? 'text-blue-600' : 
                                                   ($average >= 9.99 ? 'text-yellow-600' : 'text-red-600'));
                                        echo $avg_color; 
                                    ?>">
                                        <?php echo number_format($average, 2); ?>/20
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">Total: <?php echo number_format($total_coefficients, 1); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Animation des lignes du tableau au survol
    document.addEventListener('DOMContentLoaded', function() {
        const rows = document.querySelectorAll('tbody tr');
        rows.forEach(row => {
            row.addEventListener('mouseenter', () => {
                row.classList.add('bg-gray-50');
            });
            row.addEventListener('mouseleave', () => {
                row.classList.remove('bg-gray-50');
            });
        });
    });
    </script>
</body>
</html>

