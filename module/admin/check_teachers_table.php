<?php
include_once('main.php');
include_once('../../service/mysqlcon.php');

// Initialiser le contenu pour le template
ob_start();

// Vérifier la structure de la table teachers
$teachers_columns = [];
$columns_query = "SHOW COLUMNS FROM teachers";
$columns_result = $link->query($columns_query);
while ($column = $columns_result->fetch_assoc()) {
    $teachers_columns[] = $column;
}

// Récupérer quelques exemples d'enseignants
$teachers_data = [];
$data_query = "SELECT * FROM teachers LIMIT 5";
$data_result = $link->query($data_query);
while ($row = $data_result->fetch_assoc()) {
    $teachers_data[] = $row;
}
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h2 class="card-title mb-0">Structure de la table des enseignants</h2>
                </div>
                <div class="card-body">
                    <h3>Structure de la table teachers</h3>
                    <?php if (!empty($teachers_columns)): ?>
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
                                <?php foreach ($teachers_columns as $column): ?>
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
                    <div class="alert alert-warning">La table teachers n'existe pas ou n'a pas de colonnes.</div>
                    <?php endif; ?>
                    
                    <h3>Données de la table teachers (5 premiers enregistrements)</h3>
                    <?php if (!empty($teachers_data)): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <?php foreach (array_keys($teachers_data[0]) as $key): ?>
                                    <th><?php echo htmlspecialchars($key); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($teachers_data as $row): ?>
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
                    <div class="alert alert-warning">La table teachers n'existe pas ou ne contient pas de données.</div>
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
