<?php
require_once('../../vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Assignations');

// En-têtes des colonnes
$headers = [
    'A1' => 'ID Étudiant',
    'B1' => 'ID Enseignant',
    'C1' => 'ID Cours',
    'D1' => 'ID Classe'
];

foreach ($headers as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}

// Style pour les en-têtes
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 12
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '4472C4']
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ]
];

$sheet->getStyle('A1:D1')->applyFromArray($headerStyle);

// Ajuster la largeur des colonnes
$sheet->getColumnDimension('A')->setWidth(20);
$sheet->getColumnDimension('B')->setWidth(20);
$sheet->getColumnDimension('C')->setWidth(15);
$sheet->getColumnDimension('D')->setWidth(20);

// Ajouter des exemples de données
$exampleData = [
    ['STU001', 'TE-AMA-1234', '1', 'CLS-001'],
    ['STU002', 'TE-FAT-5678', '2', 'CLS-001'],
    ['STU003', 'TE-MOU-9012', '1', 'CLS-002']
];

$row = 2;
foreach ($exampleData as $data) {
    $col = 'A';
    foreach ($data as $value) {
        $sheet->setCellValue($col . $row, $value);
        $col++;
    }
    $row++;
}

// Style pour les données d'exemple
$dataStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'CCCCCC']
        ]
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'F0F0F0']
    ]
];

$sheet->getStyle('A2:D4')->applyFromArray($dataStyle);

// Ajouter une feuille d'instructions
$instructionsSheet = $spreadsheet->createSheet();
$instructionsSheet->setTitle('Instructions');

$instructions = [
    ['INSTRUCTIONS POUR L\'IMPORTATION DES ASSIGNATIONS'],
    [''],
    ['1. FORMAT DES DONNÉES (4 colonnes)'],
    ['   - ID Étudiant: ID de l\'étudiant (ex: STU001)'],
    ['   - ID Enseignant: ID de l\'enseignant (ex: TE-AMA-1234)'],
    ['   - ID Cours: ID du cours (ex: 1, 2, 3)'],
    ['   - ID Classe: ID de la classe (ex: CLS-001)'],
    [''],
    ['2. POINTS IMPORTANTS'],
    ['   - Tous les ID doivent exister dans le système'],
    ['   - Consultez les listes sur la page d\'importation pour voir les ID disponibles'],
    ['   - Un étudiant peut avoir plusieurs assignations (différents cours/enseignants)'],
    ['   - Le système vérifie les doublons (même étudiant + enseignant + cours + classe)'],
    ['   - Ne modifiez pas les en-têtes des colonnes'],
    ['   - Supprimez les lignes d\'exemple avant l\'importation'],
    [''],
    ['3. COMMENT OBTENIR LES ID'],
    ['   Sur la page d\'importation, vous trouverez 4 tableaux :'],
    ['   - Étudiants Disponibles : Liste des ID et noms d\'étudiants'],
    ['   - Enseignants Disponibles : Liste des ID et noms d\'enseignants'],
    ['   - Cours Disponibles : Liste des ID et noms de cours'],
    ['   - Classes Disponibles : Liste des ID et noms de classes'],
    ['   Copiez les ID exacts depuis ces tableaux'],
    [''],
    ['4. EXEMPLES DE DONNÉES VALIDES'],
    ['   ID Étudiant | ID Enseignant | ID Cours | ID Classe'],
    ['   STU001      | TE-AMA-1234   | 1        | CLS-001'],
    ['   STU002      | TE-FAT-5678   | 2        | CLS-001'],
    ['   STU003      | TE-MOU-9012   | 1        | CLS-002'],
    ['   STU001      | TE-FAT-5678   | 3        | CLS-001  (même étudiant, autre cours)'],
    [''],
    ['5. EN CAS D\'ERREUR'],
    ['   - "Étudiant inexistant" : Vérifiez l\'ID étudiant dans la liste'],
    ['   - "Enseignant inexistant" : Vérifiez l\'ID enseignant dans la liste'],
    ['   - "Cours inexistant" : Vérifiez l\'ID cours dans la liste'],
    ['   - "Classe inexistante" : Vérifiez l\'ID classe dans la liste'],
    ['   - "Assignation déjà existante" : Cette combinaison existe déjà'],
    ['   - Assurez-vous de copier les ID exactement comme affichés'],
    ['   - Vous pouvez cocher "Ignorer les lignes avec erreurs" pour continuer']
];

$row = 1;
foreach ($instructions as $instruction) {
    $instructionsSheet->setCellValue('A' . $row, $instruction[0]);
    $row++;
}

// Style pour le titre
$instructionsSheet->getStyle('A1')->applyFromArray([
    'font' => [
        'bold' => true,
        'size' => 14,
        'color' => ['rgb' => '4472C4']
    ]
]);

// Style pour les sections
$instructionsSheet->getStyle('A3')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$instructionsSheet->getStyle('A9')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$instructionsSheet->getStyle('A17')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$instructionsSheet->getStyle('A25')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$instructionsSheet->getStyle('A32')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);

$instructionsSheet->getColumnDimension('A')->setWidth(100);

$spreadsheet->setActiveSheetIndex(0);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Modele_Import_Assignations.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
