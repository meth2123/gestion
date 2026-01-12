<?php
include_once('main.php');
require_once '../../service/db_utils.php';

// Vérification de la session
if (!isset($_SESSION['student_id'])) {
    header("Location: ../../index.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// Récupération des informations de l'étudiant
$student_info = db_fetch_row(
    "SELECT s.*, c.id as class_id, c.name as class_name FROM students s
     LEFT JOIN class c ON s.classid = c.id
     WHERE s.id = ?",
    [$student_id],
    's'
);

if (!$student_info) {
    header("Location: index.php?error=student_not_found");
    exit();
}

$class_id = $student_info['classid'];
$class_name = $student_info['class_name'] ?? 'Non assigné';

// Traitement de l'envoi d'un message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $room_id = $_POST['room_id'] ?? null;
    $message = trim($_POST['message'] ?? '');
    $has_attachment = !empty($_FILES['attachment']['name']);
    
    if ($room_id && ($message || $has_attachment)) {
        // Vérifier que le salon appartient bien à la classe de l'étudiant (sécurité)
        $room_check = db_fetch_row(
            "SELECT r.id FROM chat_rooms r 
             WHERE r.id = ? AND CAST(r.class_id AS CHAR) = CAST(? AS CHAR) AND r.is_class_room = 1",
            [$room_id, $class_id],
            'is'
        );
        
        if (!$room_check) {
            header("Location: chat.php?error=unauthorized_room");
            exit();
        }
        
        // Vérifier si l'étudiant est un participant de ce salon
        $is_participant = db_fetch_row(
            "SELECT * FROM chat_participants WHERE room_id = ? AND user_id = ? AND user_type = 'student'",
            [$room_id, $student_id],
            'is'
        );
        
        if (!$is_participant) {
            // Ajouter l'étudiant comme participant
            db_execute(
                "INSERT INTO chat_participants (room_id, user_id, user_type) VALUES (?, ?, 'student')",
                [$room_id, $student_id],
                'is'
            );
        }
        
        // Insérer le message
        $message_id = db_execute(
            "INSERT INTO chat_messages (room_id, sender_id, sender_type, message, has_attachment) VALUES (?, ?, 'student', ?, ?)",
            [$room_id, $student_id, $message, $has_attachment ? 1 : 0],
            'issi',
            true // Retourner l'ID inséré
        );
        
        // Traiter la pièce jointe si présente
        if ($has_attachment && $message_id) {
            $file = $_FILES['attachment'];
            $file_name = $file['name'];
            $file_tmp = $file['tmp_name'];
            $file_size = $file['size'];
            $file_type = $file['type'];
            
            // Vérifier le type de fichier
            $allowed_types = [
                // Images
                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                // Vidéos
                'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime',
                // Documents
                'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                // Texte
                'text/plain'
            ];
            if (in_array($file_type, $allowed_types) && $file_size <= 20971520) { // 20 MB max
                // Générer un nom de fichier unique
                $extension = pathinfo($file_name, PATHINFO_EXTENSION);
                $new_file_name = uniqid('chat_') . '.' . $extension;
                $upload_path = '../../uploads/chat/' . $new_file_name;
                
                // Créer le répertoire s'il n'existe pas
                if (!is_dir('../../uploads/chat/')) {
                    mkdir('../../uploads/chat/', 0777, true);
                }
                
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    // Enregistrer l'information de la pièce jointe dans la base de données
                    db_execute(
                        "INSERT INTO chat_attachments (message_id, file_name, original_file_name, file_type, file_size) VALUES (?, ?, ?, ?, ?)",
                        [$message_id, $new_file_name, $file_name, $file_type, $file_size],
                        'isssi'
                    );
                }
            }
        }
        
        // Rediriger pour éviter la soumission multiple du formulaire
        header("Location: chat.php?room=" . $room_id);
        exit();
    }
}

// Récupération du salon actif
$active_room_id = $_GET['room'] ?? null;

