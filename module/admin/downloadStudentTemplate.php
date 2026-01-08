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
$sheet->setTitle('Étudiants');

// En-têtes des colonnes
$headers = [
    'A1' => 'ID Étudiant',
    'B1' => 'Nom Complet',
    'C1' => 'Mot de passe',
    'D1' => 'Téléphone',
    'E1' => 'Email',
    'F1' => 'Genre',
    'G1' => 'Date de naissance',
    'H1' => 'Date d\'admission',
    'I1' => 'Adresse',
    'J1' => 'ID Parent',
    'K1' => 'Classe',
    'L1' => 'Photo'
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

$sheet->getStyle('A1:J1')->applyFromArray($headerStyle);

// Ajuster la largeur des colonnes
$sheet->getColumnDimension('A')->setWidth(15);
$sheet->getColumnDimension('B')->setWidth(25);
$sheet->getColumnDimension('C')->setWidth(15);
$sheet->getColumnDimension('D')->setWidth(15);
$sheet->getColumnDimension('E')->setWidth(25);
$sheet->getColumnDimension('F')->setWidth(12);
$sheet->getColumnDimension('G')->setWidth(18);
$sheet->getColumnDimension('H')->setWidth(30);
$sheet->getColumnDimension('I')->setWidth(15);
$sheet->getColumnDimension('J')->setWidth(15);

// Ajouter des exemples de données
$exampleData = [
    ['STU001', 'Jean Dupont', 'password123', '771234567', 'jean.dupont@email.com', 'Male', '2010-05-15', '123 Rue de Dakar', 'PAR001', '1'],
    ['STU002', 'Marie Diallo', 'password456', '772345678', 'marie.diallo@email.com', 'Female', '2011-08-20', '456 Avenue Bourguiba', 'PAR002', '2'],
    ['STU003', 'Amadou Sow', 'password789', '773456789', 'amadou.sow@email.com', 'Male', '2009-12-10', '789 Boulevard de la République', 'PAR003', '1']
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

$sheet->getStyle('A2:J4')->applyFromArray($dataStyle);

// Ajouter une feuille d'instructions
$instructionsSheet = $spreadsheet->createSheet();
$instructionsSheet->setTitle('Instructions');

$instructions = [
    ['INSTRUCTIONS POUR L\'IMPORTATION DES ÉTUDIANTS'],
    [''],
    ['1. FORMAT DES DONNÉES'],
    ['   - ID Étudiant: Unique, lettres et chiffres uniquement (ex: STU001)'],
    ['   - Nom Complet: Nom complet de l\'étudiant'],
    ['   - Mot de passe: Minimum 6 caractères'],
    ['   - Téléphone: Numéros uniquement (ex: 771234567)'],
    ['   - Email: Format email valide (ex: nom@email.com)'],
    ['   - Genre: Male ou Female (respecter la casse)'],
    ['   - Date de naissance: Format AAAA-MM-JJ (ex: 2010-05-15)'],
    ['   - Adresse: Adresse complète'],
    ['   - ID Parent: Doit correspondre à un parent existant dans le système'],
    ['   - ID Classe: Doit correspondre à une classe existante dans le système'],
    [''],
    ['2. POINTS IMPORTANTS'],
    ['   - Les ID étudiants doivent être uniques'],
    ['   - Vérifiez que les ID Parent existent avant l\'importation'],
    ['   - Vérifiez que les ID Classe existent avant l\'importation'],
    ['   - Ne modifiez pas les en-têtes des colonnes'],
    ['   - Supprimez les lignes d\'exemple avant l\'importation ou modifiez-les'],
    [''],
    ['3. COMMENT OBTENIR LES ID PARENT ET CLASSE'],
    ['   - Consultez la page d\'importation pour voir la liste des parents et classes disponibles'],
    ['   - Utilisez les ID exacts affichés dans ces listes'],
    [''],
    ['4. EN CAS D\'ERREUR'],
    ['   - Le système vous indiquera les lignes qui n\'ont pas pu être importées'],
    ['   - Corrigez les erreurs et réessayez l\'importation'],
    ['   - Vous pouvez cocher "Ignorer les lignes avec erreurs" pour continuer malgré les erreurs']
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
$instructionsSheet->getStyle('A14')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$instructionsSheet->getStyle('A21')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$instructionsSheet->getStyle('A25')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);

$instructionsSheet->getColumnDimension('A')->setWidth(80);

// Activer la première feuille
$spreadsheet->setActiveSheetIndex(0);

// Générer le fichier et le télécharger
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Modele_Import_Etudiants.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
