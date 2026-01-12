<?php
include_once('main.php');
include_once('../../service/mysqlcon.php');
include_once('../../service/db_utils.php');

// Récupérer teacher_id depuis la session
$teacher_id = null;
if (isset($_SESSION['teacher_id']) && !empty($_SESSION['teacher_id'])) {
    $teacher_id = $_SESSION['teacher_id'];
} elseif (isset($_SESSION['login_id']) && !empty($_SESSION['login_id'])) {
    // Si login_id existe, vérifier si c'est un teacher
    $check_teacher = "SELECT id FROM teachers WHERE CAST(id AS CHAR) = CAST(? AS CHAR) LIMIT 1";
    $check_stmt = $link->prepare($check_teacher);
    if ($check_stmt) {
        $check_stmt->bind_param("s", $_SESSION['login_id']);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $teacher_id = $_SESSION['login_id'];
            $_SESSION['teacher_id'] = $teacher_id; // Mettre à jour la session
        }
        $check_stmt->close();
    }
}

if (!$teacher_id) {
    header("Location: ../../index.php?error=" . urlencode("Session expirée. Veuillez vous reconnecter."));
    exit();
}

$selected_date = $_GET['date'] ?? date('Y-m-d');
$selected_class = $_GET['class_id'] ?? '';
$selected_course = $_GET['course_id'] ?? '';

// Récupérer les classes assignées à cet enseignant
$classes_sql = "SELECT DISTINCT cl.id, cl.name
                FROM student_teacher_course stc
                JOIN class cl ON CAST(stc.class_id AS CHAR) = CAST(cl.id AS CHAR)
                WHERE CAST(stc.teacher_id AS CHAR) = CAST(? AS CHAR)
                UNION
                SELECT DISTINCT c.id, c.name
                FROM course co
                JOIN class c ON CAST(co.classid AS CHAR) = CAST(c.id AS CHAR)
                WHERE CAST(co.teacherid AS CHAR) = CAST(? AS CHAR)
                ORDER BY name";
$stmt = $link->prepare($classes_sql);
$stmt->bind_param("ss", $teacher_id, $teacher_id);
$stmt->execute();
$classes = $stmt->get_result();

// Récupérer les cours si une classe est sélectionnée
$courses = [];
if ($selected_class) {
    $courses_sql = "SELECT DISTINCT c.id, c.name
                     FROM student_teacher_course stc
                     JOIN course c ON CAST(stc.course_id AS CHAR) = CAST(c.id AS CHAR)
                     WHERE CAST(stc.teacher_id AS CHAR) = CAST(? AS CHAR)
                     AND CAST(stc.class_id AS CHAR) = CAST(? AS CHAR)
                     UNION
                     SELECT DISTINCT co.id, co.name
                     FROM course co
                     WHERE CAST(co.teacherid AS CHAR) = CAST(? AS CHAR)
                     AND CAST(co.classid AS CHAR) = CAST(? AS CHAR)
                     ORDER BY name";
    $stmt = $link->prepare($courses_sql);
    $stmt->bind_param("ssss", $teacher_id, $selected_class, $teacher_id, $selected_class);
    $stmt->execute();
    $courses = $stmt->get_result();
}

// Récupérer les élèves si classe et cours sont sélectionnés
$students = [];
if ($selected_class && $selected_course) {
    // Récupérer les élèves via student_teacher_course
    $students_sql = "SELECT DISTINCT s.id, s.name, s.phone, s.email
                     FROM students s
                     JOIN student_teacher_course stc ON CAST(s.id AS CHAR) = CAST(stc.student_id AS CHAR)
                     WHERE CAST(stc.teacher_id AS CHAR) = CAST(? AS CHAR)
                     AND CAST(stc.course_id AS CHAR) = CAST(? AS CHAR)
                     AND CAST(stc.class_id AS CHAR) = CAST(? AS CHAR)
                     AND CAST(s.classid AS CHAR) = CAST(? AS CHAR)
                     ORDER BY s.name";
    $stmt = $link->prepare($students_sql);
    $stmt->bind_param("ssss", $teacher_id, $selected_course, $selected_class, $selected_class);
    $stmt->execute();
    $students = $stmt->get_result();
}