// Si aucun salon n'est sélectionné, récupérer le salon de la classe de l'étudiant
if (!$active_room_id && $class_id) {
    $class_room = db_fetch_row(
        "SELECT id FROM chat_rooms WHERE CAST(class_id AS CHAR) = CAST(? AS CHAR) AND is_class_room = 1",
        [$class_id],
        's'
    );
    
    if ($class_room) {
        $active_room_id = $class_room['id'];
    }
}

// Récupération des salons disponibles pour l'étudiant
// Un étudiant ne peut voir QUE le salon de sa classe (sécurité)
$available_rooms = db_fetch_all(
    "SELECT r.* FROM chat_rooms r 
     WHERE CAST(r.class_id AS CHAR) = CAST(? AS CHAR) AND r.is_class_room = 1
     ORDER BY r.is_class_room DESC, r.name ASC",
    [$class_id],
    's'
);

// Récupération des messages du salon actif
$messages = [];
if ($active_room_id) {
    // D'abord récupérer tous les messages sans les pièces jointes
    $messages_data = db_fetch_all(
        "SELECT m.*, 
                CASE 
                    WHEN m.sender_type = 'student' THEN (SELECT name FROM students WHERE id = m.sender_id)
                    WHEN m.sender_type = 'teacher' THEN (SELECT name FROM teachers WHERE id = m.sender_id)
                END as sender_name
         FROM chat_messages m
         WHERE m.room_id = ?
         ORDER BY m.created_at ASC",
        [$active_room_id],
        'i'
    );
    
    // Ensuite récupérer les pièces jointes pour chaque message
    foreach ($messages_data as $message) {
        // Ajouter le message à notre tableau de messages
        $message_id = $message['id'];
        $messages[$message_id] = $message;
        $messages[$message_id]['attachments'] = [];
        
        // Si le message a une pièce jointe, la récupérer
        if ($message['has_attachment']) {
            $attachments = db_fetch_all(
                "SELECT * FROM chat_attachments WHERE message_id = ?",
                [$message_id],
                'i'
            );
            
            if (!empty($attachments)) {
                $messages[$message_id]['attachments'] = $attachments;
            }
        }
    }
    
    // Convertir le tableau associatif en tableau indexé pour l'affichage
    $messages = array_values($messages);
    
    // Vérifier que le salon actif appartient bien à la classe de l'étudiant (sécurité)
    $room_check = db_fetch_row(
        "SELECT r.id FROM chat_rooms r 
         WHERE r.id = ? AND CAST(r.class_id AS CHAR) = CAST(? AS CHAR) AND r.is_class_room = 1",
        [$active_room_id, $class_id],
        'is'
    );
    
    if (!$room_check) {
        // Le salon n'appartient pas à la classe de l'étudiant, rediriger vers le salon de sa classe
        if ($class_id) {
            $class_room = db_fetch_row(
                "SELECT id FROM chat_rooms WHERE CAST(class_id AS CHAR) = CAST(? AS CHAR) AND is_class_room = 1",
                [$class_id],
                's'
            );
            if ($class_room) {
                header("Location: chat.php?room=" . $class_room['id']);
                exit();
            }
        }
        header("Location: chat.php?error=unauthorized_room");
        exit();
    }
    
    // Ajouter l'étudiant comme participant s'il ne l'est pas déjà
    $is_participant = db_fetch_row(
        "SELECT * FROM chat_participants WHERE room_id = ? AND user_id = ? AND user_type = 'student'",
        [$active_room_id, $student_id],
        'is'
    );
    
    if (!$is_participant) {
        db_execute(
            "INSERT INTO chat_participants (room_id, user_id, user_type) VALUES (?, ?, 'student')",
            [$active_room_id, $student_id],
            'is'
        );
    }
}

// Récupération des informations du salon actif
$active_room = null;
if ($active_room_id) {
    $active_room = db_fetch_row(
        "SELECT * FROM chat_rooms WHERE id = ?",
        [$active_room_id],
        'i'
    );
}

// Début de la capture du contenu
ob_start();
?>

