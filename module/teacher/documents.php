<?php
include_once('main.php');
include_once('../../service/db_utils.php');
include_once('../../service/course_filters.php');

// Vérification de la session
if (!isset($_SESSION['teacher_id'])) {
    header("Location: ../../index.php");
    exit();
}

$teacher_id = $_SESSION['teacher_id'];

// Récupération des informations du professeur
$teacher_info = db_fetch_row(
    "SELECT * FROM teachers WHERE id = ?",
    [$teacher_id],
    's'
);

if (!$teacher_info) {
    header("Location: index.php?error=teacher_not_found");
    exit();
}

// Récupération des classes de l'enseignant
// Récupérer les classes où l'enseignant est directement assigné via la table course
$classes_from_course = db_fetch_all(
    "SELECT DISTINCT cl.* 
     FROM class cl
     JOIN course c ON cl.id = c.classid
     WHERE c.teacherid = ?
     AND " . get_course_modify_filter_sql($teacher_id, 'class') . "
     ORDER BY cl.name",
    [$teacher_id],
    's'
);

// Récupérer les classes où l'enseignant est assigné via la table student_teacher_course
$classes_from_stc = db_fetch_all(
    "SELECT DISTINCT cl.* 
     FROM class cl
     JOIN student_teacher_course stc ON cl.id = stc.class_id
     JOIN course c ON stc.course_id = c.id
     WHERE stc.teacher_id = ?
     AND " . get_course_modify_filter_sql($teacher_id, 'class') . "
     ORDER BY cl.name",
    [$teacher_id],
    's'
);

// Initialisation des variables
$success_message = '';
$error_message = '';
$selected_class = isset($_GET['class_id']) ? $_GET['class_id'] : '';
$documents = [];

// Fusionner les deux ensembles de classes en évitant les doublons
$classes = [];
$class_ids = [];

// Ajouter les classes de course
foreach ($classes_from_course as $class) {
    $classes[] = $class;
    $class_ids[] = $class['id'];
}

// Ajouter les classes de student_teacher_course qui ne sont pas déjà dans la liste
foreach ($classes_from_stc as $class) {
    if (!in_array($class['id'], $class_ids)) {
        $classes[] = $class;
        $class_ids[] = $class['id'];
    }
}

// Vérifier si la classe sélectionnée est dans la liste des classes autorisées
if ($selected_class && !in_array($selected_class, $class_ids)) {
    // Si la classe sélectionnée n'est pas autorisée, rediriger vers la page sans paramètre
    header("Location: documents.php?error=access_denied");
    exit();
}

