<?php
require_once('../../vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Classes');

// En-têtes des colonnes
$headers = [
    'A1' => 'Nom de la classe',
    'B1' => 'Section',
    'C1' => 'Salle'
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

$sheet->getStyle('A1:C1')->applyFromArray($headerStyle);

// Ajuster la largeur des colonnes
$sheet->getColumnDimension('A')->setWidth(25);
$sheet->getColumnDimension('B')->setWidth(15);
$sheet->getColumnDimension('C')->setWidth(15);

// Ajouter des exemples de données
$exampleData = [
    ['Classe de CI', 'A', 'Salle 101'],
    ['Classe de CP', 'B', 'Salle 102'],
    ['Maternelle', '1', 'Salle 201']
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

$sheet->getStyle('A2:C4')->applyFromArray($dataStyle);

// Ajouter une feuille d'instructions
$instructionsSheet = $spreadsheet->createSheet();
$instructionsSheet->setTitle('Instructions');

$instructions = [
    ['INSTRUCTIONS POUR L\'IMPORTATION DES CLASSES'],
    [''],
    ['1. FORMAT DES DONNÉES (3 colonnes)'],
    ['   - Nom de la classe: Nom de la classe (ex: Classe de CI, Maternelle)'],
    ['   - Section: Section de la classe (ex: A, B, 1, 2)'],
    ['   - Salle: Numéro ou nom de la salle (ex: Salle 101, Salle 201)'],
    [''],
    ['2. GÉNÉRATION AUTOMATIQUE DE L\'ID'],
    ['   - L\'ID de classe est généré automatiquement'],
    ['   - Format: CLS-XXX-SECTION-999'],
    ['   - Exemples:'],
    ['     * "Classe de CI" + Section "A" → CLS-CLA-A-456'],
    ['     * "Maternelle" + Section "1" → CLS-MAT-1-789'],
    ['   - Les 3 premières lettres du nom sont utilisées'],
    [''],
    ['3. POINTS IMPORTANTS'],
    ['   - Tous les champs sont obligatoires'],
    ['   - Le système vérifie les doublons (même nom + section + salle)'],
    ['   - Ne modifiez pas les en-têtes des colonnes'],
    ['   - Supprimez les lignes d\'exemple avant l\'importation'],
    [''],
    ['4. EXEMPLES DE DONNÉES VALIDES'],
    ['   Nom de la classe | Section | Salle'],
    ['   Classe de CI     | A       | Salle 101'],
    ['   Classe de CP     | B       | Salle 102'],
    ['   Maternelle       | 1       | Salle 201'],
    ['   CE1              | A       | Salle 301'],
    [''],
    ['5. EN CAS D\'ERREUR'],
    ['   - "Champs obligatoires manquants" : Remplissez toutes les colonnes'],
    ['   - "Classe déjà existante" : Une classe avec le même nom, section et salle existe'],
    ['   - Vérifiez que toutes les lignes sont complètes'],
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
$instructionsSheet->getStyle('A8')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$instructionsSheet->getStyle('A17')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$instructionsSheet->getStyle('A23')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$instructionsSheet->getStyle('A30')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);

$instructionsSheet->getColumnDimension('A')->setWidth(100);

$spreadsheet->setActiveSheetIndex(0);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Modele_Import_Classes.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
