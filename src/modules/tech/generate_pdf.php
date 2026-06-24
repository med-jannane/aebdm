<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';
require_once __DIR__ . '/../../libs/fpdf.php'; // Path to user's FPDF library

check_role(['tech', 'admin', 'tac']);

if (!isset($_GET['id'])) {
    die("ID Intervention manquant.");
}

$intervention_id = $_GET['id'];

// Fetch Data
$sql = "SELECT i.*, 
               t.ID_TICKET as ticket_id, t.COMMENT as ticket_desc, '-' as contact_sur_place, t.DATE as cree_le,
               c.Nom as client_nom, c.Adresse as client_adresse, c.Ville as client_ville, c.TEL as client_tel, c.Email as client_email,
               s.Nom as site_nom, s.Id_Site as code_site,
               u.nom_complet as tech_nom
        FROM Interventions i
        JOIN TICKET t ON i.ticket_id = t.ID_TICKET
        LEFT JOIN SAV_Clients c ON t.ID_CLIENT = c.ID_Client
        LEFT JOIN SAV_Sites s ON t.ID_SITE = s.Id_Site
        JOIN Users u ON i.tech_id = u.id
        WHERE i.id = ?";

$stmt = sqlsrv_query($conn, $sql, [$intervention_id]);
if ($stmt === false) {
    error_log('[TECH_GENERATE_PDF_QUERY] ' . db_last_error_message());
    die('Erreur interne lors du chargement du rapport.');
}
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$data) die("Intervention introuvable.");

// Fetch Products Used
$sqlProds = "SELECT p.nom, p.id, ip.quantite 
             FROM Intervention_Produits ip
             JOIN Produits p ON ip.produit_id = p.id
             WHERE ip.intervention_id = ?";
$stmtProds = sqlsrv_query($conn, $sqlProds, [$intervention_id]);
$products = [];
if ($stmtProds) {
    while($row = sqlsrv_fetch_array($stmtProds, SQLSRV_FETCH_ASSOC)) {
        $products[] = $row;
    }
}

class PDF extends FPDF {
    function Header() {
        // Logo (Placeholder)
        $this->SetFont('Arial','B',15);
        $this->Cell(40, 10, 'A@BDM', 1, 0, 'C'); // Logo placeholder

        // Center Info
        $this->SetFont('Arial','B',10);
        $this->Cell(100, 5, 'WELCOME CENTER : 089 . 010 . 1000', 0, 0, 'C');
        
        // Right IDs
        $this->SetFont('Arial','',9);
        $this->Cell(50, 5, 'APPEL N : ' . $GLOBALS['data']['ticket_id'], 0, 1, 'R');
        
        // Second line center
        $this->SetX(50);
        $this->SetFont('Arial','B',9);
        $this->Cell(30, 6, 'INTERVENTION', 1, 0, 'C');
        $this->Cell(5, 6, '', 0, 0);
        $this->Cell(20, 6, 'TIR', 1, 0, 'C'); 
        
        // Second line right
        $this->SetX(150);
        $this->SetFont('Arial','',9);
        $this->Cell(50, 6, 'FEUILLE N : ' . $GLOBALS['data']['id'], 0, 1, 'R');
        
        $this->Ln(10);
    }

    function Footer() {
        $this->SetY(-30);
        $this->SetFont('Arial','I',8);
        $this->Cell(0, 10, 'Important : Tous les champs doivent etre dument renseignes.', 0, 1, 'L');
        
        // Bottom Legal
        $this->SetY(-15);
        $this->SetFont('Arial','',7);
        $this->Cell(0, 5, utf8_decode('AEBDM S.A. 20, Rue Théophile Gauthier BP 14744 - 20 060 Casablanca - Web : http://www.aebdm.ma'), 0, 1, 'C');
        $this->Cell(0, 5, utf8_decode('Tél: (+212) 522 499 999 - Fax: (+212) 522 499 926 - R.C. Casa : 113 035 - Patente : 355 50 178 - CNSS: 6356457 - ICE: 001529720000063'), 0, 1, 'C');
    }
}

// Create PDF
$pdf = new PDF();
$pdf->AddPage();

// --- SECTION ACCUEIL ---
$pdf->SetFillColor(230,230,230);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(0, 8, 'ACCUEIL', 1, 1, 'L', true);

$pdf->SetFont('Arial','',9);
$yStart = $pdf->GetY();
// Left Column
$pdf->Cell(40, 6, 'NOM DU CLIENT :', 0, 0);
$pdf->Cell(80, 6, utf8_decode($data['client_nom']), 'B', 1);

