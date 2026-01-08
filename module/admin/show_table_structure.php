<?php
include_once('main.php');
include_once('../../service/mysqlcon.php');

// Initialiser le contenu pour le template
ob_start();

$admin_id = $_SESSION['login_id'];
$success_message = '';
$error_message = '';
$table_name = 'class_schedule';

// Récupérer la structure de la table
$structure_query = "SHOW CREATE TABLE $table_name";
$structure_result = $link->query($structure_query);
$table_structure = $structure_result->fetch_assoc();

// Récupérer les colonnes de la table
$columns_query = "SHOW COLUMNS FROM $table_name";
$columns_result = $link->query($columns_query);
$columns = [];
while ($column = $columns_result->fetch_assoc()) {
    $columns[] = $column;
}

// Récupérer les index de la table
$indexes_query = "SHOW INDEX FROM $table_name";
$indexes_result = $link->query($indexes_query);
$indexes = [];
while ($index = $indexes_result->fetch_assoc()) {
    $indexes[] = $index;
}

// Récupérer les contraintes de la table
$constraints_query = "
    SELECT 
        CONSTRAINT_NAME, 
        CONSTRAINT_TYPE,
        TABLE_NAME
    FROM 
        information_schema.TABLE_CONSTRAINTS 
    WHERE 
        TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = '$table_name'
";
$constraints_result = $link->query($constraints_query);
$constraints = [];
while ($constraint = $constraints_result->fetch_assoc()) {
    $constraints[] = $constraint;
}

// Récupérer les données de la table
$data_query = "SELECT * FROM $table_name LIMIT 10";
$data_result = $link->query($data_query);
$data = [];
while ($row = $data_result->fetch_assoc()) {
    $data[] = $row;
}

// Récupérer les données avec teacher_id = 0
$zero_teacher_query = "SELECT * FROM $table_name WHERE teacher_id = 0 LIMIT 10";
$zero_teacher_result = $link->query($zero_teacher_query);
$zero_teacher_data = [];
while ($row = $zero_teacher_result->fetch_assoc()) {
    $zero_teacher_data[] = $row;
}

// Récupérer les données avec la contrainte d'unicité
$unique_constraint_query = "
    SELECT 
        cs1.id as id1, 
        cs2.id as id2,
        cs1.teacher_id, 
        cs1.slot_id,
        cs1.day_of_week,
        cs1.class_id,
        cs1.subject_id,
        cs1.created_by
    FROM 
        $table_name cs1
    JOIN 
        $table_name cs2 ON cs1.teacher_id = cs2.teacher_id 
                      AND cs1.slot_id = cs2.slot_id 
                      AND cs1.day_of_week = cs2.day_of_week
                      AND cs1.id != cs2.id
    LIMIT 10
";
$unique_constraint_result = $link->query($unique_constraint_query);
$unique_constraint_data = [];
if ($unique_constraint_result) {
    while ($row = $unique_constraint_result->fetch_assoc()) {
        $unique_constraint_data[] = $row;
    }
}
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h2 class="card-title mb-0">Structure de la table <?php echo $table_name; ?></h2>
                </div>
                <div class="card-body">
                    <h3 class="h5 mb-3">Définition de la table</h3>
                    <div class="bg-light p-3 mb-4 overflow-auto" style="max-height: 300px;">
                        <pre><?php echo $table_structure['Create Table'] ?? 'Information non disponible'; ?></pre>
                    </div>
                    
                    <h3 class="h5 mb-3">Colonnes</h3>
                    <div class="table-responsive mb-4">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Nom</th>
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
                    
                    <h3 class="h5 mb-3">Index</h3>
                    <div class="table-responsive mb-4">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Colonne</th>
                                    <th>Non unique</th>
                                    <th>Séquence</th>
                                    <th>Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($indexes as $index): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($index['Key_name']); ?></td>
                                    <td><?php echo htmlspecialchars($index['Column_name']); ?></td>
                                    <td><?php echo htmlspecialchars($index['Non_unique']); ?></td>
                                    <td><?php echo htmlspecialchars($index['Seq_in_index']); ?></td>
                                    <td><?php echo htmlspecialchars($index['Index_type']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <h3 class="h5 mb-3">Contraintes</h3>
                    <div class="table-responsive mb-4">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Type</th>
                                    <th>Table</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($constraints as $constraint): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($constraint['CONSTRAINT_NAME']); ?></td>
                                    <td><?php echo htmlspecialchars($constraint['CONSTRAINT_TYPE']); ?></td>
                                    <td><?php echo htmlspecialchars($constraint['TABLE_NAME']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <h3 class="h5 mb-3">Données (10 premiers enregistrements)</h3>
                    <div class="table-responsive mb-4">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <?php foreach ($columns as $column): ?>
                                    <th><?php echo htmlspecialchars($column['Field']); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data as $row): ?>
                                <tr>
                                    <?php foreach ($columns as $column): ?>
                                    <td><?php echo htmlspecialchars($row[$column['Field']] ?? 'NULL'); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <h3 class="h5 mb-3">Enregistrements avec teacher_id = 0</h3>
                    <div class="table-responsive mb-4">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <?php foreach ($columns as $column): ?>
                                    <th><?php echo htmlspecialchars($column['Field']); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($zero_teacher_data) > 0): ?>
                                    <?php foreach ($zero_teacher_data as $row): ?>
                                    <tr>
                                        <?php foreach ($columns as $column): ?>
                                        <td><?php echo htmlspecialchars($row[$column['Field']] ?? 'NULL'); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="<?php echo count($columns); ?>">Aucun enregistrement trouvé</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <h3 class="h5 mb-3">Enregistrements en conflit (même teacher_id, slot_id et day_of_week)</h3>
                    <div class="table-responsive mb-4">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>ID 1</th>
                                    <th>ID 2</th>
                                    <th>Teacher ID</th>
                                    <th>Slot ID</th>
                                    <th>Day of Week</th>
                                    <th>Class ID</th>
                                    <th>Subject ID</th>
                                    <th>Created By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($unique_constraint_data) > 0): ?>
                                    <?php foreach ($unique_constraint_data as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['id1']); ?></td>
                                        <td><?php echo htmlspecialchars($row['id2']); ?></td>
                                        <td><?php echo htmlspecialchars($row['teacher_id']); ?></td>
                                        <td><?php echo htmlspecialchars($row['slot_id']); ?></td>
                                        <td><?php echo htmlspecialchars($row['day_of_week']); ?></td>
                                        <td><?php echo htmlspecialchars($row['class_id']); ?></td>
                                        <td><?php echo htmlspecialchars($row['subject_id']); ?></td>
                                        <td><?php echo htmlspecialchars($row['created_by']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8">Aucun enregistrement en conflit trouvé</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <a href="fix_class_schedule_ids.php" class="btn btn-primary">
                            <i class="fas fa-wrench me-2"></i>Corriger les IDs manquants
                        </a>
                        <a href="new_timetable.php" class="btn btn-outline-secondary">
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
