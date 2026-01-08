<?php
include_once('main.php');
require_once '../../service/db_utils.php';

// Vérification de la session
if (!isset($_SESSION['student_id'])) {
    header("Location: ../../index.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// Récupération des informations de l'étudiant
$student_info = db_fetch_row(
    "SELECT s.*, c.id as class_id, c.name as class_name FROM students s
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

// Jours de la semaine
$days = [
    1 => 'Lundi',
    2 => 'Mardi',
    3 => 'Mercredi',
    4 => 'Jeudi',
    5 => 'Vendredi',
    6 => 'Samedi'
];

// Récupération des créneaux horaires
$time_slots = db_fetch_all(
    "SELECT slot_id as id, day_number, start_time, end_time FROM time_slots ORDER BY start_time",
    [],
    ''
);

// Récupération de l'emploi du temps de la classe
$timetable = [];

if ($class_id) {
    $timetable_data = db_fetch_all(
        "SELECT cs.*, c.name as course_name, t.name as teacher_name, ts.start_time, ts.end_time
         FROM class_schedule cs
         JOIN course c ON cs.subject_id = c.id
         JOIN teachers t ON cs.teacher_id = t.id
         JOIN time_slots ts ON cs.slot_id = ts.slot_id
         WHERE cs.class_id = ?
         ORDER BY cs.day_of_week, ts.start_time",
        [$class_id],
        's'
    );

    // Organiser les données par jour et créneau horaire
    foreach ($timetable_data as $schedule) {
        $day = $schedule['day_of_week']; // C'est une chaîne comme "Lundi", "Mardi", etc.
        $time_slot = $schedule['slot_id'];
        $timetable[$day][$time_slot] = $schedule;
    }
}

// Début de la capture du contenu
ob_start();
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800 mb-2">Mon emploi du temps</h1>
    <p class="text-gray-600">Classe : <?php echo htmlspecialchars($student_info['class_name'] ?? 'Non assignée'); ?></p>
</div>

<?php if (empty($class_id)): ?>
    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6">
        <p>Vous n'êtes pas encore assigné à une classe. Veuillez contacter l'administration.</p>
    </div>
<?php elseif (empty($timetable)): ?>
    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6">
        <p>Aucun emploi du temps n'est disponible pour votre classe. Veuillez contacter l'administration.</p>
    </div>
<?php else: ?>
    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Horaire</th>
                        <?php 
                        // Nous utilisons les jours de la semaine comme ils sont stockés dans la base de données
                        $jour_semaine = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
                        foreach ($jour_semaine as $jour): 
                        ?>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo $jour; ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($time_slots as $slot): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <span class="text-xs text-gray-500">
                                    <?php echo date('H:i', strtotime($slot['start_time'])) . ' - ' . date('H:i', strtotime($slot['end_time'])); ?>
                                </span>
                            </td>
                            <?php foreach ($jour_semaine as $jour): ?>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if (isset($timetable[$jour][$slot['id']])): ?>
                                        <?php $schedule = $timetable[$jour][$slot['id']]; ?>
                                        <div class="flex flex-col">
                                            <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($schedule['course_name']); ?></span>
                                            <span class="text-xs text-gray-500">Prof: <?php echo htmlspecialchars($schedule['teacher_name']); ?></span>
                                            <?php if (!empty($schedule['room'])): ?>
                                                <span class="text-xs text-gray-500">Salle: <?php echo htmlspecialchars($schedule['room']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php
// Récupérer le contenu capturé et l'assigner à la variable $content
$content = ob_get_clean();

// Inclure le template qui utilisera la variable $content
include('templates/layout.php');
?>
