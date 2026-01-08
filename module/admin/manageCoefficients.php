<?php
include_once('main.php');
include_once('../../service/db_utils.php');

// Définir l'ID de l'administrateur connecté
$admin_id = $_SESSION['login_id'];

// Vérification de la session admin
if (!isset($_SESSION['login_id'])) {
    header("Location: ../../index.php");
    exit();
}

$success_message = '';
$error_message = '';
$selected_class = $_GET['class_id'] ?? '';

// Récupération de toutes les classes
$classes = db_fetch_all(
    "SELECT DISTINCT c.* 
     FROM class c 
     INNER JOIN students s ON c.id = s.classid 
     WHERE s.created_by = ? 
     ORDER BY c.name",
    [$admin_id],
    's'
);

// Récupération des cours pour la classe sélectionnée
$class_courses = [];
if ($selected_class) {
    // Vérifier d'abord si la classe appartient à l'admin
    $class_check = db_fetch_row(
        "SELECT 1 FROM students WHERE classid = ? AND created_by = ? LIMIT 1",
        [$selected_class, $admin_id],
        'ss'
    );
    
    if ($class_check) {
        // Afficher les informations de débogage
        error_log("Recherche de cours pour la classe: $selected_class");
        
        // Vérifier si la table des coefficients spécifiques aux classes existe
        $check_table = db_fetch_row(
            "SHOW TABLES LIKE 'class_course_coefficients'"
        );
        
        // Approche 1: Récupérer les cours directement associés à cette classe
        // et compter les élèves associés via la table students
        if ($check_table) {
            // Utiliser la nouvelle table pour les coefficients spécifiques aux classes
            $direct_courses = db_fetch_all(
                "SELECT DISTINCT c.*, 
                    COALESCE(ccc.coefficient, c.coefficient, 1) as current_coefficient,
                    (SELECT COUNT(*) FROM students s WHERE s.classid = c.classid) as student_count
                 FROM course c 
                 LEFT JOIN class_course_coefficients ccc ON c.id = ccc.course_id AND ccc.class_id = ?
                 WHERE c.classid = ?
                 ORDER BY c.name",
                [$selected_class, $selected_class],
                'ss'
            );
        } else {
            // Fallback sur l'ancienne méthode si la table n'existe pas encore
            $direct_courses = db_fetch_all(
                "SELECT DISTINCT c.*, 
                    COALESCE(c.coefficient, 1) as current_coefficient,
                    (SELECT COUNT(*) FROM students s WHERE s.classid = c.classid) as student_count
                 FROM course c 
                 WHERE c.classid = ?
                 ORDER BY c.name",
                [$selected_class],
                's'
            );
        }
        
        // Approche 2: Récupérer les cours via student_teacher_course
        if ($check_table) {
            // Utiliser la nouvelle table pour les coefficients spécifiques aux classes
            $stc_courses = db_fetch_all(
                "SELECT DISTINCT c.*, 
                    COALESCE(ccc.coefficient, c.coefficient, 1) as current_coefficient,
                    COUNT(DISTINCT stc.student_id) as student_count
                 FROM course c 
                 INNER JOIN student_teacher_course stc ON c.id = stc.course_id 
                 LEFT JOIN class_course_coefficients ccc ON c.id = ccc.course_id AND ccc.class_id = ?
                 WHERE stc.class_id = ?
                 GROUP BY c.id
                 ORDER BY c.name",
                [$selected_class, $selected_class],
                'ss'
            );
        } else {
            // Fallback sur l'ancienne méthode si la table n'existe pas encore
            $stc_courses = db_fetch_all(
                "SELECT DISTINCT c.*, 
                    COALESCE(c.coefficient, 1) as current_coefficient,
                    COUNT(DISTINCT stc.student_id) as student_count
                 FROM course c 
                 INNER JOIN student_teacher_course stc ON c.id = stc.course_id 
                 WHERE stc.class_id = ?
                 GROUP BY c.id
                 ORDER BY c.name",
                [$selected_class],
                's'
            );
        }
        
        // Fusionner les deux ensembles de résultats en évitant les doublons
        $class_courses = [];
        $course_ids = [];
        
        // Ajouter d'abord les cours directs
        foreach ($direct_courses as $course) {
            $class_courses[] = $course;
            $course_ids[] = $course['id'];
        }
        
        // Ajouter ensuite les cours via student_teacher_course s'ils ne sont pas déjà inclus
        foreach ($stc_courses as $course) {
            if (!in_array($course['id'], $course_ids)) {
                $class_courses[] = $course;
                $course_ids[] = $course['id'];
            } else {
                // Si le cours existe déjà, mettre à jour le nombre d'élèves si nécessaire
                foreach ($class_courses as $key => $existing_course) {
                    if ($existing_course['id'] == $course['id'] && $course['student_count'] > $existing_course['student_count']) {
                        $class_courses[$key]['student_count'] = $course['student_count'];
                    }
                }
            }
        }
        
        // Compter le nombre total d'élèves dans la classe sélectionnée
        $total_students = db_fetch_row(
            "SELECT COUNT(*) as total FROM students WHERE classid = ?",
            [$selected_class],
            's'
        );
        
        $total_student_count = $total_students ? $total_students['total'] : 0;
        error_log("Nombre total d'élèves dans la classe $selected_class: $total_student_count");
        
        // Mettre à jour le nombre d'élèves pour les cours qui ont un comptage incorrect
        foreach ($class_courses as $key => $course) {
            // Si le nombre d'élèves est 0 ou supérieur au total, utiliser le total de la classe
            if ($course['student_count'] == 0 || $course['student_count'] > $total_student_count) {
                $class_courses[$key]['student_count'] = $total_student_count;
            }
        }
        
        // Afficher le nombre de cours trouvés pour le débogage
        error_log("Nombre de cours trouvés pour la classe $selected_class: " . count($class_courses));
        foreach ($class_courses as $course) {
            error_log("Cours trouvé: ID={$course['id']}, Nom={$course['name']}");
        }
    } else {
        $error_message = "Vous n'avez pas accès à cette classe.";
        $selected_class = '';
    }
}