$pdf->Cell(40, 6, 'INTERFACE CLIENT :', 0, 0);
$pdf->Cell(80, 6, utf8_decode($data['contact_sur_place']), 'B', 1);

$pdf->Cell(40, 6, 'ADRESSE :', 0, 0);
$pdf->Cell(80, 6, utf8_decode($data['client_adresse']), 'B', 1); 
$pdf->Cell(40, 6, 'VILLE :', 0, 0);
$pdf->Cell(80, 6, utf8_decode($data['client_ville']), 'B', 1);

$pdf->Cell(40, 6, 'E-MAIL :', 0, 0);
$pdf->Cell(80, 6, utf8_decode($data['client_email']), 'B', 1);

$pdf->Cell(40, 6, 'PRODUIT :', 0, 0);
$pdf->Cell(80, 6, '', 'B', 1); 

$pdf->Cell(40, 6, utf8_decode('INGÉNIEUR INTERVENANT :'), 0, 0);
$pdf->Cell(80, 6, utf8_decode($data['tech_nom']), 'B', 1);

// Right Column (Dates Table)
$xRight = 135;
$pdf->SetXY($xRight, $yStart);
$pdf->SetFont('Arial','',8);
$pdf->Cell(12, 5, 'DATE :', 0, 0);
$pdf->Cell(40, 5, $data['date_intervention'] ? $data['date_intervention']->format('d/m/Y') : date('d/m/Y'), 1, 1);

$pdf->SetX($xRight);
$pdf->Cell(15, 5, '', 0, 0);
$pdf->Cell(15, 5, 'MATIN', 0, 0, 'C');
$pdf->Cell(15, 5, 'SOIR', 0, 1, 'C');

// H.A. (Heure Arrivée)
$pdf->SetX($xRight);
$pdf->Cell(15, 5, 'H.A.', 0, 0);
$ha_m = ($data['heure_arrivee_matin'] instanceof DateTime) ? $data['heure_arrivee_matin']->format('H:i') : ($data['heure_arrivee_matin'] ?? '');
$ha_s = ($data['heure_arrivee_soir'] instanceof DateTime) ? $data['heure_arrivee_soir']->format('H:i') : ($data['heure_arrivee_soir'] ?? '');
$pdf->Cell(15, 5, $ha_m, 1, 0, 'C'); 
$pdf->Cell(15, 5, $ha_s, 1, 1, 'C'); 

// H.D. (Heure Départ)
$pdf->SetX($xRight);
$pdf->Cell(15, 5, 'H.D.', 0, 0);
$hd_m = ($data['heure_depart_matin'] instanceof DateTime) ? $data['heure_depart_matin']->format('H:i') : ($data['heure_depart_matin'] ?? '');
$hd_s = ($data['heure_depart_soir'] instanceof DateTime) ? $data['heure_depart_soir']->format('H:i') : ($data['heure_depart_soir'] ?? '');
$pdf->Cell(15, 5, $hd_m, 1, 0, 'C'); 
$pdf->Cell(15, 5, $hd_s, 1, 1, 'C'); 

// TRS. (Trajet)
$pdf->SetX($xRight);
$pdf->Cell(15, 5, 'TRS.', 0, 0);
// Duration is likely INT (minutes) or TIME. safe to check DateTime.
$tr_m = ($data['duree_trajet_matin'] instanceof DateTime) ? $data['duree_trajet_matin']->format('H:i') : ($data['duree_trajet_matin'] ?? '');
$tr_s = ($data['duree_trajet_soir'] instanceof DateTime) ? $data['duree_trajet_soir']->format('H:i') : ($data['duree_trajet_soir'] ?? '');
$pdf->Cell(15, 5, $tr_m, 1, 0, 'C'); 
$pdf->Cell(15, 5, $tr_s, 1, 1, 'C'); 

$pdf->SetY($yStart + 45); 

// --- SECTION DIAGNOSTIC ---
$pdf->SetFont('Arial','B',10);
$pdf->Cell(0, 8, 'DIAGNOSTIC', 1, 1, 'L', true);

$pdf->SetFont('Arial','',9);
$pdf->Cell(40, 6, utf8_decode('DÉFAUT CONSTATÉ :'), 0, 0);
$pdf->MultiCell(0, 6, utf8_decode($data['ticket_desc']), 'B', 'L');