<div class="flex flex-col md:flex-row gap-4">
    <!-- Liste des salons -->
    <div class="w-full md:w-1/4 bg-white rounded-lg shadow-md p-4">
        <h2 class="text-xl font-bold mb-4">Salons de discussion</h2>
        <ul class="space-y-2">
            <?php foreach ($available_rooms as $room): ?>
                <li>
                    <a href="chat.php?room=<?php echo $room['id']; ?>" 
                       class="block p-2 rounded <?php echo ($active_room_id == $room['id']) ? 'bg-blue-100 text-blue-700' : 'hover:bg-gray-100'; ?>">
                        <?php if ($room['is_class_room']): ?>
                            <i class="fas fa-users mr-2"></i>
                        <?php else: ?>
                            <i class="fas fa-comment-alt mr-2"></i>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($room['name']); ?>
                    </a>
                </li>
            <?php endforeach; ?>
            
            <?php if (empty($available_rooms)): ?>
                <li class="text-gray-500 italic p-2">Aucun salon disponible</li>
            <?php endif; ?>
        </ul>
    </div>
    
    <!-- Notifications -->
    <?php if (isset($_GET['error'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p>
                <?php 
                $error = $_GET['error'];
                if ($error === 'unauthorized') {
                    echo "Vous n'avez pas l'autorisation de supprimer ce message.";
                } elseif ($error === 'message_not_found') {
                    echo "Le message que vous essayez de supprimer n'existe pas.";
                } elseif ($error === 'delete_failed') {
                    echo "La suppression du message a échoué. Veuillez réessayer.";
                } elseif ($error === 'unauthorized_room') {
                    echo "Vous n'avez pas accès à ce salon de discussion. Vous avez été redirigé vers le salon de votre classe.";
                } else {
                    echo "Une erreur s'est produite. Veuillez réessayer.";
                }
                ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success']) && $_GET['success'] === 'message_deleted'): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
            <p>Le message a été supprimé avec succès.</p>
        </div>
    <?php endif; ?>
    
    <!-- Zone de chat -->
    <div class="w-full md:w-3/4 bg-white rounded-lg shadow-md flex flex-col h-[600px]">
        <?php if ($active_room): ?>
            <!-- En-tête du salon -->
            <div class="p-4 border-b">
                <h2 class="text-xl font-bold"><?php echo htmlspecialchars($active_room['name']); ?></h2>
                <?php if ($active_room['description']): ?>
                    <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($active_room['description']); ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Messages -->
            <div class="flex-1 p-4 overflow-y-auto" id="chat-messages">
                <?php if (empty($messages)): ?>
                    <div class="text-center text-gray-500 italic py-4">
                        Aucun message dans ce salon. Soyez le premier à écrire !
                    </div>
                <?php else: ?>
                    <?php 
                    $current_date = '';
                    foreach ($messages as $message): 
                        $message_date = date('Y-m-d', strtotime($message['created_at']));
                        if ($message_date != $current_date):
                            $current_date = $message_date;
                            $date_display = ($message_date == date('Y-m-d')) ? 'Aujourd\'hui' : date('d/m/Y', strtotime($message_date));
                    ?>
                        <div class="flex justify-center my-2">
                            <span class="bg-gray-200 text-gray-700 text-xs px-2 py-1 rounded-full"><?php echo $date_display; ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="flex <?php echo ($message['sender_id'] == $student_id) ? 'justify-end' : 'justify-start'; ?> mb-4">
                        <div class="max-w-[70%] <?php echo ($message['sender_id'] == $student_id) ? 'bg-blue-100 text-blue-800' : 'bg-gray-100'; ?> rounded-lg px-4 py-2 shadow-sm relative group">
                            <?php if ($message['sender_id'] == $student_id): ?>
                                <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <form action="delete_message.php" method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce message ?');" class="inline">
                                        <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                        <input type="hidden" name="room_id" value="<?php echo $active_room_id; ?>">
                                        <button type="submit" class="text-red-500 hover:text-red-700 p-1">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                            <div class="flex justify-between items-center mb-1">
                                <span class="font-semibold text-sm">
                                    <?php echo ($message['sender_id'] == $student_id) ? 'Vous' : htmlspecialchars($message['sender_name']); ?>
                                </span>
                                <span class="text-xs text-gray-500">
                                    <?php echo date('H:i', strtotime($message['created_at'])); ?>
                                </span>
                            </div>
                            
                            <?php if (!empty($message['message'])): ?>
                                <p class="text-sm whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($message['has_attachment'] && !empty($message['attachments'])): ?>
                                <?php foreach ($message['attachments'] as $attachment): ?>
                                <div class="mt-2 p-2 bg-white rounded border">
                                    <?php 
                                    // Déterminer le type de fichier
                                    $is_image = strpos($attachment['file_type'], 'image/') === 0;
                                    $is_video = strpos($attachment['file_type'], 'video/') === 0;
                                    
                                    if ($is_image): // Afficher l'image directement
                                    ?>
                                        <div class="mb-2">
                                            <img src="../../uploads/chat/<?php echo $attachment['file_name']; ?>" 
                                                 alt="<?php echo htmlspecialchars($attachment['original_file_name']); ?>" 
                                                 class="max-w-full rounded max-h-64 object-contain">
                                        </div>
                                    <?php elseif ($is_video): // Afficher la vidéo directement ?>
                                        <div class="mb-2">
                                            <video controls class="max-w-full rounded max-h-64">
                                                <source src="../../uploads/chat/<?php echo $attachment['file_name']; ?>" type="<?php echo $attachment['file_type']; ?>">
                                                Votre navigateur ne prend pas en charge la lecture de vidéos.
                                            </video>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <a href="download_chat_attachment.php?id=<?php echo $attachment['id']; ?>" class="flex items-center text-blue-600 hover:underline">
                                        <?php 
                                        $file_icon = 'fa-file';
                                        if ($is_image) {
                                            $file_icon = 'fa-file-image';
                                        } elseif ($is_video) {
                                            $file_icon = 'fa-file-video';
                                        } elseif (strpos($attachment['file_type'], 'application/pdf') === 0) {
                                            $file_icon = 'fa-file-pdf';
                                        } elseif (strpos($attachment['file_type'], 'application/msword') === 0 || strpos($attachment['file_type'], 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') === 0) {
                                            $file_icon = 'fa-file-word';
                                        } elseif (strpos($attachment['file_type'], 'text/') === 0) {
                                            $file_icon = 'fa-file-alt';
                                        }
                                        ?>
                                        <i class="fas <?php echo $file_icon; ?> mr-2"></i>
                                        <?php echo htmlspecialchars($attachment['original_file_name']); ?>
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Formulaire d'envoi de message -->
            <div class="p-4 border-t">
                <form action="chat.php" method="post" enctype="multipart/form-data" class="flex flex-col space-y-2">
                    <input type="hidden" name="action" value="send_message">
                    <input type="hidden" name="room_id" value="<?php echo $active_room_id; ?>">
                    
                    <textarea name="message" placeholder="Écrivez votre message..." class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" rows="2"></textarea>
                    
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <label for="attachment" class="cursor-pointer text-blue-600 hover:text-blue-800">
                                <i class="fas fa-paperclip mr-1"></i> Joindre un fichier
                            </label>
                            <input type="file" id="attachment" name="attachment" class="hidden">
                            <span id="file-name" class="ml-2 text-sm text-gray-600"></span>
                        </div>
                        
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                            <i class="fas fa-paper-plane mr-1"></i> Envoyer
                        </button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="flex items-center justify-center h-full">
                <div class="text-center p-4">
                    <i class="fas fa-comments text-gray-300 text-5xl mb-4"></i>
                    <h2 class="text-xl font-bold text-gray-700 mb-2">Bienvenue dans le chat</h2>
                    <p class="text-gray-600">Sélectionnez un salon pour commencer à discuter</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Faire défiler jusqu'au dernier message
    const chatMessages = document.getElementById('chat-messages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    // Afficher le nom du fichier sélectionné
    const fileInput = document.getElementById('attachment');
    const fileNameDisplay = document.getElementById('file-name');
    
    if (fileInput && fileNameDisplay) {
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                fileNameDisplay.textContent = this.files[0].name;
            } else {
                fileNameDisplay.textContent = '';
            }
        });
    }
});
</script>

<?php
// Récupérer le contenu capturé et l'assigner à la variable $content
$content = ob_get_clean();

// Inclure le template qui utilisera la variable $content
include('templates/layout.php');
?>
