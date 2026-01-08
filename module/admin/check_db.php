<?php
include_once('main.php');
include_once('../../service/mysqlcon.php');

// Initialiser le contenu pour le template
ob_start();

$admin_id = $_SESSION['login_id'];
$success_message = '';
$error_message = '';

// Vérifier si les tables existent
$tables_to_check = ['class_schedule', 'time_slots', 'class', 'course', 'teachers'];
$tables_status = [];

foreach ($tables_to_check as $table) {
    $query = "SHOW TABLES LIKE '$table'";
    $result = $link->query($query);
    $tables_status[$table] = [
        'exists' => $result && $result->num_rows > 0,
        'count' => 0
    ];
    
    if ($tables_status[$table]['exists']) {
        $count_query = "SELECT COUNT(*) as count FROM $table";
        $count_result = $link->query($count_query);
        if ($count_result && $count_row = $count_result->fetch_assoc()) {
            $tables_status[$table]['count'] = $count_row['count'];
        }
    }
}

// Vérifier si la table class_schedule a des enregistrements pour cet administrateur
if ($tables_status['class_schedule']['exists']) {
    $stmt = $link->prepare("
        SELECT COUNT(*) as count FROM class_schedule 
        WHERE CONVERT(created_by USING utf8mb4) = CONVERT(? USING utf8mb4)
    ");
    $stmt->bind_param("s", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin_count = $result->fetch_assoc()['count'];
    $tables_status['class_schedule']['admin_count'] = $admin_count;
}

// Vérifier la structure de la table class_schedule
$class_schedule_columns = [];
if ($tables_status['class_schedule']['exists']) {
    $columns_query = "SHOW COLUMNS FROM class_schedule";
    $columns_result = $link->query($columns_query);
    while ($column = $columns_result->fetch_assoc()) {
        $class_schedule_columns[] = $column;
    }
}

// Vérifier les données de la table class_schedule
$class_schedule_data = [];
if ($tables_status['class_schedule']['exists'] && $tables_status['class_schedule']['count'] > 0) {
    $data_query = "SELECT * FROM class_schedule LIMIT 5";
    $data_result = $link->query($data_query);
    while ($row = $data_result->fetch_assoc()) {
        $class_schedule_data[] = $row;
    }
}

// Vérifier la structure de la table time_slots
$time_slots_columns = [];
if ($tables_status['time_slots']['exists']) {
    $columns_query = "SHOW COLUMNS FROM time_slots";
    $columns_result = $link->query($columns_query);
    while ($column = $columns_result->fetch_assoc()) {
        $time_slots_columns[] = $column;
    }
}

// Vérifier les données de la table time_slots
$time_slots_data = [];
if ($tables_status['time_slots']['exists'] && $tables_status['time_slots']['count'] > 0) {
    $data_query = "SELECT * FROM time_slots LIMIT 5";
    $data_result = $link->query($data_query);
    while ($row = $data_result->fetch_assoc()) {
        $time_slots_data[] = $row;
    }
}

// Vérifier la requête qui pose problème
$debug_query = "
    SELECT cs.id, cs.class_id, cs.subject_id, cs.teacher_id, cs.slot_id, 
           cs.day_of_week, cs.room, cs.semester, cs.academic_year, 
           c.name as class_name, s.name as subject_name, t.name as teacher_name,
           ts.start_time, ts.end_time, CONCAT(ts.start_time, ' - ', ts.end_time) as time_slot
    FROM class_schedule cs
    JOIN class c ON CONVERT(cs.class_id USING utf8mb4) = CONVERT(c.id USING utf8mb4)
    JOIN course s ON CONVERT(cs.subject_id USING utf8mb4) = CONVERT(s.id USING utf8mb4)
    JOIN teachers t ON CONVERT(cs.teacher_id USING utf8mb4) = CONVERT(t.id USING utf8mb4)
    JOIN time_slots ts ON cs.slot_id = ts.slot_id
    WHERE CONVERT(cs.created_by USING utf8mb4) = CONVERT('$admin_id' USING utf8mb4)
    LIMIT 5
";
$debug_result = $link->query($debug_query);
$debug_error = $link->error;
$debug_data = [];
if ($debug_result) {
    while ($row = $debug_result->fetch_assoc()) {
        $debug_data[] = $row;
    }
}
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h2 class="card-title mb-0">Vérification de la base de données</h2>
                </div>
                <div class="card-body">
                    <h3>Statut des tables</h3>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Table</th>
                                    <th>Existe</th>
                                    <th>Nombre d'enregistrements</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tables_status as $table => $status): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($table); ?></td>
                                    <td><?php echo $status['exists'] ? '<span class="text-success">Oui</span>' : '<span class="text-danger">Non</span>'; ?></td>
                                    <td><?php echo $status['exists'] ? $status['count'] : 'N/A'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (isset($tables_status['class_schedule']['admin_count'])): ?>
                    <div class="alert alert-info">
                        <strong>Enregistrements pour cet administrateur (ID: <?php echo htmlspecialchars($admin_id); ?>):</strong> 
                        <?php echo $tables_status['class_schedule']['admin_count']; ?>
                    </div>
                    <?php endif; ?>
                    
                    <h3>Structure de la table class_schedule</h3>
                    <?php if (!empty($class_schedule_columns)): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Colonne</th>
                                    <th>Type</th>
                                    <th>Null</th>
                                    <th>Clé</th>
                                    <th>Défaut</th>
                                    <th>Extra</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($class_schedule_columns as $column): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($column['Field']); ?></td>
                                    <td><?php echo htmlspecialchars($column['Type']); ?></td>
                                    <td><?php echo htmlspecialchars($column['Null']); ?></td>
                                    <td><?php echo htmlspecialchars($column['Key']); ?></td>
                                    <td><?php echo htmlspecialchars($column['Default'] ?? 'NULL'); ?></td>
                                    <td><?php echo htmlspecialchars($column['Extra']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning">La table class_schedule n'existe pas ou n'a pas de colonnes.</div>
                    <?php endif; ?>
                    
                    <h3>Données de la table class_schedule (5 premiers enregistrements)</h3>
                    <?php if (!empty($class_schedule_data)): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <?php foreach (array_keys($class_schedule_data[0]) as $key): ?>
                                    <th><?php echo htmlspecialchars($key); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($class_schedule_data as $row): ?>
                                <tr>
                                    <?php foreach ($row as $value): ?>
                                    <td><?php echo htmlspecialchars($value ?? 'NULL'); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning">La table class_schedule n'existe pas ou ne contient pas de données.</div>
                    <?php endif; ?>
                    
                    <h3>Structure de la table time_slots</h3>
                    <?php if (!empty($time_slots_columns)): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Colonne</th>
                                    <th>Type</th>
                                    <th>Null</th>
                                    <th>Clé</th>
                                    <th>Défaut</th>
                                    <th>Extra</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($time_slots_columns as $column): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($column['Field']); ?></td>
                                    <td><?php echo htmlspecialchars($column['Type']); ?></td>
                                    <td><?php echo htmlspecialchars($column['Null']); ?></td>
                                    <td><?php echo htmlspecialchars($column['Key']); ?></td>
                                    <td><?php echo htmlspecialchars($column['Default'] ?? 'NULL'); ?></td>
                                    <td><?php echo htmlspecialchars($column['Extra']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning">La table time_slots n'existe pas ou n'a pas de colonnes.</div>
                    <?php endif; ?>
                    
                    <h3>Données de la table time_slots (5 premiers enregistrements)</h3>
                    <?php if (!empty($time_slots_data)): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <?php foreach (array_keys($time_slots_data[0]) as $key): ?>
                                    <th><?php echo htmlspecialchars($key); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($time_slots_data as $row): ?>
                                <tr>
                                    <?php foreach ($row as $value): ?>
                                    <td><?php echo htmlspecialchars($value ?? 'NULL'); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning">La table time_slots n'existe pas ou ne contient pas de données.</div>
                    <?php endif; ?>
                    
                    <h3>Résultat de la requête de débogage</h3>
                    <?php if ($debug_error): ?>
                    <div class="alert alert-danger">
                        <strong>Erreur:</strong> <?php echo htmlspecialchars($debug_error); ?>
                    </div>
                    <?php elseif (!empty($debug_data)): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <?php foreach (array_keys($debug_data[0]) as $key): ?>
                                    <th><?php echo htmlspecialchars($key); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($debug_data as $row): ?>
                                <tr>
                                    <?php foreach ($row as $value): ?>
                                    <td><?php echo htmlspecialchars($value ?? 'NULL'); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning">La requête n'a retourné aucun résultat.</div>
                    <?php endif; ?>
                    
                    <div class="d-grid gap-2 mt-4">
                        <a href="new_timetable.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left me-2"></i>Retour à l'emploi du temps
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include('templates/layout.php');
?>
