<?php
include_once('main.php');
include_once('../../service/mysqlcon.php');

$admin_id = $_SESSION['login_id'];
$selected_date = $_GET['date'] ?? date('Y-m-d');

// Récupérer tous les enseignants avec leurs cours du jour
$sql_teachers = "SELECT DISTINCT t.id, t.name, t.phone, t.email
                 FROM teachers t
                 WHERE CAST(t.created_by AS CHAR) = CAST(? AS CHAR)
                 ORDER BY t.name";
$stmt = $link->prepare($sql_teachers);
$stmt->bind_param("s", $admin_id);
$stmt->execute();
$all_teachers = $stmt->get_result();

// Fonction pour récupérer les cours d'un enseignant pour une date donnée
function getTeacherCoursesForDate($link, $teacher_id, $date) {
    $day_of_week = date('N', strtotime($date)); // 1=Lundi, 7=Dimanche
    
    // Récupérer les cours via class_schedule (si disponible)
    $schedule_sql = "SELECT cs.id as schedule_id, cs.class_id, cs.subject_id, cs.slot_id,
                            c.id as course_id, c.name as course_name,
                            cl.name as class_name,
                            ts.start_time, ts.end_time
                     FROM class_schedule cs
                     JOIN course c ON CAST(cs.subject_id AS CHAR) = CAST(c.id AS CHAR)
                     JOIN class cl ON CAST(cs.class_id AS CHAR) = CAST(cl.id AS CHAR)
                     LEFT JOIN time_slots ts ON cs.slot_id = ts.slot_id
                     WHERE CAST(cs.teacher_id AS CHAR) = CAST(? AS CHAR)
                     AND ts.day_number = ?
                     ORDER BY ts.start_time";
    
    $stmt = $link->prepare($schedule_sql);
    $stmt->bind_param("si", $teacher_id, $day_of_week);
    $stmt->execute();
    $scheduled_courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Si pas de cours dans l'emploi du temps, récupérer tous les cours de l'enseignant
    if (empty($scheduled_courses)) {
        $courses_sql = "SELECT DISTINCT c.id as course_id, c.name as course_name,
                               cl.id as class_id, cl.name as class_name
                        FROM course c
                        JOIN class cl ON CAST(c.classid AS CHAR) = CAST(cl.id AS CHAR)
                        WHERE CAST(c.teacherid AS CHAR) = CAST(? AS CHAR)
                        ORDER BY c.name";
        $stmt = $link->prepare($courses_sql);
        $stmt->bind_param("s", $teacher_id);
        $stmt->execute();
        $courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Ajouter des horaires par défaut si pas d'emploi du temps
        foreach ($courses as &$course) {
            $course['start_time'] = '08:00:00';
            $course['end_time'] = '09:00:00';
            $course['schedule_id'] = null;
            $course['slot_id'] = null;
        }
        return $courses;
    }
    
    return $scheduled_courses;
}

