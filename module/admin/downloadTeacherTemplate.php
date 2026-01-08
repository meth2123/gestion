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
$sheet->setTitle('Enseignants');

// En-têtes des colonnes
$headers = [
    'A1' => 'Nom',
    'B1' => 'Email',
    'C1' => 'Téléphone',
    'D1' => 'Genre',
    'E1' => 'Date de naissance',
    'F1' => 'Mot de passe',
    'G1' => 'Date d\'embauche',
    'H1' => 'Salaire',
    'I1' => 'Adresse'
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

$sheet->getStyle('A1:I1')->applyFromArray($headerStyle);

// Ajuster la largeur des colonnes
$sheet->getColumnDimension('A')->setWidth(25);
$sheet->getColumnDimension('B')->setWidth(30);
$sheet->getColumnDimension('C')->setWidth(15);
$sheet->getColumnDimension('D')->setWidth(12);
$sheet->getColumnDimension('E')->setWidth(18);
$sheet->getColumnDimension('F')->setWidth(15);
$sheet->getColumnDimension('G')->setWidth(18);
$sheet->getColumnDimension('H')->setWidth(15);
$sheet->getColumnDimension('I')->setWidth(35);

// Ajouter des exemples de données
$exampleData = [
    ['Amadou Diop', 'amadou.diop@school.com', '771234567', 'male', '1985-05-15', 'password123', '2023-09-01', '350000', '123 Rue de Dakar'],
    ['Fatou Sall', 'fatou.sall@school.com', '772345678', 'female', '1990-08-20', 'password456', '2023-09-01', '320000', '456 Avenue Bourguiba'],
    ['Moussa Kane', 'moussa.kane@school.com', '773456789', 'male', '1988-12-10', 'password789', '2024-01-15', '380000', '789 Boulevard de la République']
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

$sheet->getStyle('A2:I4')->applyFromArray($dataStyle);

// Ajouter une feuille d'instructions
$instructionsSheet = $spreadsheet->createSheet();
$instructionsSheet->setTitle('Instructions');

$instructions = [
    ['INSTRUCTIONS POUR L\'IMPORTATION DES ENSEIGNANTS'],
    [''],
    ['1. FORMAT DES DONNÉES'],
    ['   - Nom: Nom complet de l\'enseignant'],
    ['   - Email: Format email valide et unique (ex: nom@email.com)'],
    ['   - Téléphone: Numéros uniquement (ex: 771234567)'],
    ['   - Genre: male ou female (en minuscules)'],
    ['   - Date de naissance: Format AAAA-MM-JJ (ex: 1985-05-15)'],
    ['   - Mot de passe: Minimum 6 caractères'],
    ['   - Date d\'embauche: Format AAAA-MM-JJ (ex: 2023-09-01)'],
    ['   - Salaire: Nombre entier en FCFA (ex: 350000)'],
    ['   - Adresse: Adresse complète'],
    [''],
    ['2. POINTS IMPORTANTS'],
    ['   - L\'ID enseignant est généré automatiquement (format: TE-XXX-9999)'],
    ['   - Les emails doivent être uniques'],
    ['   - Le genre doit être exactement "male" ou "female" (en minuscules)'],
    ['   - Les dates doivent être au format AAAA-MM-JJ'],
    ['   - Le salaire doit être un nombre sans espaces ni symboles'],
    ['   - Ne modifiez pas les en-têtes des colonnes'],
    ['   - Supprimez les lignes d\'exemple avant l\'importation ou modifiez-les'],
    [''],
    ['3. EXEMPLES DE DONNÉES VALIDES'],
    ['   - Genre: male, female'],
    ['   - Date: 1985-05-15, 1990-12-25'],
    ['   - Salaire: 250000, 350000, 500000'],
    ['   - Email: amadou.diop@school.com'],
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
$instructionsSheet->getStyle('A23')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$instructionsSheet->getStyle('A29')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);

$instructionsSheet->getColumnDimension('A')->setWidth(90);

// Activer la première feuille
$spreadsheet->setActiveSheetIndex(0);

// Générer le fichier et le télécharger
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Modele_Import_Enseignants.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