// Traitement de la soumission des coefficients
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_coefficients'])) {
    try {
        // Vérifier si la table des coefficients spécifiques aux classes existe
        $check_table = db_fetch_row(
            "SHOW TABLES LIKE 'class_course_coefficients'"
        );
        
        // Créer la table si elle n'existe pas
        if (!$check_table) {
            db_query(
                "CREATE TABLE class_course_coefficients (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    class_id VARCHAR(50) NOT NULL,
                    course_id INT NOT NULL,
                    coefficient DECIMAL(3,1) NOT NULL DEFAULT 1.0,
                    created_by VARCHAR(50) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_class_course (class_id, course_id)
                )"
            );
        }
        
        foreach ($_POST['coefficients'] as $course_id => $coefficient) {
            if (is_numeric($coefficient) && $coefficient > 0) {
                // Insérer ou mettre à jour le coefficient spécifique à la classe
                db_query(
                    "INSERT INTO class_course_coefficients (class_id, course_id, coefficient, created_by)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE coefficient = ?, updated_at = NOW()",
                    [$selected_class, $course_id, $coefficient, $admin_id, $coefficient],
                    'sidsd'
                );
                
                // Mise à jour des coefficients dans student_teacher_course pour les examens de cette classe
                db_query(
                    "UPDATE student_teacher_course 
                     SET coefficient = ? 
                     WHERE course_id = ? 
                     AND class_id = ? 
                     AND grade_type = 'examen'",
                    [$coefficient, $course_id, $selected_class],
                    'dss'
                );
            }
        }
        $success_message = "Les coefficients ont été mis à jour avec succès.";
    } catch (Exception $e) {
        $error_message = "Une erreur est survenue lors de la mise à jour des coefficients: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Gestion des Coefficients - Administration</title>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Gestion des Coefficients</h1>
            <a href="index.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-gray-600 hover:bg-gray-700">
                Retour au tableau de bord
            </a>
        </div>

        <?php if ($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Bouton d'importation Excel -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 flex items-center justify-between">
            <div class="flex items-center">
                <svg class="w-6 h-6 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <div>
                    <h3 class="font-semibold text-gray-800">Importation en masse</h3>
                    <p class="text-sm text-gray-600">Téléversez un fichier Excel pour définir plusieurs coefficients à la fois</p>
                </div>
            </div>
            <a href="importCoefficients.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                </svg>
                Importer depuis Excel
            </a>
        </div>

        <!-- Formulaire de sélection de la classe -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="class_id" class="block text-sm font-medium text-gray-700 mb-2">Classe</label>
                    <select name="class_id" id="class_id" onchange="this.form.submit()"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Sélectionner une classe</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo htmlspecialchars($class['id']); ?>"
                                    <?php echo $selected_class === $class['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <?php if ($selected_class && !empty($class_courses)): ?>
            <!-- Formulaire des coefficients -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800">Coefficients des matières</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Définissez les coefficients pour chaque matière. Ces coefficients seront utilisés pour calculer les moyennes.
                    </p>
                </div>
                
                <form method="POST" class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matière</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre d'élèves</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Coefficient actuel</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nouveau coefficient</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($class_courses as $course): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($course['name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($course['student_count']); ?> élèves
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($course['current_coefficient']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <input type="number" 
                                                   name="coefficients[<?php echo htmlspecialchars($course['id']); ?>]"
                                                   value="<?php echo htmlspecialchars($course['current_coefficient']); ?>"
                                                   min="0.5" 
                                                   step="0.5"
                                                   class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-24 sm:text-sm border-gray-300 rounded-md">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <button type="submit" 
                                name="submit_coefficients"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Enregistrer les coefficients
                        </button>
                    </div>
                </form>
            </div>
        <?php elseif ($selected_class): ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative">
                Aucune matière trouvée pour cette classe.
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 