// Fonction pour vérifier si une présence existe déjà
function checkAttendanceExists($link, $teacher_id, $course_id, $datetime) {
    $check_sql = "SELECT id FROM attendance 
                  WHERE CAST(attendedid AS CHAR) = CAST(? AS CHAR)
                  AND course_id = ?
                  AND DATE(datetime) = DATE(?)
                  AND TIME(datetime) = TIME(?)";
    $stmt = $link->prepare($check_sql);
    $stmt->bind_param("siss", $teacher_id, $course_id, $datetime, $datetime);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

// Générer le contenu HTML
ob_start();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Présences des Enseignants</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .course-select {
            margin-top: 10px;
        }
        .attendance-form {
            margin-bottom: 20px;
        }
        .time-badge {
            font-size: 0.85em;
            padding: 4px 8px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h3 fw-bold"><i class="fas fa-chalkboard-teacher me-2"></i>Présences des Enseignants</h2>
            <div>
                <input type="date" id="date-selector" value="<?= htmlspecialchars($selected_date) ?>" class="form-control">
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_GET['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_GET['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <?php if ($all_teachers && $all_teachers->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Enseignant</th>
                                    <th>Cours / Horaire</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($teacher = $all_teachers->fetch_assoc()): ?>
                                    <?php 
                                    $courses = getTeacherCoursesForDate($link, $teacher['id'], $selected_date);
                                    if (empty($courses)): 
                                    ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($teacher['name']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($teacher['id']) ?></small>
                                            </td>
                                            <td>
                                                <span class="text-muted">Aucun cours programmé</span>
                                            </td>
                                            <td>
                                                <form method="POST" action="attendTeacher.php" class="d-inline">
                                                    <input type="hidden" name="teacher_id" value="<?= htmlspecialchars($teacher['id']) ?>">
                                                    <input type="hidden" name="date" value="<?= htmlspecialchars($selected_date) ?>">
                                                    <input type="hidden" name="course_id" value="">
                                                    <input type="hidden" name="status" value="present">
                                                    <button type="submit" class="btn btn-success btn-sm">
                                                        <i class="fas fa-check me-1"></i>Présent
                                                    </button>
                                                </form>
                                                <form method="POST" action="attendTeacher.php" class="d-inline">
                                                    <input type="hidden" name="teacher_id" value="<?= htmlspecialchars($teacher['id']) ?>">
                                                    <input type="hidden" name="date" value="<?= htmlspecialchars($selected_date) ?>">
                                                    <input type="hidden" name="course_id" value="">
                                                    <input type="hidden" name="status" value="absent">
                                                    <button type="submit" class="btn btn-danger btn-sm">
                                                        <i class="fas fa-times me-1"></i>Absent
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($courses as $course): ?>
                                            <?php
                                            $course_datetime = $selected_date . ' ' . $course['start_time'];
                                            $already_marked = checkAttendanceExists($link, $teacher['id'], $course['course_id'], $course_datetime);
                                            ?>
                                            <tr <?= $already_marked ? 'class="table-secondary"' : '' ?>>
                                                <td>
                                                    <?php if ($course === reset($courses)): ?>
                                                        <strong><?= htmlspecialchars($teacher['name']) ?></strong><br>
                                                        <small class="text-muted"><?= htmlspecialchars($teacher['id']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($course['course_name']) ?></strong>
                                                    <?php if (!empty($course['class_name'])): ?>
                                                        <br><small class="text-muted">Classe: <?= htmlspecialchars($course['class_name']) ?></small>
                                                    <?php endif; ?>
                                                    <?php if (!empty($course['start_time'])): ?>
                                                        <br><span class="badge bg-info time-badge">
                                                            <i class="fas fa-clock me-1"></i>
                                                            <?= date('H:i', strtotime($course['start_time'])) ?> - <?= date('H:i', strtotime($course['end_time'])) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($already_marked): ?>
                                                        <br><small class="text-success"><i class="fas fa-check-circle me-1"></i>Déjà marqué</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!$already_marked): ?>
                                                        <form method="POST" action="attendTeacher.php" class="d-inline">
                                                            <input type="hidden" name="teacher_id" value="<?= htmlspecialchars($teacher['id']) ?>">
                                                            <input type="hidden" name="date" value="<?= htmlspecialchars($selected_date) ?>">
                                                            <input type="hidden" name="course_id" value="<?= htmlspecialchars($course['course_id']) ?>">
                                                            <input type="hidden" name="time_slot_id" value="<?= htmlspecialchars($course['slot_id'] ?? '') ?>">
                                                            <input type="hidden" name="datetime" value="<?= htmlspecialchars($course_datetime) ?>">
                                                            <input type="hidden" name="status" value="present">
                                                            <button type="submit" class="btn btn-success btn-sm">
                                                                <i class="fas fa-check me-1"></i>Présent
                                                            </button>
                                                        </form>
                                                        <form method="POST" action="attendTeacher.php" class="d-inline">
                                                            <input type="hidden" name="teacher_id" value="<?= htmlspecialchars($teacher['id']) ?>">
                                                            <input type="hidden" name="date" value="<?= htmlspecialchars($selected_date) ?>">
                                                            <input type="hidden" name="course_id" value="<?= htmlspecialchars($course['course_id']) ?>">
                                                            <input type="hidden" name="time_slot_id" value="<?= htmlspecialchars($course['slot_id'] ?? '') ?>">
                                                            <input type="hidden" name="datetime" value="<?= htmlspecialchars($course_datetime) ?>">
                                                            <input type="hidden" name="status" value="absent">
                                                            <button type="submit" class="btn btn-danger btn-sm">
                                                                <i class="fas fa-times me-1"></i>Absent
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="text-muted">Déjà enregistré</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>Aucun enseignant trouvé.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Rediriger quand la date change
        document.getElementById('date-selector').addEventListener('change', function() {
            window.location.href = 'teacherAttendance.php?date=' + this.value;
        });
    </script>
</body>
</html>

<?php
$content = ob_get_clean();
include('templates/layout.php');
?>
