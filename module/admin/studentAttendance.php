<?php
include_once('main.php');
include_once('../../service/mysqlcon.php');

$admin_id = $_SESSION['login_id'];
$selected_date = $_GET['date'] ?? date('Y-m-d');
$selected_class = $_GET['class_id'] ?? '';
$selected_course = $_GET['course_id'] ?? '';

// Récupérer les classes de l'admin
$classes_sql = "SELECT DISTINCT c.id, c.name 
                FROM class c
                JOIN students s ON CAST(c.id AS CHAR) = CAST(s.classid AS CHAR)
                WHERE CAST(s.created_by AS CHAR) = CAST(? AS CHAR)
                ORDER BY c.name";
$stmt = $link->prepare($classes_sql);
$stmt->bind_param("s", $admin_id);
$stmt->execute();
$classes = $stmt->get_result();

// Récupérer les cours si une classe est sélectionnée
$courses = [];
if ($selected_class) {
    $courses_sql = "SELECT DISTINCT c.id, c.name 
                    FROM course c
                    WHERE CAST(c.classid AS CHAR) = CAST(? AS CHAR)
                    AND CAST(c.created_by AS CHAR) = CAST(? AS CHAR)
                    ORDER BY c.name";
    $stmt = $link->prepare($courses_sql);
    $stmt->bind_param("ss", $selected_class, $admin_id);
    $stmt->execute();
    $courses = $stmt->get_result();
}

// Récupérer les élèves si classe et cours sont sélectionnés
$students = [];
if ($selected_class && $selected_course) {
    $students_sql = "SELECT s.id, s.name, s.phone, s.email, c.name as class_name
                     FROM students s
                     JOIN class c ON CAST(s.classid AS CHAR) = CAST(c.id AS CHAR)
                     WHERE CAST(s.classid AS CHAR) = CAST(? AS CHAR)
                     AND CAST(s.created_by AS CHAR) = CAST(? AS CHAR)
                     ORDER BY s.name";
    $stmt = $link->prepare($students_sql);
    $stmt->bind_param("ss", $selected_class, $admin_id);
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
    <title>Présences des Élèves</title>
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
            <h2 class="h3 fw-bold"><i class="fas fa-user-graduate me-2"></i>Présences des Élèves</h2>
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
                    <h5 class="card-title mb-3">Marquer la présence pour le <?= date('d/m/Y', strtotime($selected_date)) ?></h5>
                    <form method="POST" action="attendStudent.php">
                        <input type="hidden" name="class_id" value="<?= htmlspecialchars($selected_class) ?>">
                        <input type="hidden" name="course_id" value="<?= htmlspecialchars($selected_course) ?>">
                        <input type="hidden" name="date" value="<?= htmlspecialchars($selected_date) ?>">
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Élève</th>
                                        <th>Statut</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $default_time = '08:00:00'; // Heure par défaut
                                    $datetime = $selected_date . ' ' . $default_time;
                                    while ($student = $students->fetch_assoc()): 
                                        $already_marked = checkStudentAttendanceExists($link, $student['id'], $selected_course, $datetime);
                                    ?>
                                        <tr <?= $already_marked ? 'class="table-secondary"' : '' ?>>
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
                                                    <select name="status[<?= htmlspecialchars($student['id']) ?>]" class="form-select form-select-sm">
                                                        <option value="present">Présent</option>
                                                        <option value="absent">Absent</option>
                                                        <option value="late">En retard</option>
                                                        <option value="excused">Excusé</option>
                                                    </select>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!$already_marked): ?>
                                                    <input type="checkbox" name="students[]" value="<?= htmlspecialchars($student['id']) ?>" checked>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Enregistrer les présences
                            </button>
                            <a href="studentAttendance.php?date=<?= htmlspecialchars($selected_date) ?>&class_id=<?= htmlspecialchars($selected_class) ?>&course_id=<?= htmlspecialchars($selected_course) ?>" class="btn btn-secondary">
                                <i class="fas fa-redo me-2"></i>Actualiser
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        <?php elseif ($selected_class && $selected_course): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Aucun élève trouvé pour cette classe.
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

