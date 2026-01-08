<?php
include_once('main.php');
include_once('../../service/db_utils.php');

// Vérification de la session
if (!isset($_SESSION['login_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Connexion à la base de données
require_once('../../db/config.php');
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$teacher_id = $_SESSION['login_id'];
$course_id = $_GET['course_id'] ?? '';

if (empty($course_id)) {
    header("Location: exam.php");
    exit();
}
// Pas de traitement de formulaire d'appel

// Récupération des informations du cours et de l'enseignant

// Récupérer les détails du cours d'abord
$course_details = db_fetch_row(
    "SELECT c.id, c.name, c.classid, cl.name as class_name 
     FROM course c
     JOIN class cl ON c.classid = cl.id
     WHERE c.id = ? AND c.teacherid = ?",
    [$course_id, $teacher_id],
    'ss'
);



// Récupérer les détails de l'examen
$exam = db_fetch_row(
    "SELECT e.*, 
            c.name as course_name,
            c.id as course_id,
            cl.name as class_name,
            cl.id as class_id,
            DATE_FORMAT(e.examdate, '%d/%m/%Y') as formatted_date,
            TIME_FORMAT(e.time, '%H:%i') as formatted_time,
            CASE 
                WHEN e.examdate < CURDATE() THEN 'past'
                WHEN e.examdate = CURDATE() THEN 'today'
                ELSE 'upcoming'
            END as status
     FROM examschedule e
     JOIN course c ON BINARY e.courseid = BINARY c.id
     JOIN class cl ON BINARY c.classid = BINARY cl.id
     WHERE BINARY c.id = BINARY ? AND BINARY c.teacherid = BINARY ?",
    [$course_id, $teacher_id],
    'ss'
);



if (!$exam) {
    header("Location: exam.php?error=exam_not_found");
    exit();
}

// Récupérer la liste des étudiants inscrits à ce cours et qui appartiennent à la classe associée à l'examen
$students_query = "SELECT DISTINCT s.id, s.name, s.email, s.phone, s.classid
 FROM students s
 INNER JOIN student_teacher_course stc ON BINARY s.id = BINARY stc.student_id
 WHERE BINARY stc.course_id = BINARY ?
 AND BINARY stc.teacher_id = BINARY ?
 AND BINARY stc.class_id = BINARY ?
 AND BINARY s.classid = BINARY ?
 ORDER BY s.name";

$stmt = $conn->prepare($students_query);
$stmt->bind_param("ssss", $course_id, $teacher_id, $exam['class_id'], $exam['class_id']);
$stmt->execute();
$result = $stmt->get_result();
$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appel - <?php echo htmlspecialchars($exam['course_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <img src="../../source/logo.jpg" class="h-12 w-12 object-contain" alt="School Management System"/>
                    <h1 class="ml-4 text-xl font-semibold text-gray-800">Appel - <?php echo htmlspecialchars($exam['course_name']); ?></h1>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="exam.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-arrow-left mr-2"></i>Retour
                    </a>
                    <a href="index.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-home mr-2"></i>Accueil
                    </a>
                    <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-md text-sm font-medium transition duration-150 ease-in-out">
                        <i class="fas fa-sign-out-alt mr-2"></i>Déconnexion
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Contenu principal -->
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- En-tête de l'examen -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex justify-between items-start">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">
                        <?php echo htmlspecialchars($exam['course_name']); ?>
                    </h2>
                    <p class="text-gray-600">
                        <i class="fas fa-chalkboard-teacher mr-2"></i>
                        <?php echo htmlspecialchars($exam['class_name']); ?>
                    </p>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="px-3 py-1 rounded-full text-sm font-medium 
                        <?php echo $exam['status'] === 'upcoming' ? 'bg-green-100 text-green-800' : 
                                ($exam['status'] === 'today' ? 'bg-yellow-100 text-yellow-800' : 
                                'bg-gray-100 text-gray-800'); ?>">
                        <i class="fas <?php echo $exam['status'] === 'upcoming' ? 'fa-calendar-plus' : 
                                            ($exam['status'] === 'today' ? 'fa-calendar-day' : 
                                            'fa-calendar-check'); ?> mr-2"></i>
                        <?php echo $exam['status'] === 'upcoming' ? 'À venir' : 
                                ($exam['status'] === 'today' ? 'Aujourd\'hui' : 'Passé'); ?>
                    </span>
                </div>
            </div>
            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-calendar text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Date</p>
                        <p class="text-lg font-semibold text-gray-900"><?php echo date('d/m/Y'); ?></p>
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-clock text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Heure</p>
                        <p class="text-lg font-semibold text-gray-900"><?php echo date('H:i'); ?></p>
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <i class="fas fa-users text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Étudiants</p>
                        <p class="text-lg font-semibold text-gray-900"><?php echo count($students); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des étudiants inscrits -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Étudiants inscrits</h3>
            
            <div class="mt-6">
                <?php if (count($students) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Étudiant</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Téléphone</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($student['name']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($student['email']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($student['phone']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-500">
                        Aucun étudiant inscrit à ce cours.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        

    </div>

    <!-- Footer -->
    <footer class="bg-white shadow-lg mt-8">
        <div class="max-w-7xl mx-auto py-4 px-4">
            <p class="text-center text-gray-500 text-sm">
                &copy; <?php echo date('Y'); ?> Système de Gestion Scolaire. Tous droits réservés.
            </p>
        </div>
    </footer>
</body>
</html>