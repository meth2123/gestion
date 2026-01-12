<?php
include_once('main.php');
include_once('../../service/mysqlcon.php');
include_once('../../service/db_utils.php');

// Vérification de la session
if (!isset($_SESSION['student_id'])) {
    header("Location: ../../index.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// Récupération des informations de l'étudiant
$student_info = db_fetch_row(
    "SELECT s.*, c.id as class_id, c.name as class_name 
     FROM students s
     LEFT JOIN class c ON s.classid = c.id
     WHERE s.id = ?",
    [$student_id],
    's'
);

if (!$student_info) {
    header("Location: index.php?error=student_not_found");
    exit();
}

$class_id = $student_info['classid'];
$class_name = $student_info['class_name'] ?? 'Non assigné';

// Récupérer la période sélectionnée
$selected_period = $_GET['period'] ?? 'thismonth'; // thismonth, all

// Récupérer les présences/absences
$attendances = [];
$query = "SELECT sa.*, c.name as course_name, cl.name as class_name, t.name as teacher_name
          FROM student_attendance sa
          LEFT JOIN course c ON sa.course_id = c.id
          LEFT JOIN class cl ON sa.class_id = cl.id
          LEFT JOIN teachers t ON sa.teacher_id = t.id
          WHERE CAST(sa.student_id AS CHAR) = CAST(? AS CHAR)";

$params = [$student_id];
$types = 's';

if ($selected_period === 'thismonth') {
    $query .= " AND MONTH(sa.datetime) = MONTH(CURRENT_DATE) 
               AND YEAR(sa.datetime) = YEAR(CURRENT_DATE)";
}

$query .= " ORDER BY sa.datetime DESC";

$attendances = db_fetch_all($query, $params, $types);

// Calculer les statistiques
$stats = [
    'total' => 0,
    'present' => 0,
    'absent' => 0,
    'late' => 0,
    'excused' => 0,
    'justified' => 0,
    'unjustified' => 0
];

foreach ($attendances as $attendance) {
    $stats['total']++;
    $status = $attendance['status'] ?? 'present';
    if (isset($stats[$status])) {
        $stats[$status]++;
    }
    
    // Compter les justifiées/non justifiées
    if ($status === 'absent' || $status === 'late') {
        if (!empty($attendance['comment']) && trim($attendance['comment']) !== '') {
            $stats['justified']++;
        } else {
            $stats['unjustified']++;
        }
    }
}

// Début de la capture du contenu
ob_start();
?>

<div class="bg-white rounded-lg shadow-md p-6">
    <h2 class="text-2xl font-bold mb-6 text-gray-800">
        <i class="fas fa-calendar-check mr-2 text-blue-600"></i>Mes présences et absences
    </h2>

    <!-- Sélection de la période -->
    <div class="mb-6 bg-gray-50 p-4 rounded-lg">
        <form method="GET" class="flex items-center gap-4">
            <label for="period" class="font-semibold text-gray-700">Période :</label>
            <select name="period" id="period" onchange="this.form.submit()" 
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="thismonth" <?= $selected_period === 'thismonth' ? 'selected' : '' ?>>Ce mois</option>
                <option value="all" <?= $selected_period === 'all' ? 'selected' : '' ?>>Tout l'historique</option>
            </select>
        </form>
    </div>

    <!-- Statistiques -->
    <?php if ($stats['total'] > 0): ?>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                <div class="text-2xl font-bold text-blue-600"><?= $stats['total'] ?></div>
                <div class="text-sm text-gray-600">Total</div>
            </div>
            <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded">
                <div class="text-2xl font-bold text-green-600"><?= $stats['present'] ?></div>
                <div class="text-sm text-gray-600">Présents</div>
            </div>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
                <div class="text-2xl font-bold text-red-600"><?= $stats['absent'] ?></div>
                <div class="text-sm text-gray-600">Absents</div>
            </div>
            <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded">
                <div class="text-2xl font-bold text-yellow-600"><?= $stats['late'] ?></div>
                <div class="text-sm text-gray-600">Retards</div>
            </div>
        </div>

        <?php if ($stats['absent'] > 0 || $stats['late'] > 0): ?>
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="bg-purple-50 border-l-4 border-purple-500 p-4 rounded">
                    <div class="text-xl font-bold text-purple-600"><?= $stats['justified'] ?></div>
                    <div class="text-sm text-gray-600">Justifiées</div>
                </div>
                <div class="bg-orange-50 border-l-4 border-orange-500 p-4 rounded">
                    <div class="text-xl font-bold text-orange-600"><?= $stats['unjustified'] ?></div>
                    <div class="text-sm text-gray-600">Non justifiées</div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Liste des présences -->
    <div class="mt-6">
        <h3 class="text-xl font-semibold mb-4 text-gray-800">Historique des présences</h3>
        
        <?php if (!empty($attendances)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Heure</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Cours</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Enseignant</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Statut</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Commentaire</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($attendances as $attendance): ?>
                            <?php
                            $datetime = new DateTime($attendance['datetime']);
                            $status = $attendance['status'] ?? 'present';
                            
                            $badge_class = [
                                'present' => 'bg-green-100 text-green-800',
                                'absent' => 'bg-red-100 text-red-800',
                                'late' => 'bg-yellow-100 text-yellow-800',
                                'excused' => 'bg-blue-100 text-blue-800'
                            ];
                            
                            $status_text = [
                                'present' => 'Présent',
                                'absent' => 'Absent',
                                'late' => 'En retard',
                                'excused' => 'Excusé'
                            ];
                            
                            $badge_class_current = $badge_class[$status] ?? 'bg-gray-100 text-gray-800';
                            $status_text_current = $status_text[$status] ?? $status;
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                    <?= $datetime->format('d/m/Y') ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                    <?= $datetime->format('H:i') ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    <?= htmlspecialchars($attendance['course_name'] ?? 'N/A') ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    <?= htmlspecialchars($attendance['teacher_name'] ?? 'N/A') ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $badge_class_current ?>">
                                        <?= $status_text_current ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <?php if (!empty($attendance['comment']) && trim($attendance['comment']) !== ''): ?>
                                        <span class="text-blue-600" title="<?= htmlspecialchars($attendance['comment']) ?>">
                                            <i class="fas fa-check-circle mr-1"></i>
                                            <?= htmlspecialchars(mb_substr($attendance['comment'], 0, 50)) ?>
                                            <?= mb_strlen($attendance['comment']) > 50 ? '...' : '' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            Aucune présence enregistrée pour cette période.
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Récupérer le contenu capturé et l'assigner à la variable $content
$content = ob_get_clean();

// Inclure le template qui utilisera la variable $content
include('templates/layout.php');
?>
