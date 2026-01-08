<?php
// Gestion manuelle des années scolaires
require_once(__DIR__ . '/../../db/config.php');
require_once('../../service/db_utils.php');

// Ajouter une année scolaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_year'])) {
    $year = trim($_POST['school_year']);
    if ($year) {
        db_execute("INSERT IGNORE INTO school_years (year) VALUES (?)", [$year], 's');
    }
}
// Suppression
if (isset($_GET['delete'])) {
    db_execute("DELETE FROM school_years WHERE year = ?", [$_GET['delete']], 's');
}
$years = db_fetch_all("SELECT * FROM school_years ORDER BY year DESC");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des années scolaires</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <h2 class="mb-4">Gestion des années scolaires</h2>
    <form method="post" class="row g-3 mb-4">
        <div class="col-auto">
            <input type="text" name="school_year" class="form-control" placeholder="Ex: 2024-2025" required>
        </div>
        <div class="col-auto">
            <button type="submit" name="add_year" class="btn btn-primary">Ajouter</button>
        </div>
    </form>
    <table class="table table-bordered bg-white">
        <thead><tr><th>Année scolaire</th><th>Action</th></tr></thead>
        <tbody>
            <?php foreach ($years as $y): ?>
                <tr>
                    <td><?= htmlspecialchars($y['year']) ?></td>
                    <td><a href="?delete=<?= urlencode($y['year']) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer cette année ?')">Supprimer</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <a href="index.php" class="btn btn-secondary mt-3">Retour</a>
</div>
</body>
</html>
