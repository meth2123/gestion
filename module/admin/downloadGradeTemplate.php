<?php
require_once('../../vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Notes');

// En-têtes des colonnes
$headers = [
    'A1' => 'ID Étudiant',
    'B1' => 'ID Enseignant',
    'C1' => 'ID Cours',
    'D1' => 'ID Classe',
    'E1' => 'Type',
    'F1' => 'Note',
    'G1' => 'Semestre'
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

$sheet->getStyle('A1:G1')->applyFromArray($headerStyle);

// Ajuster la largeur des colonnes
$sheet->getColumnDimension('A')->setWidth(18);
$sheet->getColumnDimension('B')->setWidth(18);
$sheet->getColumnDimension('C')->setWidth(12);
$sheet->getColumnDimension('D')->setWidth(20);
$sheet->getColumnDimension('E')->setWidth(12);
$sheet->getColumnDimension('F')->setWidth(10);
$sheet->getColumnDimension('G')->setWidth(12);

// Ajouter des exemples de données
$exampleData = [
    ['STU001', 'TE-AMA-1234', '1', 'CLS-MAT-1-592', 'devoir', '15', '1'],
    ['STU001', 'TE-AMA-1234', '1', 'CLS-MAT-1-592', 'devoir', '16.5', '1'],
    ['STU001', 'TE-AMA-1234', '1', 'CLS-MAT-1-592', 'examen', '14', '1'],
    ['STU002', 'TE-FAT-5678', '2', 'CLS-MAT-1-592', 'devoir', '12', '1'],
    ['STU002', 'TE-FAT-5678', '2', 'CLS-MAT-1-592', 'examen', '13.5', '1']
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

$sheet->getStyle('A2:G6')->applyFromArray($dataStyle);

// Ajouter une feuille d'instructions
$instructionsSheet = $spreadsheet->createSheet();
$instructionsSheet->setTitle('Instructions');

$instructions = [
    ['INSTRUCTIONS POUR L\'IMPORTATION DES NOTES'],
    [''],
    ['1. FORMAT DES DONNÉES (7 colonnes)'],
    ['   - ID Étudiant: ID de l\'étudiant (ex: STU001)'],
    ['   - ID Enseignant: ID de l\'enseignant (ex: TE-AMA-1234)'],
    ['   - ID Cours: ID du cours (ex: 1, 2, 3)'],
    ['   - ID Classe: ID de la classe (ex: CLS-MAT-1-592)'],
    ['   - Type: Type de note - devoir ou examen'],
    ['   - Note: Valeur de la note entre 0 et 20 (ex: 15, 16.5, 14)'],
    ['   - Semestre: Numéro du semestre - 1 ou 2'],
    [''],
    ['2. POINTS IMPORTANTS'],
    ['   - Tous les ID doivent exister dans le système'],
    ['   - La note doit être entre 0 et 20'],
    ['   - Le type doit être exactement "devoir" ou "examen" (en minuscules)'],
    ['   - Le semestre doit être 1 ou 2'],
    ['   - Vous pouvez saisir plusieurs notes pour le même étudiant'],
    ['   - Les devoirs sont numérotés automatiquement (devoir 1, devoir 2, etc.)'],
    ['   - Consultez les listes sur la page d\'importation pour voir les ID disponibles'],
    ['   - Ne modifiez pas les en-têtes des colonnes'],
    ['   - Supprimez les lignes d\'exemple avant l\'importation'],
    [''],
    ['3. COMMENT OBTENIR LES ID'],
    ['   Sur la page d\'importation, vous trouverez des tableaux :'],
    ['   - Étudiants Disponibles : Liste des ID et noms d\'étudiants'],
    ['   - Cours Disponibles : Liste des ID et noms de cours'],
    ['   Copiez les ID exacts depuis ces tableaux'],
    [''],
    ['4. EXEMPLES DE DONNÉES VALIDES'],
    ['   ID Étudiant | ID Enseignant | ID Cours | ID Classe      | Type   | Note | Semestre'],
    ['   STU001      | TE-AMA-1234   | 1        | CLS-MAT-1-592  | devoir | 15   | 1'],
    ['   STU001      | TE-AMA-1234   | 1        | CLS-MAT-1-592  | devoir | 16.5 | 1'],
    ['   STU001      | TE-AMA-1234   | 1        | CLS-MAT-1-592  | examen | 14   | 1'],
    ['   STU002      | TE-FAT-5678   | 2        | CLS-MAT-1-592  | devoir | 12   | 1'],
    [''],
    ['5. TYPES DE NOTES'],
    ['   - devoir : Note de devoir (peut en avoir plusieurs)'],
    ['   - examen : Note d\'examen (généralement 1 par semestre)'],
    ['   Les devoirs sont automatiquement numérotés dans l\'ordre d\'importation'],
    [''],
    ['6. EN CAS D\'ERREUR'],
    ['   - "Étudiant inexistant" : Vérifiez l\'ID étudiant dans la liste'],
    ['   - "Enseignant inexistant" : Vérifiez l\'ID enseignant dans la liste'],
    ['   - "Cours inexistant" : Vérifiez l\'ID cours dans la liste'],
    ['   - "Classe inexistante" : Vérifiez l\'ID classe dans la liste'],
    ['   - "Note invalide" : La note doit être entre 0 et 20'],
    ['   - "Type invalide" : Utilisez "devoir" ou "examen"'],
    ['   - "Semestre invalide" : Utilisez 1 ou 2'],
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
$instructionsSheet->getStyle('A12')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$instructionsSheet->getStyle('A24')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$instructionsSheet->getStyle('A30')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$instructionsSheet->getStyle('A37')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$instructionsSheet->getStyle('A42')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);

$instructionsSheet->getColumnDimension('A')->setWidth(120);

$spreadsheet->setActiveSheetIndex(0);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Modele_Import_Notes.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
