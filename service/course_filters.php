<?php
/**
 * Fichier pour les filtres de cours
 * Ce fichier permet de déterminer quels cours un enseignant est autorisé à voir
 * et à modifier en fonction des assignations dans la base de données.
 */

/**
 * Vérifie si un enseignant est autorisé à MODIFIER un cours pour une classe donnée
 * en interrogeant directement la base de données.
 * 
 * @param string $teacher_id ID de l'enseignant
 * @param string $course_id ID du cours
 * @param string $class_id ID de la classe
 * @return bool True si l'enseignant est autorisé à modifier, False sinon
 */
function can_teacher_modify_course($teacher_id, $course_id, $class_id) {
    // Vérifier si l'enseignant est directement assigné au cours
    $direct_assignment = db_fetch_row(
        "SELECT 1 FROM course WHERE id = ? AND teacherid = ? AND classid = ?",
        [$course_id, $teacher_id, $class_id],
        'sss'
    );
    
    if ($direct_assignment) {
        return true;
    }
    
    // Sinon, vérifier si l'enseignant est assigné via student_teacher_course
    $stc_assignment = db_fetch_row(
        "SELECT 1 FROM student_teacher_course 
         WHERE teacher_id = ? AND course_id = ? AND class_id = ?",
        [$teacher_id, $course_id, $class_id],
        'sss'
    );
    
    if ($stc_assignment) {
        return true;
    }
    
    return false;
}

/**
 * Vérifie si un enseignant est autorisé à VOIR un cours (tous les cours sont visibles)
 * 
 * @param string $teacher_id ID de l'enseignant
 * @param string $course_id ID du cours
 * @param string $class_id ID de la classe
 * @return bool True si l'enseignant est autorisé à voir, False sinon
 */
function can_teacher_view_course($teacher_id, $course_id, $class_id) {
    // Tous les cours sont visibles pour tous les enseignants
    return true;
}

/**
 * Vérifie si un enseignant est autorisé à accéder aux détails d'un cours spécifique
 * Cette fonction est utilisée pour déterminer si un enseignant peut accéder à la page manageGrades.php
 * 
 * @param string $teacher_id ID de l'enseignant
 * @param string $course_id ID du cours
 * @param string|null $class_id ID de la classe (optionnel)
 * @return bool True si l'enseignant est autorisé à accéder aux détails, False sinon
 */
function can_teacher_access_course_details($teacher_id, $course_id, $class_id = null) {
    // Débogage
    error_log("Vérification des droits d'accès - Teacher ID: $teacher_id, Course ID: $course_id, Class ID: $class_id");
    
    // Vérifier si l'enseignant est directement assigné au cours
    $sql_direct = "SELECT 1 FROM course 
                  WHERE CONVERT(id USING utf8mb4) = CONVERT(? USING utf8mb4) 
                  AND CONVERT(teacherid USING utf8mb4) = CONVERT(? USING utf8mb4)";
    
    // Si une classe spécifique est fournie, l'inclure dans la requête
    $params_direct = [$course_id, $teacher_id];
    $types_direct = 'ss';
    
    if ($class_id) {
        $sql_direct .= " AND CONVERT(classid USING utf8mb4) = CONVERT(? USING utf8mb4)";
        $params_direct[] = $class_id;
        $types_direct .= 's';
    }
    
    $direct_assignment = db_fetch_row($sql_direct, $params_direct, $types_direct);
    
    if ($direct_assignment) {
        error_log("Accès autorisé - Assignation directe trouvée");
        return true;
    }
    
    // Sinon, vérifier si l'enseignant est assigné via student_teacher_course
    $sql_stc = "SELECT 1 FROM student_teacher_course 
               WHERE CONVERT(teacher_id USING utf8mb4) = CONVERT(? USING utf8mb4) 
               AND CONVERT(course_id USING utf8mb4) = CONVERT(? USING utf8mb4)";
    
    // Si une classe spécifique est fournie, l'inclure dans la requête
    $params_stc = [$teacher_id, $course_id];
    $types_stc = 'ss';
    
    if ($class_id) {
        $sql_stc .= " AND CONVERT(class_id USING utf8mb4) = CONVERT(? USING utf8mb4)";
        $params_stc[] = $class_id;
        $types_stc .= 's';
    }
    
    $sql_stc .= " LIMIT 1";
    $stc_assignment = db_fetch_row($sql_stc, $params_stc, $types_stc);
    
    if ($stc_assignment) {
        error_log("Accès autorisé - Assignation via student_teacher_course trouvée");
        return true;
    }
    
    error_log("Accès refusé - Aucune assignation trouvée pour Teacher ID: $teacher_id, Course ID: $course_id, Class ID: $class_id");
    return false;
}

/**
 * Génère la clause SQL pour filtrer les cours que l'enseignant peut MODIFIER
 * 
 * @param string $teacher_id ID de l'enseignant
 * @param string $context Contexte de la requête ('course', 'class', 'simple')
 * @return string Clause SQL à ajouter dans les requêtes
 */
function get_course_modify_filter_sql($teacher_id = null, $context = 'course') {
    // Si aucun ID d'enseignant n'est fourni, utiliser celui de la session
    if ($teacher_id === null && isset($_SESSION['login_id'])) {
        $teacher_id = $_SESSION['login_id'];
    }
    
    // Si nous n'avons toujours pas d'ID d'enseignant, retourner une condition qui sélectionne tout
    if ($teacher_id === null) {
        return "(1=1)";
    }
    
    // Adapter la condition SQL en fonction du contexte
    switch ($context) {
        case 'class':
            // Pour les requêtes sur les classes, on ne peut pas référencer c.teacherid
            return "(1=1)";
            
        case 'simple':
            // Version simplifiée qui ne référence que c.teacherid
            return "c.teacherid = '$teacher_id'";
            
        case 'course':
        default:
            // Version complète pour les requêtes qui ont accès à c et cl
            return "(
                c.teacherid = '$teacher_id'
                OR 
                EXISTS (
                    SELECT 1 FROM student_teacher_course stc 
                    WHERE stc.teacher_id = '$teacher_id' 
                    AND stc.course_id = c.id 
                    AND stc.class_id = c.classid
                )
            )";
    }
}

/**
 * Génère la clause SQL pour filtrer les cours que l'enseignant peut VOIR
 * (tous les cours sont visibles)
 * 
 * @param string $teacher_id ID de l'enseignant (non utilisé mais gardé pour compatibilité)
 * @return string Clause SQL à ajouter dans les requêtes
 */
function get_course_view_filter_sql($teacher_id = null) {
    // Tous les cours sont visibles, donc on retourne une condition toujours vraie
    return "(1=1)";
}

/**
 * Fonction de compatibilité pour les appels existants
 * Utilise par défaut le filtre de modification (plus restrictif)
 * 
 * @param string $teacher_id ID de l'enseignant
 * @return string Clause SQL à ajouter dans les requêtes
 */
function get_course_filter_sql($teacher_id = null) {
    // Par défaut, on utilise le filtre de modification (plus restrictif)
    return get_course_modify_filter_sql($teacher_id);
}
?>
