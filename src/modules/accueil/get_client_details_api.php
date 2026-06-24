<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

header('Content-Type: application/json');

if (!isset($_GET['client_id'])) {
    echo json_encode([]);
    exit;
}

$client_id = $_GET['client_id'];

// Get Sites
$sites_sql = "SELECT Id_Site as id, Nom as nom, Ville as ville, Adresse as adresse, '' as contact_nom, Tel as contact_tel FROM SAV_Sites WHERE Id_Client = ? ORDER BY Nom ASC";
$sites_res = query($sites_sql, [$client_id]);
$sites = [];
while ($row = sqlsrv_fetch_array($sites_res, SQLSRV_FETCH_ASSOC)) {
    $sites[] = $row;
}

// Get Active Contrats (robuste import): liaison par ID_CLIENT ou Code_Client,
// et filtre actif tolérant les statuts/espaces et les dates sans heure.
$contrats_sql = "SELECT ID_CONTRAT as id, CODE_CONTRAT as numero_contrat, TYPE as categorie_contrat, Date_Fin as date_fin, Date_Debut as date_debut, ETAT as etat, Montant_Contrat as montant 
    FROM CONTRAT 
    WHERE (
        LTRIM(RTRIM(ISNULL(ID_CLIENT, ''))) = LTRIM(RTRIM(?))
        OR LTRIM(RTRIM(ISNULL(Code_Client, ''))) = LTRIM(RTRIM(?))
    )
    AND (
        Date_Fin IS NULL
        OR CAST(Date_Fin AS DATE) >= CAST(GETDATE() AS DATE)
        OR UPPER(LTRIM(RTRIM(ISNULL(ETAT, '')))) IN ('ACTIF', 'EN_ATTENTE_SIGNATURE', 'RENOUVELLEMENT')
    )
    ORDER BY
        CASE WHEN Date_Fin IS NULL THEN 1 ELSE 0 END,
        Date_Fin DESC";
$contrats_res = query($contrats_sql, [$client_id, $client_id]);
$contrats = [];
while ($row = sqlsrv_fetch_array($contrats_res, SQLSRV_FETCH_ASSOC)) {
    // Format dates for JS
    if ($row['date_fin'] instanceof DateTime) {
        $row['date_fin'] = $row['date_fin']->format('d/m/Y');
    } else {
        $row['date_fin'] = 'Non définie';
    }
    if ($row['date_debut'] instanceof DateTime) {
        $row['date_debut'] = $row['date_debut']->format('d/m/Y');
    } else {
        $row['date_debut'] = '';
    }
    $contrats[] = $row;
}

// Get Client Info (for contact source defaults etc)
$client_sql = "SELECT TEL as telephone, Email as email, Nom as nom FROM SAV_Clients WHERE ID_Client = ?";
$client_res = query($client_sql, [$client_id]);
$client_info = sqlsrv_fetch_array($client_res, SQLSRV_FETCH_ASSOC);

echo json_encode([
    'sites' => $sites,
    'contrats' => $contrats,
    'client' => $client_info
]);
?>
