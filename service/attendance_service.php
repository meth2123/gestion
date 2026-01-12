<?php
include_once('db_utils.php');

// Vérification de la méthode de requête
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Vérification de la session
session_start();
if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Récupération des paramètres
$action = $_POST['action'] ?? '';
$student_id = $_POST['student_id'] ?? '';
$period = $_POST['period'] ?? 'thismonth';

// Validation des paramètres
if (empty($action) || empty($student_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit();
}

// Traitement des différentes actions
switch ($action) {
    case 'get_presence':
        // Utiliser la table student_attendance pour les élèves
        $query = "SELECT sa.datetime, c.name as course_name, sa.status
                 FROM student_attendance sa
                 INNER JOIN course c ON sa.course_id = c.id
                 WHERE CAST(sa.student_id AS CHAR) = CAST(? AS CHAR)";
        
        $params = [$student_id];
        $types = 's';
        
        if ($period === 'thismonth') {
            $query .= " AND MONTH(sa.datetime) = MONTH(CURRENT_DATE) AND YEAR(sa.datetime) = YEAR(CURRENT_DATE)";
        }
        
        $query .= " ORDER BY sa.datetime DESC";
        break;

    case 'get_absence':
        // Pour les absences, utiliser student_attendance avec status='absent'
        $query = "SELECT sa.datetime, c.name as course_name, sa.status, sa.comment
                 FROM student_attendance sa
                 INNER JOIN course c ON sa.course_id = c.id
                 WHERE CAST(sa.student_id AS CHAR) = CAST(? AS CHAR)
                 AND sa.status IN ('absent', 'late')";
        
        $params = [$student_id];
        $types = 's';
        
        if ($period === 'thismonth') {
            $query .= " AND MONTH(sa.datetime) = MONTH(CURRENT_DATE) AND YEAR(sa.datetime) = YEAR(CURRENT_DATE)";
        }
        
        $query .= " ORDER BY sa.datetime DESC";
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
        exit();
}

// Exécution de la requête
try {
    $results = db_fetch_all($query, $params, $types);
    
    // Formatage des dates
    if ($results) {
        foreach ($results as &$result) {
            $date = new DateTime($result['datetime'] ?? $result['date'] ?? 'now');
            $result['date_formatted'] = $date->format('d/m/Y');
            $result['time_formatted'] = $date->format('H:i');
            if (!isset($result['status'])) {
                $result['status'] = $action === 'get_presence' ? 'present' : 'absent';
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $results ?: []
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des données : ' . $e->getMessage()
    ]);
} 