<?php
require_once('../../vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Coefficients');

// En-têtes des colonnes
$headers = [
    'A1' => 'ID Cours',
    'B1' => 'ID Classe',
    'C1' => 'Nouveau Coefficient'
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
$sheet->getColumnDimension('A')->setWidth(15);
$sheet->getColumnDimension('B')->setWidth(25);
$sheet->getColumnDimension('C')->setWidth(20);

// Ajouter des exemples de données
$exampleData = [
    ['1', 'CLS-MAT-1-592', '2'],
    ['2', 'CLS-MAT-1-592', '3'],
    ['3', 'CLS-MAT-1-592', '1.5'],
    ['1', 'CLS-001', '2'],
    ['4', 'CLS-001', '2.5']
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

$sheet->getStyle('A2:C6')->applyFromArray($dataStyle);

// Ajouter une feuille d'instructions
$instructionsSheet = $spreadsheet->createSheet();
$instructionsSheet->setTitle('Instructions');

$instructions = [
    ['INSTRUCTIONS POUR L\'IMPORTATION DES COEFFICIENTS'],
    [''],
    ['1. FORMAT DES DONNÉES (3 colonnes)'],
    ['   - ID Cours: ID du cours (ex: 1, 2, 3)'],
    ['   - ID Classe: ID de la classe (ex: CLS-MAT-1-592, CLS-001)'],
    ['   - Nouveau Coefficient: Valeur du coefficient (ex: 1, 2, 3, 1.5, 2.5)'],
    [''],
    ['2. POINTS IMPORTANTS'],
    ['   - Les ID Classe et ID Cours doivent exister dans le système'],
    ['   - Le coefficient doit être un nombre positif'],
    ['   - Vous pouvez utiliser des décimales (ex: 1.5, 2.5)'],
    ['   - Un même cours peut avoir des coefficients différents selon les classes'],
    ['   - Si un coefficient existe déjà, il sera mis à jour'],
    ['   - Consultez les listes sur la page d\'importation pour voir les ID disponibles'],
    ['   - Ne modifiez pas les en-têtes des colonnes'],
    ['   - Supprimez les lignes d\'exemple avant l\'importation'],
    [''],
    ['3. COMMENT OBTENIR LES ID'],
    ['   Sur la page d\'importation, vous trouverez 2 tableaux :'],
    ['   - Classes Disponibles : Liste des ID et noms de classes'],
    ['   - Cours Disponibles : Liste des ID et noms de cours'],
    ['   Copiez les ID exacts depuis ces tableaux'],
    [''],
    ['4. EXEMPLES DE DONNÉES VALIDES'],
    ['   ID Cours | ID Classe      | Nouveau Coefficient'],
    ['   1        | CLS-MAT-1-592  | 2           (Mathématiques = coef 2)'],
    ['   2        | CLS-MAT-1-592  | 3           (Français = coef 3)'],
    ['   3        | CLS-MAT-1-592  | 1.5         (Sciences = coef 1.5)'],
    ['   1        | CLS-001        | 2           (Même cours, autre classe)'],
    [''],
    ['5. COEFFICIENTS MULTIPLES'],
    ['   - Vous pouvez définir plusieurs coefficients pour la même classe'],
    ['   - Chaque ligne représente un coefficient pour un cours dans une classe'],
    ['   - Exemple: Si une classe a 5 cours, vous aurez 5 lignes avec le même ID Classe'],
    [''],
    ['6. EN CAS D\'ERREUR'],
    ['   - "Classe inexistante" : Vérifiez l\'ID de classe dans la liste'],
    ['   - "Cours inexistant" : Vérifiez l\'ID cours dans la liste'],
    ['   - "Coefficient invalide" : Le coefficient doit être un nombre positif'],
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
$instructionsSheet->getStyle('A8')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$instructionsSheet->getStyle('A18')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$instructionsSheet->getStyle('A24')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$instructionsSheet->getStyle('A31')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$instructionsSheet->getStyle('A37')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);

$instructionsSheet->getColumnDimension('A')->setWidth(100);

$spreadsheet->setActiveSheetIndex(0);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Modele_Import_Coefficients.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
