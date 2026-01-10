<?php
require_once(__DIR__ . '/check_director_access.php');
require_once('../../db/config.php');
require_once('../../service/db_utils.php');

// Récupérer l'admin_id lié au directeur
$director_id = $_SESSION['userid'];
global $link;
if ($link === null || !$link) {
    die('Erreur de connexion à la base de données. Vérifiez les variables d\'environnement Railway.');
}
$sql = "SELECT created_by FROM director WHERE userid = ?";
$stmt = $link->prepare($sql);
$stmt->bind_param("s", $director_id);
$stmt->execute();
$result = $stmt->get_result();
$admin_row = $result->fetch_assoc();
$admin_id = $admin_row['created_by'];

// Paiements du mois en cours (filtrés par l'école du directeur)
$sql = "SELECT p.*, s.name as student_name 
        FROM payment p 
        INNER JOIN students s ON p.studentid = s.id 
        WHERE s.created_by = ?
        ORDER BY p.id DESC";
$stmt = $link->prepare($sql);
$stmt->bind_param("s", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

// Suppression d'un paiement
$success_message = '';
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = $_POST['delete_id'];
    $del_stmt = $link->prepare("DELETE FROM payment WHERE id = ? AND studentid IN (SELECT id FROM students WHERE created_by = ?)");
    $del_stmt->bind_param("is", $delete_id, $admin_id);
    if ($del_stmt->execute() && $del_stmt->affected_rows > 0) {
        $success_message = "Paiement supprimé avec succès.";
    } else {
        $error_message = "Erreur ou paiement non autorisé.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supprimer un Paiement</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="bg-white shadow-md">
        <div class="container mx-auto px-4 py-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <img src="../../source/logo.jpg" class="h-16 w-16 object-contain mr-4" alt="School Management System"/>
                    <h1 class="text-2xl font-bold text-gray-800">Système de Gestion Scolaire</h1>
                </div>
                <div class="flex items-center">
                    <span class="mr-4">Bonjour, <?php echo htmlspecialchars($_SESSION['name'] ?? 'Directeur');?> </span>
                    <a href="../../logout.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-sign-out-alt mr-2"></i>Déconnexion
                    </a>
                </div>
            </div>
        </div>
    </div>
    <nav class="bg-white shadow-md mt-4">
        <div class="container mx-auto px-4">
            <div class="flex space-x-4 py-4">
                <a href="index.php" class="text-gray-600 hover:text-blue-500 px-3 py-2 rounded-md">
                    <i class="fas fa-home mr-2"></i>Accueil
                </a>
                <a href="payment.php" class="text-gray-600 hover:text-blue-500 px-3 py-2 rounded-md">
                    <i class="fas fa-list mr-2"></i>Liste des Paiements
                </a>
                <a href="addPayment.php" class="text-gray-600 hover:text-blue-500 px-3 py-2 rounded-md">
                    <i class="fas fa-plus mr-2"></i>Ajouter un Paiement
                </a>
            </div>
        </div>
    </nav>
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">
                    <i class="fas fa-trash mr-2 text-red-500"></i>
                    Supprimer un Paiement
                </h2>
                <?php if ($success_message): ?>
                    <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                <div id="paymentList" class="mt-4">
                    <?php if ($result->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Étudiant</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Montant</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mois</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Année</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">#<?php echo htmlspecialchars($row['id']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($row['student_name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($row['amount']); ?> €</td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($row['month']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($row['year']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <form method="POST" onsubmit="return confirm('Confirmer la suppression de ce paiement ?');">
                                            <input type="hidden" name="delete_id" value="<?php echo htmlspecialchars($row['id']); ?>">
                                            <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded"><i class="fas fa-trash"></i> Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <div class="text-center text-gray-500 py-8">Aucun paiement à supprimer pour le moment.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
