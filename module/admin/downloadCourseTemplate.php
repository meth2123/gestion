<?php
require_once('../../vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Cours');

// En-têtes des colonnes
$headers = [
    'A1' => 'Nom du Cours',
    'B1' => 'ID Classe',
    'C1' => 'Nom Classe',
    'D1' => 'ID Enseignant',
    'E1' => 'Nom Enseignant'
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

$sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

// Ajuster la largeur des colonnes
$sheet->getColumnDimension('A')->setWidth(35);
$sheet->getColumnDimension('B')->setWidth(20);
$sheet->getColumnDimension('C')->setWidth(25);
$sheet->getColumnDimension('D')->setWidth(20);
$sheet->getColumnDimension('E')->setWidth(25);

// Ajouter des exemples de données
$exampleData = [
    ['Mathématiques', 'CLS-001', 'Classe de CI', 'TE-AMA-1234', 'Amadou Diop'],
    ['Français', 'CLS-001', 'Classe de CI', 'TE-FAT-5678', 'Fatou Sall'],
    ['Sciences', 'CLS-002', 'Classe de CP', 'TE-MOU-9012', 'Moussa Kane']
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

$sheet->getStyle('A2:E4')->applyFromArray($dataStyle);

// Ajouter une feuille d'instructions
$instructionsSheet = $spreadsheet->createSheet();
$instructionsSheet->setTitle('Instructions');

$instructions = [
    ['INSTRUCTIONS POUR L\'IMPORTATION DES COURS'],
    [''],
    ['1. FORMAT DES DONNÉES (5 colonnes)'],
    ['   - Nom du Cours: Nom du cours (ex: Mathématiques, Français)'],
    ['   - ID Classe: ID de la classe existante (ex: CLS-001)'],
    ['   - Nom Classe: Nom de la classe (optionnel, pour référence)'],
    ['   - ID Enseignant: ID de l\'enseignant existant (ex: TE-AMA-1234)'],
    ['   - Nom Enseignant: Nom de l\'enseignant (optionnel, pour référence)'],
    [''],
    ['2. COLONNES UTILISÉES PAR LE SYSTÈME'],
    ['   - Seules les colonnes A (Nom du Cours), B (ID Classe) et D (ID Enseignant) sont utilisées'],
    ['   - Les colonnes C (Nom Classe) et E (Nom Enseignant) sont ignorées'],
    ['   - Vous pouvez les remplir pour faciliter la lecture, mais elles ne sont pas obligatoires'],
    [''],
    ['3. POINTS IMPORTANTS'],
    ['   - Les ID Classe doivent correspondre à des classes existantes dans le système'],
    ['   - Les ID Enseignant doivent correspondre à des enseignants existants'],
    ['   - Consultez les listes sur la page d\'importation pour voir les ID disponibles'],
    ['   - Ne modifiez pas les en-têtes des colonnes'],
    ['   - Supprimez les lignes d\'exemple avant l\'importation'],
    [''],
    ['4. EXEMPLES DE DONNÉES VALIDES'],
    ['   Nom du Cours | ID Classe | Nom Classe    | ID Enseignant | Nom Enseignant'],
    ['   Mathématiques | CLS-001   | Classe de CI  | TE-AMA-1234   | Amadou Diop'],
    ['   Français      | CLS-001   | Classe de CI  | TE-FAT-5678   | Fatou Sall'],
    [''],
    ['5. COMMENT OBTENIR LES ID'],
    ['   - Sur la page d\'importation, vous trouverez deux tableaux :'],
    ['     * Classes Disponibles : Liste des ID et noms de classes'],
    ['     * Enseignants Disponibles : Liste des ID et noms d\'enseignants'],
    ['   - Copiez les ID exacts depuis ces tableaux dans les colonnes B et D'],
    [''],
    ['6. EN CAS D\'ERREUR'],
    ['   - "Classe inexistante" : Vérifiez l\'ID de classe dans la colonne B'],
    ['   - "Enseignant inexistant" : Vérifiez l\'ID enseignant dans la colonne D'],
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
$instructionsSheet->getStyle('A10')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$instructionsSheet->getStyle('A14')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$instructionsSheet->getStyle('A21')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$instructionsSheet->getStyle('A26')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$instructionsSheet->getStyle('A32')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);

$instructionsSheet->getColumnDimension('A')->setWidth(100);

$spreadsheet->setActiveSheetIndex(0);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Modele_Import_Cours.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