// Vérifier si un dossier pour les documents existe, sinon le créer
$upload_dir = "../../uploads/documents/";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Traitement du téléchargement de document
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    $document_title = trim($_POST['document_title'] ?? '');
    $document_description = trim($_POST['document_description'] ?? '');
    $class_id = $_POST['class_id'] ?? '';
    
    // Validation des données
    if (empty($document_title)) {
        $error_message = "Le titre du document est requis.";
    } elseif (empty($class_id)) {
        $error_message = "Veuillez sélectionner une classe.";
    } elseif (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
        $error_message = "Erreur lors du téléchargement du fichier.";
    } else {
        // Vérifier si la classe appartient à l'enseignant (soit directement, soit via student_teacher_course)
        $class_check = db_fetch_row(
            "SELECT 1 
             FROM course 
             WHERE teacherid = ? AND classid = ? 
             LIMIT 1",
            [$teacher_id, $class_id],
            'ss'
        );
        
        // Si pas trouvé dans course, vérifier dans student_teacher_course
        if (!$class_check) {
            $class_check = db_fetch_row(
                "SELECT 1 
                 FROM student_teacher_course 
                 WHERE teacher_id = ? AND class_id = ? 
                 LIMIT 1",
                [$teacher_id, $class_id],
                'ss'
            );
        }
        
        if (!$class_check) {
            $error_message = "Vous n'avez pas accès à cette classe.";
        } else {
            $file = $_FILES['document_file'];
            $file_name = $file['name'];
            $file_tmp = $file['tmp_name'];
            $file_size = $file['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Vérifier l'extension du fichier
            $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar', 'jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($file_ext, $allowed_extensions)) {
                $error_message = "Extension de fichier non autorisée. Les extensions autorisées sont : " . implode(', ', $allowed_extensions);
            } elseif ($file_size > 10485760) { // 10 MB
                $error_message = "La taille du fichier ne doit pas dépasser 10 MB.";
            } else {
                // Générer un nom de fichier unique
                $new_file_name = uniqid('doc_') . '.' . $file_ext;
                $upload_path = $upload_dir . $new_file_name;
                
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    // Enregistrer les informations du document dans la base de données
                    try {
                        // Vérifier si la table documents existe, sinon la créer
                        $check_table = $link->query("SHOW TABLES LIKE 'documents'");
                        if ($check_table->num_rows == 0) {
                            $link->query("
                                CREATE TABLE documents (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    title VARCHAR(255) NOT NULL,
                                    description TEXT,
                                    file_name VARCHAR(255) NOT NULL,
                                    original_file_name VARCHAR(255) NOT NULL,
                                    file_size INT NOT NULL,
                                    file_type VARCHAR(50) NOT NULL,
                                    class_id VARCHAR(50) NOT NULL,
                                    teacher_id VARCHAR(50) NOT NULL,
                                    download_count INT DEFAULT 0,
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                            ");
                        }
                        
                        db_execute(
                            "INSERT INTO documents (title, description, file_name, original_file_name, file_size, file_type, class_id, teacher_id) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                            [$document_title, $document_description, $new_file_name, $file_name, $file_size, $file_ext, $class_id, $teacher_id],
                            'ssssisss'
                        );
                        
                        $success_message = "Le document a été téléchargé avec succès.";
                        
                        // Rediriger vers la même page avec le paramètre class_id
                        header("Location: documents.php?class_id=" . $class_id . "&success=1");
                        exit();
                    } catch (Exception $e) {
                        $error_message = "Une erreur est survenue lors de l'enregistrement du document : " . $e->getMessage();
                        // Supprimer le fichier en cas d'erreur
                        if (file_exists($upload_path)) {
                            unlink($upload_path);
                        }
                    }
                } else {
                    $error_message = "Erreur lors du déplacement du fichier téléchargé.";
                }
            }
        }
    }
}

