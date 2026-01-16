<?php

require_once __DIR__ . '/OneSignalService.php';
require_once __DIR__ . '/mysqlcon.php';

/**
 * Service de notification push intégré
 * Gère les notifications pour les emplois du temps et notifications admin
 */
class PushNotificationService
{
    private $oneSignal;
    private $link;
    
    public function __construct($link)
    {
        $this->oneSignal = new OneSignalService();
        $this->link = $link;
    }
    
    /**
     * Envoyer une notification lors de la création d'un emploi du temps
     * 
     * @param int $classId ID de la classe
     * @param int $teacherId ID de l'enseignant
     * @param string $adminName Nom de l'admin
     * @param array $timetableDetails Détails de l'emploi du temps
     * @return array
     */
    public function notifyTimetableCreation($classId, $teacherId, $adminName, $timetableDetails)
    {
        // Récupérer les informations de la classe
        $classQuery = "SELECT name FROM class WHERE id = ?";
        $stmt = $this->link->prepare($classQuery);
        $stmt->bind_param("i", $classId);
        $stmt->execute();
        $classResult = $stmt->get_result();
        $className = $classResult->fetch_assoc()['name'] ?? 'Classe inconnue';
        
        // Récupérer les informations de l'enseignant
        $teacherQuery = "SELECT name, onesignal_player_id FROM teachers WHERE id = ?";
        $stmt = $this->link->prepare($teacherQuery);
        $stmt->bind_param("i", $teacherId);
        $stmt->execute();
        $teacherResult = $stmt->get_result();
        $teacher = $teacherResult->fetch_assoc();
        
        // Récupérer tous les étudiants de la classe
        $studentsQuery = "SELECT onesignal_player_id FROM students WHERE class_id = ? AND onesignal_player_id IS NOT NULL";
        $stmt = $this->link->prepare($studentsQuery);
        $stmt->bind_param("i", $classId);
        $stmt->execute();
        $studentsResult = $stmt->get_result();
        
        // Préparer les destinataires
        $recipients = [];
        
        // Ajouter l'enseignant s'il a un player ID
        if (!empty($teacher['onesignal_player_id'])) {
            $recipients[] = $teacher['onesignal_player_id'];
        }
        
        // Ajouter les étudiants
        while ($student = $studentsResult->fetch_assoc()) {
            $recipients[] = $student['onesignal_player_id'];
        }
        
        if (empty($recipients)) {
            return ['success' => false, 'message' => 'Aucun destinataire trouvé'];
        }
        
        $title = "Nouvel emploi du temps";
        $message = "L'administrateur {$adminName} a créé un nouvel emploi du temps pour la classe {$className}";
        
        $data = [
            'type' => 'timetable_created',
            'class_id' => $classId,
            'teacher_id' => $teacherId,
            'admin_name' => $adminName,
            'class_name' => $className,
            'teacher_name' => $teacher['name'] ?? 'Enseignant inconnu'
        ];
        
        return $this->oneSignal->sendToUsers($recipients, $title, $message, $data);
    }
    
