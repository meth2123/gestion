<?php  
include_once('main.php');
include_once('../../service/db_utils.php');

// Récupération des absences du mois en cours (utiliser status='absent' dans attendance)
$absences = db_fetch_all(
    "SELECT DISTINCT DATE_FORMAT(datetime, '%d/%m/%Y') as formatted_date 
     FROM attendance 
     WHERE CAST(attendedid AS CHAR) = CAST(? AS CHAR)
     AND person_type = 'staff'
     AND status IN ('absent', 'late')
     AND MONTH(datetime) = MONTH(CURRENT_DATE) 
     AND YEAR(datetime) = YEAR(CURRENT_DATE)
     ORDER BY datetime DESC",
    [$check],
    's'
);

if (empty($absences)) {
    echo '<div class="p-4 text-center text-gray-500">Aucune absence enregistrée ce mois-ci</div>';
} else {
    echo '<div class="overflow-hidden rounded-lg shadow">';
    echo '<table class="min-w-full divide-y divide-gray-200">';
    echo '<thead class="bg-gray-50">';
    echo '<tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date d\'absence</th></tr>';
    echo '</thead>';
    echo '<tbody class="bg-white divide-y divide-gray-200">';
    
    foreach ($absences as $absence) {
        echo '<tr class="hover:bg-gray-50">';
        echo '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">' . htmlspecialchars($absence['formatted_date']) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}
?>