// Traitement de la suppression d'un document
if (isset($_GET['delete_document']) && is_numeric($_GET['delete_document'])) {
    $document_id = $_GET['delete_document'];
    
    // Vérifier si le document appartient à l'enseignant
    $document = db_fetch_row(
        "SELECT * FROM documents WHERE id = ? AND teacher_id = ?",
        [$document_id, $teacher_id],
        'is'
    );
    
    if ($document) {
        // Supprimer le fichier
        $file_path = $upload_dir . $document['file_name'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        // Supprimer l'entrée de la base de données
        db_execute(
            "DELETE FROM documents WHERE id = ?",
            [$document_id],
            'i'
        );
        
        $success_message = "Le document a été supprimé avec succès.";
        
        // Rediriger vers la même page avec le paramètre class_id
        header("Location: documents.php?class_id=" . $selected_class . "&success=2");
        exit();
    } else {
        $error_message = "Vous n'avez pas l'autorisation de supprimer ce document.";
    }
}

// Récupération des documents pour la classe sélectionnée
if ($selected_class) {
    // Vérifier si la classe appartient à l'enseignant (soit directement, soit via student_teacher_course)
    $class_check = db_fetch_row(
        "SELECT 1 
         FROM course 
         WHERE teacherid = ? AND classid = ? 
         LIMIT 1",
        [$teacher_id, $selected_class],
        'ss'
    );
    
    // Si pas trouvé dans course, vérifier dans student_teacher_course
    if (!$class_check) {
        $class_check = db_fetch_row(
            "SELECT 1 
             FROM student_teacher_course 
             WHERE teacher_id = ? AND class_id = ? 
             LIMIT 1",
            [$teacher_id, $selected_class],
            'ss'
        );
    }
    
    // Si l'enseignant n'a pas accès à cette classe, le rediriger vers la page des documents sans paramètre
    if (!$class_check) {
        header("Location: documents.php?error=access_denied");
        exit();
    }
    
    // Si l'enseignant a accès à cette classe
    $documents = db_fetch_all(
        "SELECT d.*, t.name as teacher_name, c.name as class_name
         FROM documents d
         JOIN teachers t ON d.teacher_id = t.id
         JOIN class c ON d.class_id = c.id
         WHERE d.class_id = ?
         ORDER BY d.created_at DESC",
        [$selected_class],
        's'
    );
} else {
    $error_message = "Veuillez sélectionner une classe pour voir les documents partagés.";
    $selected_class = '';
}

// Si success est passé en paramètre GET
if (isset($_GET['success'])) {
    if ($_GET['success'] == '1') {
        $success_message = "Le document a été téléchargé avec succès.";
    } elseif ($_GET['success'] == '2') {
        $success_message = "Le document a été supprimé avec succès.";
    }
}

// Début de la capture du contenu
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Gestion des Documents</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
        <i class="fas fa-upload me-1"></i> Partager un document
    </button>
</div>

<?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($success_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Sélection de classe -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-6">
                <label for="class_id" class="form-label">Sélectionner une classe</label>
                <select name="class_id" id="class_id" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Choisir une classe --</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo htmlspecialchars($class['id']); ?>" <?php echo $selected_class === $class['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<!-- Liste des documents -->
<?php if ($selected_class): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0">Documents partagés</h5>
        </div>
        <div class="card-body">
            <?php if (empty($documents)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-file-alt fa-3x mb-3"></i>
                    <p>Aucun document partagé pour cette classe</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Titre</th>
                                <th>Description</th>
                                <th>Type</th>
                                <th>Taille</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $document): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php
                                            $icon_class = 'fas fa-file';
                                            switch ($document['file_type']) {
                                                case 'pdf':
                                                    $icon_class = 'fas fa-file-pdf text-danger';
                                                    break;
                                                case 'doc':
                                                case 'docx':
                                                    $icon_class = 'fas fa-file-word text-primary';
                                                    break;
                                                case 'xls':
                                                case 'xlsx':
                                                    $icon_class = 'fas fa-file-excel text-success';
                                                    break;
                                                case 'ppt':
                                                case 'pptx':
                                                    $icon_class = 'fas fa-file-powerpoint text-warning';
                                                    break;
                                                case 'jpg':
                                                case 'jpeg':
                                                case 'png':
                                                case 'gif':
                                                    $icon_class = 'fas fa-file-image text-info';
                                                    break;
                                                case 'zip':
                                                case 'rar':
                                                    $icon_class = 'fas fa-file-archive text-secondary';
                                                    break;
                                            }
                                            ?>
                                            <i class="<?php echo $icon_class; ?> fa-2x me-2"></i>
                                            <div>
                                                <div class="fw-medium"><?php echo htmlspecialchars($document['title']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($document['original_file_name']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo !empty($document['description']) ? htmlspecialchars($document['description']) : '<span class="text-muted">Aucune description</span>'; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark"><?php echo strtoupper(htmlspecialchars($document['file_type'])); ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $size = $document['file_size'];
                                        if ($size < 1024) {
                                            echo $size . ' B';
                                        } elseif ($size < 1048576) {
                                            echo round($size / 1024, 2) . ' KB';
                                        } else {
                                            echo round($size / 1048576, 2) . ' MB';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y H:i', strtotime($document['created_at'])); ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="download_document.php?id=<?php echo $document['id']; ?>" class="btn btn-outline-primary" title="Télécharger">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <?php if ($document['teacher_id'] === $teacher_id): ?>
                                                <a href="documents.php?class_id=<?php echo $selected_class; ?>&delete_document=<?php echo $document['id']; ?>" 
                                                   class="btn btn-outline-danger" 
                                                   title="Supprimer"
                                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce document ?');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>
        Veuillez sélectionner une classe pour voir les documents partagés.
    </div>
<?php endif; ?>

<!-- Modal pour télécharger un document -->
<div class="modal fade" id="uploadDocumentModal" tabindex="-1" aria-labelledby="uploadDocumentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadDocumentModalLabel">Partager un document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="document_title" class="form-label">Titre du document <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="document_title" name="document_title" required>
                    </div>
                    <div class="mb-3">
                        <label for="document_description" class="form-label">Description</label>
                        <textarea class="form-control" id="document_description" name="document_description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="class_id_modal" class="form-label">Classe <span class="text-danger">*</span></label>
                        <select class="form-select" id="class_id_modal" name="class_id" required>
                            <option value="">-- Choisir une classe --</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo htmlspecialchars($class['id']); ?>" <?php echo $selected_class === $class['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="document_file" class="form-label">Fichier <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="document_file" name="document_file" required>
                        <div class="form-text">
                            Formats acceptés : PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, ZIP, RAR, JPG, JPEG, PNG, GIF. Taille maximale : 10 MB.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="upload_document" class="btn btn-primary">Partager</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Récupérer le contenu capturé et l'assigner à la variable $content
$content = ob_get_clean();

// Inclure le template qui utilisera la variable $content
include('templates/layout.php');
?>
