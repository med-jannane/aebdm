<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

// Export endpoints shouldn't output any HTML before headers
check_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Méthode non autorisée.");
}

$date_debut = $_POST['date_debut'] ?? '';
$date_fin = $_POST['date_fin'] ?? '';
$format = $_POST['format'] ?? 'csv';

if (empty($date_debut) || empty($date_fin)) {
    die("Les dates sont obligatoires.");
}

// Ensure end date is inclusive of the whole day
$date_fin_sql = $date_fin . ' 23:59:59';
$date_debut_sql = $date_debut . ' 00:00:00';

// Main Complex Query to fetch complete lifecycle
$sql = "SELECT 
            t.ID_TICKET as TicketID,
            t.OBJET as Sujet,
            t.DATE as DateCreation,
            t.ETAT as StatutTicket,
            t.PRIORITE as Priorite,
            c.Nom as Client,
            c.ID_Client as CodeClient,
            s.Nom as Site,
            t.MESSAGE_DISPATCH as DiagnosticTAC,
            t.DATE as DateTAC,
            i.id as InterventionID,
            u.nom_complet as Technicien,
            i.date_planifiee as DatePlanifiee,
            i.date_intervention as DateIntervention,
            i.statut as StatutIntervention,
            i.rapport as RapportTech
        FROM TICKET t
        JOIN SAV_Clients c ON t.ID_CLIENT = c.ID_Client
        LEFT JOIN SAV_Sites s ON t.ID_SITE = s.Id_Site
        LEFT JOIN Interventions i ON t.ID_TICKET = i.ticket_id
        LEFT JOIN Users u ON i.tech_id = u.id
        WHERE t.DATE >= ? AND t.DATE <= ?
        ORDER BY t.DATE DESC";

$stmt = sqlsrv_query($conn, $sql, [$date_debut_sql, $date_fin_sql]);

if ($stmt === false) {
    error_log('[ADMIN_EXPORT_STATS_QUERY] ' . db_last_error_message());
    die("Erreur interne lors de l'export des statistiques.");
}

$results = [];
while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $results[] = $row;
}

if ($format === 'csv') {
    // Generate CSV (Excel Compatible UTF-8 BOM)
    $filename = "Export_SAV_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '";');
    
    $output = fopen('php://output', 'w');
    // Add UTF-8 BOM for Excel Excel readability
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    fputcsv($output, [
        'ID Ticket', 'Date Creation', 'Statut Ticket', 'Priorite', 'Sujet', 
        'Code Client', 'Client', 'Site', 
        'Diag. TAC', 'Date Trait. TAC', 
        'ID Interv.', 'Technicien', 'Date Planifiee', 'Date Tech.', 'Statut Interv.', 'Rapport Tech'
    ], ';'); // Use semi-colon for French Excel
    
    foreach ($results as $row) {
        // Format dates
        $dCree = $row['DateCreation'] ? $row['DateCreation']->format('Y-m-d H:i:s') : '';
        $dTAC = $row['DateTAC'] ? $row['DateTAC']->format('Y-m-d H:i:s') : '';
        $dPla = $row['DatePlanifiee'] ? $row['DatePlanifiee']->format('Y-m-d H:i:s') : '';
        $dInt = $row['DateIntervention'] ? $row['DateIntervention']->format('Y-m-d H:i:s') : '';
        
        // Clean text (remove newlines in CSV cells to avoid breaking layout)
        $cleanDiag = str_replace(array("\r", "\n"), " ", $row['DiagnosticTAC'] ?? '');
        $cleanRapp = str_replace(array("\r", "\n"), " ", $row['RapportTech'] ?? '');
        
        fputcsv($output, [
            $row['TicketID'], $dCree, $row['StatutTicket'], strtoupper($row['Priorite']), $row['Sujet'],
            $row['CodeClient'], $row['Client'], $row['Site'],
            $cleanDiag, $dTAC,
            $row['InterventionID'], $row['Technicien'], $dPla, $dInt, $row['StatutIntervention'], $cleanRapp
        ], ';');
    }
    fclose($output);
    exit;
} elseif ($format === 'pdf') {
    // Generate PDF Report using FPDF
    require_once __DIR__ . '/../../libs/fpdf.php';
    
    class PDFReport extends FPDF {
        function Header() {
            $this->SetFont('Arial', 'B', 15);
            $this->Cell(0, 10, 'Rapport Statistique SAV', 0, 1, 'C');
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 5, utf8_decode('Période du : ' . $_POST['date_debut'] . ' au ' . $_POST['date_fin']), 0, 1, 'C');
            $this->Ln(10);
        }
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb} - Edite le ' . date('d/m/Y'), 0, 0, 'C');
        }
    }
    
    $pdf = new PDFReport('L', 'mm', 'A4'); // Landscape for tables
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 8);
    
    // Resume Stats first
    $total_tickets = count($results);
    $inter_count = 0;
    foreach($results as $r) { if($r['InterventionID']) $inter_count++; }
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 10, utf8_decode("Résumé : $total_tickets tickets créés, $inter_count interventions associées."), 0, 1);
    $pdf->Ln(5);
    
    // Table Header
    $pdf->SetFillColor(200, 200, 200);
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->Cell(15, 6, 'Ticket #', 1, 0, 'C', true);
    $pdf->Cell(25, 6, 'Date Crea.', 1, 0, 'C', true);
    $pdf->Cell(45, 6, 'Client', 1, 0, 'L', true);
    $pdf->Cell(25, 6, 'Statut', 1, 0, 'C', true);
    $pdf->Cell(35, 6, 'Tech Assigne', 1, 0, 'L', true);
    $pdf->Cell(25, 6, 'Statut Interv.', 1, 0, 'C', true);
    $pdf->Cell(105, 6, 'Rapport / Diagnostic', 1, 1, 'L', true);
    
    $pdf->SetFont('Arial', '', 7);
    foreach ($results as $row) {
        $dCree = $row['DateCreation'] ? $row['DateCreation']->format('Y-m-d') : '';
        
        $diag = substr(str_replace(array("\r", "\n"), ' ', $row['DiagnosticTAC'] ?? ''), 0, 50);
        $rapp = substr(str_replace(array("\r", "\n"), ' ', $row['RapportTech'] ?? ''), 0, 50);
        $text = "Diag: " . ($diag?:'-') . " | Rap: " . ($rapp?:'-');
        
        // Truncating text for single line
        $client = substr(utf8_decode($row['Client']), 0, 25);
        $tech = substr(utf8_decode($row['Technicien'] ?? '-'), 0, 20);
        $textLimit = substr(utf8_decode($text), 0, 80);
        
        $pdf->Cell(15, 6, $row['TicketID'], 1, 0, 'C');
        $pdf->Cell(25, 6, $dCree, 1, 0, 'C');
        $pdf->Cell(45, 6, $client, 1, 0, 'L');
        $pdf->Cell(25, 6, $row['StatutTicket'], 1, 0, 'C');
        $pdf->Cell(35, 6, $tech, 1, 0, 'L');
        $pdf->Cell(25, 6, $row['StatutIntervention'] ?? '-', 1, 0, 'C');
        $pdf->Cell(105, 6, $textLimit, 1, 1, 'L');
    }
    
    $pdf->Output('I', "Export_SAV_" . date('Ymd_His') . ".pdf");
    exit;
} else {
    die("Format non reconnu.");
}