// Fonction pour vérifier si une présence existe déjà
function checkStudentAttendanceExists($link, $student_id, $course_id, $datetime) {
    $check_sql = "SELECT id FROM student_attendance 
                  WHERE CAST(student_id AS CHAR) = CAST(? AS CHAR)
                  AND course_id = ?
                  AND DATE(datetime) = DATE(?)
                  AND TIME(datetime) = TIME(?)";
    $stmt = $link->prepare($check_sql);
    $stmt->bind_param("siss", $student_id, $course_id, $datetime, $datetime);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

ob_start();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marquer la Présence des Élèves</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .attendance-form {
            margin-bottom: 20px;
        }
        .status-badge {
            font-size: 0.85em;
            padding: 4px 8px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h3 fw-bold"><i class="fas fa-clipboard-check me-2"></i>Marquer la Présence des Élèves</h2>
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

        <!-- Filtres -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <input type="hidden" name="date" value="<?= htmlspecialchars($selected_date) ?>">
                    <div class="col-md-4">
                        <label for="class_id" class="form-label">Classe</label>
                        <select name="class_id" id="class_id" class="form-select" onchange="this.form.submit()">
                            <option value="">Sélectionner une classe</option>
                            <?php if ($classes && $classes->num_rows > 0): ?>
                                <?php while ($class = $classes->fetch_assoc()): ?>
                                    <option value="<?= htmlspecialchars($class['id']) ?>" 
                                            <?= $selected_class == $class['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($class['name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <?php if ($selected_class): ?>
                        <div class="col-md-4">
                            <label for="course_id" class="form-label">Cours</label>
                            <select name="course_id" id="course_id" class="form-select" onchange="this.form.submit()">
                                <option value="">Sélectionner un cours</option>
                                <?php if ($courses && $courses->num_rows > 0): ?>
                                    <?php while ($course = $courses->fetch_assoc()): ?>
                                        <option value="<?= htmlspecialchars($course['id']) ?>" 
                                                <?= $selected_course == $course['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($course['name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Liste des élèves -->
        <?php if ($selected_class && $selected_course && $students && $students->num_rows > 0): ?>
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-3">
                        <i class="fas fa-calendar-day me-2"></i>
                        Présence pour le <?= date('d/m/Y', strtotime($selected_date)) ?>
                    </h5>
                    <form method="POST" action="saveStudentAttendance.php">
                        <input type="hidden" name="class_id" value="<?= htmlspecialchars($selected_class) ?>">
                        <input type="hidden" name="course_id" value="<?= htmlspecialchars($selected_course) ?>">
                        <input type="hidden" name="date" value="<?= htmlspecialchars($selected_date) ?>">
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th width="5%">#</th>
                                        <th>Élève</th>
                                        <th>Statut</th>
                                        <th>Commentaire</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $default_time = date('H:i:s'); // Heure actuelle
                                    $datetime = $selected_date . ' ' . $default_time;
                                    $counter = 1;
                                    while ($student = $students->fetch_assoc()): 
                                        $already_marked = checkStudentAttendanceExists($link, $student['id'], $selected_course, $datetime);
                                    ?>
                                        <tr <?= $already_marked ? 'class="table-secondary"' : '' ?>>
                                            <td><?= $counter++ ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($student['name']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($student['id']) ?></small>
                                            </td>
                                            <td>
                                                <?php if ($already_marked): ?>
                                                    <span class="badge bg-success status-badge">
                                                        <i class="fas fa-check-circle me-1"></i>Déjà marqué
                                                    </span>
                                                <?php else: ?>
                                                    <select name="status[<?= htmlspecialchars($student['id']) ?>]" class="form-select form-select-sm" required>
                                                        <option value="present">✅ Présent</option>
                                                        <option value="absent">❌ Absent</option>
                                                        <option value="late">⏰ En retard</option>
                                                        <option value="excused">📝 Excusé</option>
                                                    </select>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!$already_marked): ?>
                                                    <input type="text" name="comment[<?= htmlspecialchars($student['id']) ?>]" 
                                                           class="form-control form-control-sm" 
                                                           placeholder="Commentaire optionnel">
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Enregistrer les présences
                            </button>
                            <a href="markStudentAttendance.php?date=<?= htmlspecialchars($selected_date) ?>&class_id=<?= htmlspecialchars($selected_class) ?>&course_id=<?= htmlspecialchars($selected_course) ?>" class="btn btn-secondary">
                                <i class="fas fa-redo me-2"></i>Actualiser
                            </a>
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-home me-2"></i>Retour
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        <?php elseif ($selected_class && $selected_course): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Aucun élève trouvé pour ce cours.
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Veuillez sélectionner une classe et un cours pour commencer.
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Rediriger quand la date change
        document.getElementById('date-selector').addEventListener('change', function() {
            const url = new URL(window.location.href);
            url.searchParams.set('date', this.value);
            window.location.href = url.toString();
        });
    </script>
</body>
</html>

<?php
$content = ob_get_clean();
include('templates/layout.php');
?>

