<?php
include_once('main.php');
include_once('../../service/mysqlcon.php');

// Initialiser le contenu pour le template
ob_start();

// Vérifier la structure de la table class_schedule
$columns_query = "DESCRIBE class_schedule";
$columns_result = $link->query($columns_query);
$columns = [];
while ($column = $columns_result->fetch_assoc()) {
    $columns[] = $column;
}

// Tester l'insertion directe
$test_insert = false;
$insert_result = null;
$insert_error = null;

if (isset($_POST['test_insert'])) {
    $test_insert = true;
    
    // Valeurs de test
    $class_id = 'CLS-C2-A-798';
    $subject_id = '9';
    $teacher_id = 'c'; // ID d'un enseignant existant
    $slot_id = '1';
    $day_of_week = 'Lundi';
    $room = 'Test';
    $semester = '1';
    $academic_year = '2025-2026';
    $created_by = 'ad-123-1'; // ID de l'administrateur
    
    // Tester l'insertion
    try {
        $stmt = $link->prepare("
            INSERT INTO class_schedule 
            (class_id, subject_id, teacher_id, slot_id, day_of_week, room, semester, academic_year, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param("sssisssss", 
            $class_id, $subject_id, $teacher_id, $slot_id, $day_of_week, 
            $room, $semester, $academic_year, $created_by
        );
        
        $success = $stmt->execute();
        
        if ($success) {
            $insert_id = $stmt->insert_id;
            $insert_result = "Insertion réussie avec l'ID " . $insert_id;
            
            // Vérifier l'enregistrement inséré
            $check_stmt = $link->prepare("
                SELECT * FROM class_schedule WHERE id = ?
            ");
            $check_stmt->bind_param("i", $insert_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $inserted_record = $check_result->fetch_assoc();
        } else {
            $insert_error = "Erreur lors de l'insertion : " . $stmt->error;
        }
    } catch (Exception $e) {
        $insert_error = "Exception : " . $e->getMessage();
    }
}

// Récupérer quelques exemples de la table
$data_query = "SELECT * FROM class_schedule ORDER BY id DESC LIMIT 5";
$data_result = $link->query($data_query);
$data = [];
while ($row = $data_result->fetch_assoc()) {
    $data[] = $row;
}
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h2 class="card-title mb-0">Vérification de la table class_schedule</h2>
                </div>
                <div class="card-body">
                    <h3>Structure de la table</h3>
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
                                <?php foreach ($columns as $column): ?>
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
                    
                    <h3 class="mt-4">Données récentes</h3>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <?php if (!empty($data)): foreach (array_keys($data[0]) as $key): ?>
                                    <th><?php echo htmlspecialchars($key); ?></th>
                                    <?php endforeach; endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data as $row): ?>
                                <tr>
                                    <?php foreach ($row as $value): ?>
                                    <td><?php echo htmlspecialchars($value ?? 'NULL'); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <h3 class="mt-4">Test d'insertion directe</h3>
                    <form method="post" action="">
                        <div class="d-grid gap-2">
                            <button type="submit" name="test_insert" class="btn btn-primary">
                                <i class="fas fa-database me-2"></i>Tester l'insertion
                            </button>
                        </div>
                    </form>
                    
                    <?php if ($test_insert): ?>
                    <div class="mt-3">
                        <?php if ($insert_result): ?>
                        <div class="alert alert-success">
                            <strong>Succès :</strong> <?php echo $insert_result; ?>
                            
                            <?php if (isset($inserted_record)): ?>
                            <h4 class="mt-3">Enregistrement inséré :</h4>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <?php foreach (array_keys($inserted_record) as $key): ?>
                                            <th><?php echo htmlspecialchars($key); ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <?php foreach ($inserted_record as $value): ?>
                                            <td><?php echo htmlspecialchars($value ?? 'NULL'); ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-danger">
                            <strong>Erreur :</strong> <?php echo $insert_error; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-grid gap-2 mt-4">
                        <a href="new_timetable.php" class="btn btn-secondary">
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