$pdf->Cell(40, 6, utf8_decode('TRAVAUX DEMANDÉS :'), 0, 0);
$pdf->MultiCell(0, 6, '', 'B', 'L'); // Ticket desc implies requested work usually

$pdf->Cell(45, 6, utf8_decode('TRAVAUX RECOMMANDÉS :'), 0, 0);
$pdf->MultiCell(0, 6, utf8_decode($data['travaux_recommandes'] ?? ''), 'B', 'L');

$pdf->Ln(5);

// --- SECTION INTERVENTION ---
$pdf->SetFont('Arial','B',10);
$pdf->Cell(0, 8, 'INTERVENTION', 1, 1, 'L', true);

$pdf->SetFont('Arial','BU',9);
$pdf->Cell(0, 6, utf8_decode('TRAVAUX EFFECTUÉS :'), 0, 1);

$pdf->SetFont('Arial','',9);
$rapport = $data['rapport'] ? $data['rapport'] : "Aucun rapport saisi.";
$pdf->MultiCell(0, 6, utf8_decode($rapport), 0, 'L');
$pdf->Ln(2);

// Blank lines if empty
if(empty($data['rapport'])) {
    $pdf->Cell(0, 6, '', 'B', 1);
    $pdf->Cell(0, 6, '', 'B', 1);
}

$pdf->Ln(5);

// --- MATERIEL REMPLACE ---
$ySig = $pdf->GetY();
$pdf->SetFont('Arial','',8);
$pdf->Text($pdf->GetX(), $pdf->GetY()+4, 'MATERIEL REMPLACE :');
$pdf->SetY($pdf->GetY()+5);

// Table header
$pdf->Cell(30, 5, 'REF', 1, 0, 'C');
$pdf->Cell(80, 5, 'DESIGNATION', 1, 0, 'C');
$pdf->Cell(10, 5, 'NB', 1, 1, 'C');

// Rows
$rows_printed = 0;
foreach($products as $prod) {
    if ($rows_printed >= 4) break; // Limit rows
    $pdf->Cell(30, 6, 'PROD-'.$prod['id'], 1, 0);
    $pdf->Cell(80, 6, utf8_decode($prod['nom']), 1, 0);
    $pdf->Cell(10, 6, $prod['quantite'], 1, 1, 'C');
    $rows_printed++;
}
// Empty rows filler
for($i=$rows_printed; $i<4; $i++) {
    $pdf->Cell(30, 6, '', 1, 0);
    $pdf->Cell(80, 6, '', 1, 0);
    $pdf->Cell(10, 6, '', 1, 1);
}

// Comments
$pdf->Ln(5);
$pdf->Text($pdf->GetX(), $pdf->GetY(), 'COMMENTAIRE CLIENT :');
$pdf->SetY($pdf->GetY()+2);
$pdf->MultiCell(120, 5, utf8_decode($data['commentaire_client'] ?? ''), 0, 'L');
$pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetX()+120, $pdf->GetY()); // Underline last line

// --- SIGNATURES ---
$xSigRight = 140;
$pdf->SetXY($xSigRight, $ySig);

// Technician
$pdf->SetFillColor(255,255,255);
$pdf->Cell(50, 40, '', 1, 1); // Box
$pdf->SetXY($xSigRight, $ySig);
$pdf->SetFont('Arial','B',8);
$pdf->Cell(50, 5, 'POUR AEBDM :', 0, 1, 'L');
$pdf->SetX($xSigRight);
$pdf->SetFont('Arial','',8);
$pdf->Cell(50, 5, 'Nom : ' . utf8_decode($data['tech_nom']), 0, 1, 'L');
$pdf->SetX($xSigRight);
$pdf->Cell(50, 5, 'Signature :', 0, 1, 'L');

// Client
$pdf->SetXY($xSigRight, $ySig + 45);
$pdf->Cell(50, 40, '', 1, 1); // Box
$pdf->SetXY($xSigRight, $ySig + 45);
$pdf->SetFont('Arial','B',8);
$pdf->Cell(50, 5, 'POUR LE CLIENT :', 0, 1, 'L');
$pdf->SetX($xSigRight);
$pdf->SetFont('Arial','',8);
$pdf->Cell(50, 5, 'Nom : ' . utf8_decode($data['nom_signataire_client'] ?? ''), 0, 1, 'L');
$pdf->SetX($xSigRight);
$pdf->Cell(50, 5, 'Signature :', 0, 1, 'L');

$pdf->Output();
?>
