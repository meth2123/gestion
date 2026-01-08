<?php
require_once __DIR__ . '/../../db/config.php';
require_once __DIR__ . '/../../service/NotificationService.php';
require_once __DIR__ . '/main.php';
require_once __DIR__ . '/../../service/db_utils.php';

// Vérifier si l'utilisateur est connecté et est un parent
if (!isset($_SESSION['parent_id']) || $_SESSION['user_type'] !== 'parent') {
    header('Location: ../../login.php');
    exit;
}

// Récupérer l'ID du parent
$check = $_SESSION['parent_id'];

// Forcer le type à parent pour la compatibilité avec le service de notifications
// Note: Cela nécessite une modification de la table notifications pour accepter le type 'parent'
$user_type = 'parent';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Récupérer les notifications
$notificationService = new NotificationService($link, $check, $user_type);

// Compter toutes les notifications (la méthode countAll n'existe pas, nous utilisons une requête directe)
$stmt = $link->prepare("
    SELECT COUNT(*) as count 
    FROM notifications 
    WHERE user_id = ? AND user_type = ?
");
$stmt->bind_param("ss", $check, $user_type);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$total_notifications = $result['count'] ?? 0;

$notifications = $notificationService->getAll($per_page, $offset);
$total_pages = ceil($total_notifications / $per_page);

// Marquer toutes les notifications comme lues si demandé
if (isset($_GET['mark_all_read']) && $_GET['mark_all_read'] === '1') {
    if ($notificationService->markAllAsRead()) {
        $_SESSION['success'] = "Toutes les notifications ont été marquées comme lues";
    } else {
        $_SESSION['error'] = "Erreur lors du marquage des notifications";
    }
}

// Marquer une notification spécifique comme lue si demandé
if (isset($_POST['notification_id'])) {
    $notification_id = $_POST['notification_id'];
    if ($notificationService->markAsRead($notification_id)) {
        $_SESSION['success'] = "La notification a été marquée comme lue.";
    } else {
        $_SESSION['error'] = "Une erreur est survenue lors du marquage de la notification.";
    }
}

// Récupération des informations du parent
$parent_info = db_fetch_row(
    "SELECT * FROM parents WHERE id = ?",
    [$check],
    's'
);

if (!$parent_info) {
    header("Location: ../../?error=parent_not_found");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Espace Parent</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <img src="../../source/logo.jpg" class="h-12 w-12 object-contain" alt="School Management System"/>
                    <h1 class="ml-4 text-xl font-semibold text-gray-800">Système de Gestion Scolaire</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Bonjour, <?php echo htmlspecialchars($parent_info['fathername']); ?></span>
                    <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-md text-sm font-medium transition duration-150 ease-in-out">
                        <i class="fas fa-sign-out-alt mr-2"></i>Déconnexion
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Menu de navigation -->
    <div class="bg-white shadow-md mt-6 mx-4 lg:mx-auto max-w-7xl rounded-lg">
        <div class="flex flex-wrap justify-center gap-4 p-4">
            <a href="index.php" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-md text-sm font-medium transition duration-150 ease-in-out">
                <i class="fas fa-home mr-2"></i>Accueil
            </a>
            <a href="modify.php" class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded-md text-sm font-medium transition duration-150 ease-in-out">
                <i class="fas fa-key mr-2"></i>Changer le mot de passe
            </a>
            <a href="checkchild.php" class="bg-purple-500 hover:bg-purple-600 text-white px-6 py-2 rounded-md text-sm font-medium transition duration-150 ease-in-out">
                <i class="fas fa-child mr-2"></i>Information enfant
            </a>
            <a href="childpayment.php" class="bg-indigo-500 hover:bg-indigo-600 text-white px-6 py-2 rounded-md text-sm font-medium transition duration-150 ease-in-out">
                <i class="fas fa-money-bill-wave mr-2"></i>Paiements
            </a>
            <a href="childattendance.php" class="bg-pink-500 hover:bg-pink-600 text-white px-6 py-2 rounded-md text-sm font-medium transition duration-150 ease-in-out">
                <i class="fas fa-calendar-check mr-2"></i>Présences
            </a>
            <a href="childreport.php" class="bg-teal-500 hover:bg-teal-600 text-white px-6 py-2 rounded-md text-sm font-medium transition duration-150 ease-in-out">
                <i class="fas fa-file-alt mr-2"></i>Bulletins
            </a>
            <a href="notifications.php" class="bg-amber-500 hover:bg-amber-600 text-white px-6 py-2 rounded-md text-sm font-medium transition duration-150 ease-in-out">
                <i class="fas fa-bell mr-2"></i>Notifications
            </a>
        </div>
    </div>

    <!-- Contenu principal -->
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="bg-white shadow-lg rounded-lg overflow-hidden">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">Mes Notifications</h2>
                    <?php if ($total_notifications > 0): ?>
                    <a href="?mark_all_read=1" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium transition duration-150 ease-in-out">
                        <i class="fas fa-check-double mr-2"></i>Tout marquer comme lu
                    </a>
                    <?php endif; ?>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
                    <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                        <span class="sr-only">Fermer</span>
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
                    <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                        <span class="sr-only">Fermer</span>
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php endif; ?>

                <?php if (empty($notifications)): ?>
                <div class="text-center py-8">
                    <div class="text-gray-400 mb-4">
                        <i class="fas fa-bell-slash text-5xl"></i>
                    </div>
                    <p class="text-gray-500">Vous n'avez aucune notification pour le moment.</p>
                </div>
                <?php else: ?>
                <div class="divide-y divide-gray-200">
                    <?php foreach ($notifications as $notification): ?>
                    <div class="py-4 <?php echo $notification['is_read'] ? '' : 'bg-blue-50'; ?> rounded-lg p-4 mb-2">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center">
                                    <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($notification['title'] ?? ''); ?></h3>
                                    <?php if (!$notification['is_read']): ?>
                                    <span class="ml-2 bg-blue-500 text-white text-xs px-2 py-1 rounded-full">Nouveau</span>
                                    <?php endif; ?>
                                </div>
                                <p class="mt-1 text-gray-600"><?php echo htmlspecialchars($notification['message'] ?? ''); ?></p>
                                <div class="mt-2 flex items-center text-sm text-gray-500">
                                    <span>
                                        <?php
                                        $timestamp = strtotime($notification['created_at']);
                                        $now = time();
                                        $diff = $now - $timestamp;
                                        
                                        if ($diff < 60) {
                                            echo "Il y a " . $diff . " secondes";
                                        } elseif ($diff < 3600) {
                                            echo "Il y a " . floor($diff / 60) . " minutes";
                                        } elseif ($diff < 86400) {
                                            echo "Il y a " . floor($diff / 3600) . " heures";
                                        } elseif ($diff < 604800) {
                                            echo "Il y a " . floor($diff / 86400) . " jours";
                                        } else {
                                            echo date('d/m/Y H:i', $timestamp);
                                        }
                                        ?>
                                    </span>
                                    <?php if (!empty($notification['type'])): ?>
                                    <span class="ml-2 px-2 py-1 rounded-full text-xs <?php
                                        switch ($notification['type']) {
                                            case 'success':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'warning':
                                                echo 'bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'error':
                                                echo 'bg-red-100 text-red-800';
                                                break;
                                            default:
                                                echo 'bg-blue-100 text-blue-800';
                                        }
                                    ?>">
                                        <?php echo ucfirst($notification['type']); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($notification['link'])): ?>
                                <div class="mt-3">
                                    <a href="<?php echo htmlspecialchars($notification['link']); ?>" class="text-blue-500 hover:text-blue-700 font-medium">
                                        Voir plus <i class="fas fa-chevron-right ml-1"></i>
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php if (!$notification['is_read']): ?>
                            <div>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                    <button type="submit" class="ml-2 bg-gray-200 hover:bg-gray-300 text-gray-700 p-2 rounded-full transition duration-150 ease-in-out">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                <div class="mt-6">
                    <nav class="flex justify-center">
                        <ul class="flex">
                            <?php if ($page > 1): ?>
                            <li>
                                <a href="?page=<?php echo $page - 1; ?>" class="mx-1 px-3 py-2 bg-white rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            <?php else: ?>
                            <li>
                                <span class="mx-1 px-3 py-2 bg-gray-100 rounded-md border border-gray-300 text-gray-400">
                                    <i class="fas fa-chevron-left"></i>
                                </span>
                            </li>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            if ($start_page > 1) {
                                echo '<li><a href="?page=1" class="mx-1 px-3 py-2 bg-white rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">1</a></li>';
                                if ($start_page > 2) {
                                    echo '<li><span class="mx-1 px-3 py-2 bg-white rounded-md border border-gray-300 text-gray-700">...</span></li>';
                                }
                            }

                            for ($i = $start_page; $i <= $end_page; $i++) {
                                if ($i === $page) {
                                    echo '<li><span class="mx-1 px-3 py-2 bg-blue-500 rounded-md border border-blue-500 text-white">' . $i . '</span></li>';
                                } else {
                                    echo '<li><a href="?page=' . $i . '" class="mx-1 px-3 py-2 bg-white rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">' . $i . '</a></li>';
                                }
                            }

                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<li><span class="mx-1 px-3 py-2 bg-white rounded-md border border-gray-300 text-gray-700">...</span></li>';
                                }
                                echo '<li><a href="?page=' . $total_pages . '" class="mx-1 px-3 py-2 bg-white rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">' . $total_pages . '</a></li>';
                            }
                            ?>

                            <?php if ($page < $total_pages): ?>
                            <li>
                                <a href="?page=<?php echo $page + 1; ?>" class="mx-1 px-3 py-2 bg-white rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                            <?php else: ?>
                            <li>
                                <span class="mx-1 px-3 py-2 bg-gray-100 rounded-md border border-gray-300 text-gray-400">
                                    <i class="fas fa-chevron-right"></i>
                                </span>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white shadow-lg mt-8">
        <div class="max-w-7xl mx-auto py-4 px-4">
            <p class="text-center text-gray-500 text-sm">
                © <?php echo date('Y'); ?> Système de Gestion Scolaire. Tous droits réservés.
            </p>
        </div>
    </footer>
</body>
</html>