    /**
     * Envoyer une notification ciblée créée par l'admin
     * 
     * @param string $adminName Nom de l'admin
     * @param string $adminId ID de l'admin
     * @param string $title Titre de la notification
     * @param string $message Message de la notification
     * @param array $targets Cibles sélectionnées (classes, enseignants, etc.)
     * @return array
     */
    public function sendAdminNotification($adminName, $adminId, $title, $message, $targets)
    {
        $recipients = [];
        $targetDetails = [];
        
        // Traiter les différentes cibles
        if (!empty($targets['classes'])) {
            foreach ($targets['classes'] as $classId) {
                // Récupérer les étudiants de la classe
                $studentsQuery = "SELECT s.name, s.onesignal_player_id, c.name as class_name 
                                 FROM students s 
                                 JOIN class c ON s.class_id = c.id 
                                 WHERE s.class_id = ? AND s.onesignal_player_id IS NOT NULL 
                                 AND s.created_by = ?";
                $stmt = $this->link->prepare($studentsQuery);
                $stmt->bind_param("is", $classId, $adminId);
                $stmt->execute();
                $studentsResult = $stmt->get_result();
                
                while ($student = $studentsResult->fetch_assoc()) {
                    $recipients[] = $student['onesignal_player_id'];
                    $targetDetails[] = "Étudiant: {$student['name']} ({$student['class_name']})";
                }
            }
        }
        
        if (!empty($targets['teachers'])) {
            foreach ($targets['teachers'] as $teacherId) {
                $teacherQuery = "SELECT name, onesignal_player_id FROM teachers 
                               WHERE id = ? AND onesignal_player_id IS NOT NULL AND created_by = ?";
                $stmt = $this->link->prepare($teacherQuery);
                $stmt->bind_param("is", $teacherId, $adminId);
                $stmt->execute();
                $teacherResult = $stmt->get_result();
                
                while ($teacher = $teacherResult->fetch_assoc()) {
                    $recipients[] = $teacher['onesignal_player_id'];
                    $targetDetails[] = "Enseignant: {$teacher['name']}";
                }
            }
        }
        
        if (!empty($targets['parents'])) {
            foreach ($targets['parents'] as $parentId) {
                $parentQuery = "SELECT name, onesignal_player_id FROM parents 
                              WHERE id = ? AND onesignal_player_id IS NOT NULL AND created_by = ?";
                $stmt = $this->link->prepare($parentQuery);
                $stmt->bind_param("is", $parentId, $adminId);
                $stmt->execute();
                $parentResult = $stmt->get_result();
                
                while ($parent = $parentResult->fetch_assoc()) {
                    $recipients[] = $parent['onesignal_player_id'];
                    $targetDetails[] = "Parent: {$parent['name']}";
                }
            }
        }
        
        // Éliminer les doublons
        $recipients = array_unique($recipients);
        
        if (empty($recipients)) {
            return ['success' => false, 'message' => 'Aucun destinataire trouvé pour les cibles sélectionnées'];
        }
        
        $notificationTitle = "Notification de {$adminName}";
        $notificationMessage = "{$title}: {$message}";
        
        $data = [
            'type' => 'admin_notification',
            'admin_id' => $adminId,
            'admin_name' => $adminName,
            'title' => $title,
            'message' => $message,
            'targets' => $targetDetails
        ];
        
        return $this->oneSignal->sendToUsers($recipients, $notificationTitle, $notificationMessage, $data);
    }
    
    /**
     * Mettre à jour le player ID OneSignal d'un utilisateur
     * 
     * @param string $userId ID de l'utilisateur
     * @param string $userType Type d'utilisateur (student, teacher, parent, admin)
     * @param string $playerId OneSignal player ID
     * @return bool
     */
    public function updatePlayerId($userId, $userType, $playerId)
    {
        $table = $this->getUserTable($userType);
        $idField = $this->getUserIdField($userType);
        
        if (!$table || !$idField) {
            return false;
        }
        
        $query = "UPDATE {$table} SET onesignal_player_id = ? WHERE {$idField} = ?";
        $stmt = $this->link->prepare($query);
        $stmt->bind_param("ss", $playerId, $userId);
        
        return $stmt->execute();
    }
    
    /**
     * Obtenir la table utilisateur selon le type
     */
    private function getUserTable($userType)
    {
        $tables = [
            'student' => 'students',
            'teacher' => 'teachers',
            'parent' => 'parents',
            'admin' => 'admin'
        ];
        
        return $tables[$userType] ?? null;
    }
    
    /**
     * Obtenir le champ ID selon le type d'utilisateur
     */
    private function getUserIdField($userType)
    {
        $fields = [
            'student' => 'id',
            'teacher' => 'id',
            'parent' => 'id',
            'admin' => 'id'
        ];
        
        return $fields[$userType] ?? null;
    }
    
    /**
     * Ajouter les colonnes OneSignal si elles n'existent pas
     */
    public function setupDatabase()
    {
        $tables = ['students', 'teachers', 'parents', 'admin'];
        
        foreach ($tables as $table) {
            $checkColumn = "SHOW COLUMNS FROM {$table} LIKE 'onesignal_player_id'";
            $result = $this->link->query($checkColumn);
            
            if ($result->num_rows === 0) {
                $addColumn = "ALTER TABLE {$table} ADD COLUMN onesignal_player_id VARCHAR(255) NULL";
                $this->link->query($addColumn);
            }
        }
    }
}
