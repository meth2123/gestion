<?php
require_once('../../vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Personnel');

// En-têtes des colonnes
$headers = [
    'A1' => 'Nom',
    'B1' => 'Email',
    'C1' => 'Téléphone',
    'D1' => 'Adresse',
    'E1' => 'Genre',
    'F1' => 'Date de naissance',
    'G1' => 'Salaire',
    'H1' => 'Mot de passe'
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

$sheet->getStyle('A1:H1')->applyFromArray($headerStyle);

// Ajuster la largeur des colonnes
$sheet->getColumnDimension('A')->setWidth(25);
$sheet->getColumnDimension('B')->setWidth(30);
$sheet->getColumnDimension('C')->setWidth(15);
$sheet->getColumnDimension('D')->setWidth(35);
$sheet->getColumnDimension('E')->setWidth(12);
$sheet->getColumnDimension('F')->setWidth(18);
$sheet->getColumnDimension('G')->setWidth(15);
$sheet->getColumnDimension('H')->setWidth(15);

// Ajouter des exemples de données
$exampleData = [
    ['Ibrahima Sarr', 'ibrahima.sarr@school.com', '771234567', '123 Rue de Dakar', 'male', '1980-05-15', '200000', 'password123'],
    ['Aminata Diallo', 'aminata.diallo@school.com', '772345678', '456 Avenue Bourguiba', 'female', '1985-08-20', '180000', 'password456'],
    ['Cheikh Ndiaye', 'cheikh.ndiaye@school.com', '773456789', '789 Boulevard République', 'male', '1982-12-10', '220000', 'password789']
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

$sheet->getStyle('A2:H4')->applyFromArray($dataStyle);

// Ajouter une feuille d'instructions
$instructionsSheet = $spreadsheet->createSheet();
$instructionsSheet->setTitle('Instructions');

$instructions = [
    ['INSTRUCTIONS POUR L\'IMPORTATION DU PERSONNEL'],
    [''],
    ['1. FORMAT DES DONNÉES (8 colonnes)'],
    ['   - Nom: Nom complet du membre du personnel'],
    ['   - Email: Format email valide et unique (ex: nom@email.com)'],
    ['   - Téléphone: Numéros uniquement (ex: 771234567)'],
    ['   - Adresse: Adresse complète (optionnel)'],
    ['   - Genre: male ou female (en minuscules)'],
    ['   - Date de naissance: Format AAAA-MM-JJ (ex: 1980-05-15)'],
    ['   - Salaire: Nombre entier en FCFA (ex: 200000)'],
    ['   - Mot de passe: Minimum 6 caractères'],
    [''],
    ['2. GÉNÉRATION AUTOMATIQUE DE L\'ID'],
    ['   - L\'ID du personnel est généré automatiquement'],
    ['   - Format: STF001XXX (ex: STF001ABC, STF002XYZ)'],
    ['   - STF = Préfixe pour Staff'],
    ['   - 001 = Numéro séquentiel'],
    ['   - XXX = 3 lettres aléatoires'],
    ['   - Cet ID servira d\'identifiant de connexion'],
    [''],
    ['3. POINTS IMPORTANTS'],
    ['   - Les emails doivent être uniques'],
    ['   - Le genre doit être exactement "male" ou "female" (en minuscules)'],
    ['   - Les dates doivent être au format AAAA-MM-JJ'],
    ['   - Le salaire doit être un nombre sans espaces ni symboles'],
    ['   - L\'adresse est le seul champ optionnel'],
    ['   - La date d\'embauche est automatiquement la date du jour'],
    ['   - Ne modifiez pas les en-têtes des colonnes'],
    ['   - Supprimez les lignes d\'exemple avant l\'importation'],
    [''],
    ['4. EXEMPLES DE DONNÉES VALIDES'],
    ['   Nom            | Email                    | Téléphone | Adresse        | Genre  | Date naissance | Salaire | Mot de passe'],
    ['   Ibrahima Sarr  | ibrahima.sarr@school.com | 771234567 | 123 Rue Dakar  | male   | 1980-05-15     | 200000  | pass123'],
    ['   Aminata Diallo | aminata.diallo@school.com| 772345678 | 456 Ave Bourg  | female | 1985-08-20     | 180000  | pass456'],
    [''],
    ['5. EN CAS D\'ERREUR'],
    ['   - "Email déjà existant" : Utilisez un email unique différent'],
    ['   - "Format d\'email invalide" : Vérifiez le format (nom@domaine.com)'],
    ['   - "Le salaire doit être un nombre" : Utilisez uniquement des chiffres'],
    ['   - "Format de date invalide" : Utilisez le format AAAA-MM-JJ'],
    ['   - Erreur de genre : Utilisez exactement "male" ou "female"'],
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
$instructionsSheet->getStyle('A13')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$instructionsSheet->getStyle('A22')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$instructionsSheet->getStyle('A32')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$instructionsSheet->getStyle('A37')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);

$instructionsSheet->getColumnDimension('A')->setWidth(120);

$spreadsheet->setActiveSheetIndex(0);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Modele_Import_Personnel.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
