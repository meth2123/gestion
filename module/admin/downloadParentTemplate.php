<?php
require_once('../../vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Créer un nouveau fichier Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Définir le titre de la feuille
$sheet->setTitle('Parents');

// En-têtes des colonnes
$headers = [
    'A1' => 'Mot de passe',
    'B1' => 'Nom du père',
    'C1' => 'Nom de la mère',
    'D1' => 'Téléphone du père',
    'E1' => 'Téléphone de la mère',
    'F1' => 'Adresse'
];

// Appliquer les en-têtes
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

$sheet->getStyle('A1:F1')->applyFromArray($headerStyle);

// Ajuster la largeur des colonnes
$sheet->getColumnDimension('A')->setWidth(18);
$sheet->getColumnDimension('B')->setWidth(25);
$sheet->getColumnDimension('C')->setWidth(25);
$sheet->getColumnDimension('D')->setWidth(18);
$sheet->getColumnDimension('E')->setWidth(18);
$sheet->getColumnDimension('F')->setWidth(40);

// Ajouter des exemples de données
$exampleData = [
    ['password123', 'Mamadou Diop', 'Fatou Sall', '771234567', '772345678', '123 Rue de Dakar, Sénégal'],
    ['password456', 'Abdou Ndiaye', 'Aissatou Kane', '773456789', '774567890', '456 Avenue Bourguiba, Dakar'],
    ['password789', 'Ousmane Diouf', 'Mariama Sy', '775678901', '776789012', '789 Boulevard de la République']
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

$sheet->getStyle('A2:F4')->applyFromArray($dataStyle);

// Ajouter une feuille d'instructions
$instructionsSheet = $spreadsheet->createSheet();
$instructionsSheet->setTitle('Instructions');

$instructions = [
    ['INSTRUCTIONS POUR L\'IMPORTATION DES PARENTS'],
    [''],
    ['1. FORMAT DES DONNÉES'],
    ['   - Mot de passe: Minimum 6 caractères'],
    ['   - Nom du père: Nom complet du père (obligatoire)'],
    ['   - Nom de la mère: Nom complet de la mère (obligatoire)'],
    ['   - Téléphone du père: Numéros uniquement (obligatoire)'],
    ['   - Téléphone de la mère: Numéros uniquement (optionnel)'],
    ['   - Adresse: Adresse complète de la famille'],
    [''],
    ['2. POINTS IMPORTANTS'],
    ['   - L\'ID parent est généré automatiquement basé sur le nom de la mère'],
    ['   - Format de l\'ID: XXX-PA001 (ex: SAL-PA001 pour Sall)'],
    ['   - Le nom de la mère est OBLIGATOIRE pour générer l\'ID'],
    ['   - Le mot de passe, nom du père et téléphone du père sont obligatoires'],
    ['   - Ne modifiez pas les en-têtes des colonnes'],
    ['   - Supprimez les lignes d\'exemple avant l\'importation'],
    [''],
    ['3. EXEMPLES DE DONNÉES VALIDES'],
    ['   - Mot de passe: password123, motdepasse456'],
    ['   - Nom du père: Mamadou Diop, Abdou Ndiaye'],
    ['   - Nom de la mère: Fatou Sall, Aissatou Kane'],
    ['   - Téléphone: 771234567, 773456789'],
    ['   - Adresse: 123 Rue de Dakar, Sénégal'],
    [''],
    ['4. GÉNÉRATION DE L\'ID'],
    ['   - Si la mère s\'appelle "Fatou Sall", l\'ID sera SAL-PA001, SAL-PA002, etc.'],
    ['   - Si la mère s\'appelle "Aissatou Kane", l\'ID sera KAN-PA001, KAN-PA002, etc.'],
    ['   - Les 3 premières lettres du nom de la mère sont utilisées'],
    [''],
    ['5. EN CAS D\'ERREUR'],
    ['   - Le système vous indiquera les lignes qui n\'ont pas pu être importées'],
    ['   - Corrigez les erreurs et réessayez l\'importation'],
    ['   - Vous pouvez cocher "Ignorer les lignes avec erreurs" pour continuer']
];

$row = 1;
foreach ($instructions as $instruction) {
    $instructionsSheet->setCellValue('A' . $row, $instruction[0]);
    $row++;
}

// Style pour le titre des instructions
$instructionsSheet->getStyle('A1')->applyFromArray([
    'font' => [
        'bold' => true,
        'size' => 14,
        'color' => ['rgb' => '4472C4']
    ]
]);

// Style pour les sections
$instructionsSheet->getStyle('A3')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$instructionsSheet->getStyle('A11')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$instructionsSheet->getStyle('A19')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$instructionsSheet->getStyle('A26')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$instructionsSheet->getStyle('A31')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);

$instructionsSheet->getColumnDimension('A')->setWidth(100);

// Activer la première feuille
$spreadsheet->setActiveSheetIndex(0);

// Générer le fichier et le télécharger
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Modele_Import_Parents.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
