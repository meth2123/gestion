<?php
include_once('main.php');
include_once('../../service/db_utils.php');

// Vérification de la session
if (!isset($_SESSION['student_id'])) {
    header("Location: ../../index.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// Récupération des informations de l'étudiant
$student_info = db_fetch_row(
    "SELECT s.*, c.name as class_name 
     FROM students s
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

// Récupération des documents partagés avec la classe de l'étudiant
$documents = [];

if ($class_id) {
    $documents = db_fetch_all(
        "SELECT d.*, t.name as teacher_name, c.name as course_name, co.id as course_id
         FROM documents d
         JOIN teachers t ON d.teacher_id = t.id
         LEFT JOIN course co ON (co.teacherid = t.id AND co.classid = d.class_id)
         JOIN class c ON d.class_id = c.id
         WHERE d.class_id = ?
         ORDER BY d.created_at DESC",
        [$class_id],
        's'
    );
}

// Récupération des matières de l'étudiant pour filtrer les documents
$courses = db_fetch_all(
    "SELECT c.*, t.name as teacher_name
     FROM course c
     JOIN teachers t ON c.teacherid = t.id
     WHERE c.classid = ?
     ORDER BY c.name",
    [$class_id],
    's'
);

// Filtrer par matière si demandé
$filtered_course_id = isset($_GET['course_id']) ? $_GET['course_id'] : '';
$filtered_documents = $documents;

if ($filtered_course_id && !empty($documents)) {
    $filtered_documents = array_filter($documents, function($doc) use ($filtered_course_id) {
        return isset($doc['course_id']) && (string)$doc['course_id'] === (string)$filtered_course_id;
    });
}

// Début de la capture du contenu
ob_start();
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800 mb-2">Documents partagés</h1>
    <p class="text-gray-600">Classe : <?php echo htmlspecialchars($student_info['class_name'] ?? 'Non assignée'); ?></p>
</div>

<?php if (empty($class_id)): ?>
    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6">
        <p>Vous n'êtes pas encore assigné à une classe. Veuillez contacter l'administration.</p>
    </div>
<?php else: ?>
    <!-- Filtres -->
    <div class="bg-white shadow-md rounded-lg p-4 mb-6">
        <form method="GET" class="flex flex-wrap items-center gap-4">
            <div class="flex-grow">
                <label for="course_id" class="block text-sm font-medium text-gray-700 mb-1">Filtrer par matière</label>
                <select name="course_id" id="course_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" onchange="this.form.submit()">
                    <option value="">Toutes les matières</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?php echo htmlspecialchars($course['id']); ?>" <?php echo $filtered_course_id === $course['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($course['name'] . ' (' . $course['teacher_name'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($filtered_course_id): ?>
                <div class="self-end">
                    <a href="documents.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700">
                        <i class="fas fa-times mr-2"></i>Réinitialiser
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Liste des documents -->
    <?php if (empty($filtered_documents)): ?>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6">
            <p>Aucun document n'a été partagé avec votre classe<?php echo $filtered_course_id ? ' pour cette matière' : ''; ?>.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($filtered_documents as $document): ?>
                <div class="bg-white shadow-md rounded-lg overflow-hidden">
                    <div class="p-4">
                        <?php
                        $icon_class = 'fas fa-file';
                        $bg_color = 'bg-gray-100';
                        $text_color = 'text-gray-500';
                        
                        switch ($document['file_type']) {
                            case 'pdf':
                                $icon_class = 'fas fa-file-pdf';
                                $bg_color = 'bg-red-100';
                                $text_color = 'text-red-500';
                                break;
                            case 'doc':
                            case 'docx':
                                $icon_class = 'fas fa-file-word';
                                $bg_color = 'bg-blue-100';
                                $text_color = 'text-blue-500';
                                break;
                            case 'xls':
                            case 'xlsx':
                                $icon_class = 'fas fa-file-excel';
                                $bg_color = 'bg-green-100';
                                $text_color = 'text-green-500';
                                break;
                            case 'ppt':
                            case 'pptx':
                                $icon_class = 'fas fa-file-powerpoint';
                                $bg_color = 'bg-yellow-100';
                                $text_color = 'text-yellow-500';
                                break;
                            case 'jpg':
                            case 'jpeg':
                            case 'png':
                            case 'gif':
                                $icon_class = 'fas fa-file-image';
                                $bg_color = 'bg-purple-100';
                                $text_color = 'text-purple-500';
                                break;
                            case 'zip':
                            case 'rar':
                                $icon_class = 'fas fa-file-archive';
                                $bg_color = 'bg-gray-100';
                                $text_color = 'text-gray-500';
                                break;
                        }
                        ?>
                        <div class="flex items-center mb-4">
                            <div class="flex-shrink-0 <?php echo $bg_color; ?> <?php echo $text_color; ?> p-3 rounded-lg">
                                <i class="<?php echo $icon_class; ?> text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($document['title']); ?></h3>
                                <p class="text-sm text-gray-500">
                                    <span class="font-medium">Prof:</span> <?php echo htmlspecialchars($document['teacher_name']); ?>
                                </p>
                            </div>
                        </div>
                        
                        <?php if (!empty($document['description'])): ?>
                            <div class="mb-4">
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($document['description']); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="flex justify-between items-center text-sm text-gray-500 mb-4">
                            <span>
                                <i class="fas fa-calendar-alt mr-1"></i>
                                <?php echo date('d/m/Y', strtotime($document['created_at'])); ?>
                            </span>
                            <span>
                                <i class="fas fa-file mr-1"></i>
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
                            </span>
                        </div>
                        
                        <div class="mt-4">
                            <a href="download_document.php?id=<?php echo $document['id']; ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 w-full justify-center">
                                <i class="fas fa-download mr-2"></i>Télécharger
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php
// Récupérer le contenu capturé et l'assigner à la variable $content
$content = ob_get_clean();

// Inclure le template qui utilisera la variable $content
include('templates/layout.php');
?>
