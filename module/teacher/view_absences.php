<?php
include_once('main.php');
include_once('../../service/db_utils.php');

// Vérification de la session
if (!isset($_SESSION['teacher_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Utiliser la connexion $link créée par main.php
global $link;
$conn = $link;
if ($conn === null || !$conn) {
    die('Erreur de connexion à la base de données. Vérifiez les variables d\'environnement Railway.');
}

$teacher_id = $_SESSION['teacher_id'];
$student_id = $_GET['student'] ?? '';
$class_id = $_GET['class'] ?? '';

// Vérifier que l'enseignant a accès à cet élève
$student = db_fetch_row(
    "SELECT s.*, c.name as class_name 
     FROM students s
     JOIN class c ON s.classid = c.id
     JOIN student_teacher_course stc ON s.id = stc.student_id
     WHERE s.id = ? AND stc.teacher_id = ?",
    [$student_id, $teacher_id],
    'ss'
);

if (!$student) {
    die("Accès non autorisé à cet élève.");
}

// Récupérer les cours de l'enseignant pour cet élève
$courses = db_fetch_all(
    "SELECT DISTINCT c.id, c.name, t.name as teacher_name
     FROM course c
     JOIN student_teacher_course stc ON c.id = stc.course_id
     JOIN teachers t ON stc.teacher_id = t.id
     WHERE stc.student_id = ? AND stc.teacher_id = ?",
    [$student_id, $teacher_id],
    'ss'
);

// Récupérer les présences/absences depuis student_attendance (les 30 derniers jours)
$absences = db_fetch_all(
    "SELECT 
        DATE_FORMAT(sa.datetime, '%d/%m/%Y') as date,
        TIME(sa.datetime) as course_time,
        COALESCE(c.name, 'Cours supprimé') as course_name,
        COALESCE(t.name, 'Professeur non assigné') as teacher_name,
        sa.status,
        sa.comment,
        sa.datetime as raw_datetime
     FROM student_attendance sa
     LEFT JOIN course c ON sa.course_id = c.id
     LEFT JOIN teachers t ON CAST(c.teacherid AS CHAR) = CAST(t.id AS CHAR)
     WHERE CAST(sa.student_id AS CHAR) = CAST(? AS CHAR)
     AND CAST(sa.class_id AS CHAR) = CAST(? AS CHAR)
     AND sa.datetime >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
     AND sa.datetime <= NOW()
     ORDER BY sa.datetime DESC",
    [$student_id, $class_id],
    'ss'
);

// Calculer les statistiques
$total_absences = 0;
$justified_absences = 0;
$unjustified_absences = 0;

foreach ($absences as $absence) {
    // Compter uniquement les absences et retards
    if ($absence['status'] === 'absent' || $absence['status'] === 'late') {
        $total_absences++;
        // Si un commentaire existe, considérer comme justifié, sinon non justifié
        if (!empty($absence['comment']) && trim($absence['comment']) !== '') {
            $justified_absences++;
        } else {
            $unjustified_absences++;
        }
    }
}

// Fonction utilitaire pour gérer les valeurs nulles avec htmlspecialchars
function safe_html($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$content = '
<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-lg p-6">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Registre des Absences</h1>
            <p class="text-gray-600">Élève : ' . safe_html($student['name']) . ' - Classe : ' . safe_html($student['class_name']) . '</p>
        </div>

        <!-- Statistiques des absences -->
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-gray-50 p-4 rounded-lg">
                <p class="text-sm text-gray-600">Total des absences</p>
                <p class="text-2xl font-bold text-gray-900">' . $total_absences . '</p>
            </div>
            <div class="bg-green-50 p-4 rounded-lg">
                <p class="text-sm text-gray-600">Absences justifiées</p>
                <p class="text-2xl font-bold text-green-600">' . $justified_absences . '</p>
            </div>
            <div class="bg-red-50 p-4 rounded-lg">
                <p class="text-sm text-gray-600">Absences non justifiées</p>
                <p class="text-2xl font-bold text-red-600">' . $unjustified_absences . '</p>
            </div>
        </div>

        <!-- Liste des absences -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Heure</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Matière</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Professeur</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Commentaire</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">';

if (!empty($absences)) {
    foreach ($absences as $absence) {
        $status = $absence['status'] ?? 'present';
        $status_class = '';
        $status_text = '';
        
        if ($status === 'present') {
            $status_class = 'text-green-600';
            $status_text = 'Présent';
        } elseif ($status === 'absent') {
            $status_class = 'text-red-600';
            $status_text = 'Absent';
        } elseif ($status === 'late') {
            $status_class = 'text-yellow-600';
            $status_text = 'En retard';
        } elseif ($status === 'excused') {
            $status_class = 'text-blue-600';
            $status_text = 'Excusé';
        } else {
            $status_class = 'text-gray-600';
            $status_text = ucfirst($status);
        }
        
        $justified_badge = '';
        if ($status === 'absent' || $status === 'late') {
            if (!empty($absence['comment']) && trim($absence['comment']) !== '') {
                $justified_badge = '<span class="ml-2 px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Justifiée</span>';
            } else {
                $justified_badge = '<span class="ml-2 px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Non justifiée</span>';
            }
        }
        
        $content .= '
            <tr>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">' . 
                    safe_html($absence['date']) . '</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">' . 
                    safe_html($absence['course_time'] ?? '') . '</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">' . 
                    safe_html($absence['course_name']) . '</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">' . 
                    safe_html($absence['teacher_name']) . '</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm ' . $status_class . '">' . 
                    safe_html($status_text) . $justified_badge . '</td>
                <td class="px-6 py-4 text-sm text-gray-600">' . 
                    safe_html($absence['comment'] ?? '-') . '</td>
            </tr>';
    }
} else {
    $content .= '
        <tr>
            <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                Aucune absence enregistrée pour les 30 derniers jours
            </td>
        </tr>';
}

$content .= '
                </tbody>
            </table>
        </div>

        <!-- Bouton d\'impression -->
        <div class="mt-8 text-center">
            <button onclick="window.print()" 
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Imprimer le registre
            </button>
        </div>
    </div>
</div>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    .container, .container * {
        visibility: visible;
    }
    .container {
        position: absolute;
        left: 0;
        top: 0;
    }
    button {
        display: none;
    }
}
</style>';

include('templates/layout.php');
?> 