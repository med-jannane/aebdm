<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['admin', 'directeur']);

$pageTitle = 'Audit Imports';

function getCount($conn, $sql) {
    $stmt = sqlsrv_query($conn, $sql);
    if (!$stmt) return 0;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return (int)($row['total'] ?? 0);
}

$stats = [
    'clients_total' => getCount($conn, "SELECT COUNT(*) as total FROM SAV_Clients"),
    'clients_missing_address' => getCount($conn, "SELECT COUNT(*) as total FROM SAV_Clients WHERE Adresse IS NULL OR LTRIM(RTRIM(Adresse)) = ''"),
    'clients_missing_city' => getCount($conn, "SELECT COUNT(*) as total FROM SAV_Clients WHERE Ville IS NULL OR LTRIM(RTRIM(Ville)) = ''"),
    'clients_missing_phone' => getCount($conn, "SELECT COUNT(*) as total FROM SAV_Clients WHERE TEL IS NULL OR LTRIM(RTRIM(TEL)) = ''"),
    'clients_missing_email' => getCount($conn, "SELECT COUNT(*) as total FROM SAV_Clients WHERE Email IS NULL OR LTRIM(RTRIM(Email)) = ''"),

    'contrats_total' => getCount($conn, "SELECT COUNT(*) as total FROM CONTRAT"),
    'contrats_missing_montant' => getCount($conn, "SELECT COUNT(*) as total FROM CONTRAT WHERE Montant_Contrat IS NULL"),
    'contrats_missing_date_debut' => getCount($conn, "SELECT COUNT(*) as total FROM CONTRAT WHERE Date_Debut IS NULL"),
    'contrats_missing_date_fin' => getCount($conn, "SELECT COUNT(*) as total FROM CONTRAT WHERE Date_Fin IS NULL"),
    'contrats_missing_client' => getCount($conn, "SELECT COUNT(*) as total FROM CONTRAT WHERE (Code_Client IS NULL OR LTRIM(RTRIM(Code_Client)) = '') AND (ID_CLIENT IS NULL OR LTRIM(RTRIM(ID_CLIENT)) = '')"),

    'sites_total' => getCount($conn, "SELECT COUNT(*) as total FROM SAV_Sites"),
    'sites_missing_name' => getCount($conn, "SELECT COUNT(*) as total FROM SAV_Sites WHERE Nom IS NULL OR LTRIM(RTRIM(Nom)) = ''"),
    'sites_missing_address' => getCount($conn, "SELECT COUNT(*) as total FROM SAV_Sites WHERE Adresse IS NULL OR LTRIM(RTRIM(Adresse)) = ''"),
    'sites_missing_city' => getCount($conn, "SELECT COUNT(*) as total FROM SAV_Sites WHERE Ville IS NULL OR LTRIM(RTRIM(Ville)) = ''"),
    'sites_missing_phone' => getCount($conn, "SELECT COUNT(*) as total FROM SAV_Sites WHERE TEL IS NULL OR LTRIM(RTRIM(TEL)) = ''"),
    'sites_missing_geo' => getCount($conn, "SELECT COUNT(*) as total FROM SAV_Sites WHERE latitude IS NULL OR LTRIM(RTRIM(latitude)) = '' OR longitude IS NULL OR LTRIM(RTRIM(longitude)) = ''"),
    'sites_missing_client_link' => getCount($conn, "SELECT COUNT(*) as total FROM SAV_Sites WHERE Id_Client IS NULL OR LTRIM(RTRIM(Id_Client)) = ''"),
];

$sampleClients = sqlsrv_query($conn, "SELECT TOP 20 ID_Client, Nom, Adresse, Ville, TEL, Email FROM SAV_Clients WHERE Adresse IS NULL OR LTRIM(RTRIM(Adresse)) = '' OR Ville IS NULL OR LTRIM(RTRIM(Ville)) = '' OR TEL IS NULL OR LTRIM(RTRIM(TEL)) = '' ORDER BY Nom ASC");
$sampleContrats = sqlsrv_query($conn, "SELECT TOP 20 CODE_CONTRAT, Code_Client, ID_CLIENT, Montant_Contrat, Date_Debut, Date_Fin, Nom_Client FROM CONTRAT WHERE Montant_Contrat IS NULL OR Date_Debut IS NULL OR Date_Fin IS NULL ORDER BY CODE_CONTRAT ASC");
$sampleSites = sqlsrv_query($conn, "SELECT TOP 20 Id_Site, Id_Client, Nom, Ville, Adresse, TEL, latitude, longitude FROM SAV_Sites WHERE Nom IS NULL OR LTRIM(RTRIM(Nom)) = '' OR Adresse IS NULL OR LTRIM(RTRIM(Adresse)) = '' OR latitude IS NULL OR LTRIM(RTRIM(latitude)) = '' OR longitude IS NULL OR LTRIM(RTRIM(longitude)) = '' ORDER BY Id_Site ASC");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header>
            <div style="display:flex; align-items:center; gap:12px;">
                <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                <h1>Audit Qualite des Imports</h1>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="delete_all_sites.php" class="btn btn-secondary"><i class="fa-solid fa-trash"></i> Supprimer Sites</a>
                <a href="delete_all_clients.php" class="btn btn-secondary"><i class="fa-solid fa-users-slash"></i> Reset Clients</a>
            </div>
        </header>

        <div class="page-content">
            <div class="kpi-grid">
                <div class="kpi-card"><div class="kpi-body"><strong><?= $stats['clients_total'] ?></strong><span>Clients Total</span></div></div>
                <div class="kpi-card"><div class="kpi-body"><strong><?= $stats['contrats_total'] ?></strong><span>Contrats Total</span></div></div>
                <div class="kpi-card"><div class="kpi-body"><strong><?= $stats['sites_total'] ?></strong><span>Sites Total</span></div></div>
            </div>

            <div class="grid-3">
                <div class="card">
                    <h3><i class="fa-solid fa-building-user text-primary"></i> Clients</h3>
                    <p>Adresse manquante: <strong><?= $stats['clients_missing_address'] ?></strong></p>
                    <p>Ville manquante: <strong><?= $stats['clients_missing_city'] ?></strong></p>
                    <p>Telephone manquant: <strong><?= $stats['clients_missing_phone'] ?></strong></p>
                    <p>Email manquant: <strong><?= $stats['clients_missing_email'] ?></strong></p>
                </div>
                <div class="card">
                    <h3><i class="fa-solid fa-file-contract text-warning"></i> Contrats</h3>
                    <p>Montant manquant: <strong><?= $stats['contrats_missing_montant'] ?></strong></p>
                    <p>Date debut manquante: <strong><?= $stats['contrats_missing_date_debut'] ?></strong></p>
                    <p>Date fin manquante: <strong><?= $stats['contrats_missing_date_fin'] ?></strong></p>
                    <p>Lien client manquant: <strong><?= $stats['contrats_missing_client'] ?></strong></p>
                </div>
                <div class="card">
                    <h3><i class="fa-solid fa-map-location-dot text-success"></i> Sites</h3>
                    <p>Nom manquant: <strong><?= $stats['sites_missing_name'] ?></strong></p>
                    <p>Adresse manquante: <strong><?= $stats['sites_missing_address'] ?></strong></p>
                    <p>Ville manquante: <strong><?= $stats['sites_missing_city'] ?></strong></p>
                    <p>Telephone manquant: <strong><?= $stats['sites_missing_phone'] ?></strong></p>
                    <p>GPS manquant: <strong><?= $stats['sites_missing_geo'] ?></strong></p>
                    <p>Lien client manquant: <strong><?= $stats['sites_missing_client_link'] ?></strong></p>
                </div>
            </div>

            <div class="card">
                <div class="sec-head"><h3><i class="fa-solid fa-table"></i> Echantillon Clients incomplets</h3></div>
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>ID</th><th>Nom</th><th>Adresse</th><th>Ville</th><th>Tel</th><th>Email</th></tr></thead>
                        <tbody>
                        <?php if ($sampleClients && sqlsrv_has_rows($sampleClients)): while ($r = sqlsrv_fetch_array($sampleClients, SQLSRV_FETCH_ASSOC)): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['ID_Client'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['Nom'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['Adresse'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['Ville'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['TEL'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['Email'] ?? '') ?></td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="6" class="text-center text-muted">Aucun client incomplet detecte sur cet echantillon.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="sec-head"><h3><i class="fa-solid fa-table"></i> Echantillon Contrats incomplets</h3></div>
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>Code Contrat</th><th>Code Client</th><th>ID Client</th><th>Montant</th><th>Date Debut</th><th>Date Fin</th><th>Nom Client</th></tr></thead>
                        <tbody>
                        <?php if ($sampleContrats && sqlsrv_has_rows($sampleContrats)): while ($r = sqlsrv_fetch_array($sampleContrats, SQLSRV_FETCH_ASSOC)): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['CODE_CONTRAT'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['Code_Client'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['ID_CLIENT'] ?? '') ?></td>
                                <td><?= htmlspecialchars((string)($r['Montant_Contrat'] ?? '')) ?></td>
                                <td><?= ($r['Date_Debut'] instanceof DateTime) ? $r['Date_Debut']->format('Y-m-d') : htmlspecialchars((string)($r['Date_Debut'] ?? '')) ?></td>
                                <td><?= ($r['Date_Fin'] instanceof DateTime) ? $r['Date_Fin']->format('Y-m-d') : htmlspecialchars((string)($r['Date_Fin'] ?? '')) ?></td>
                                <td><?= htmlspecialchars($r['Nom_Client'] ?? '') ?></td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="7" class="text-center text-muted">Aucun contrat incomplet detecte sur cet echantillon.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="sec-head"><h3><i class="fa-solid fa-table"></i> Echantillon Sites incomplets</h3></div>
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>ID Site</th><th>ID Client</th><th>Nom</th><th>Ville</th><th>Adresse</th><th>Tel</th><th>Latitude</th><th>Longitude</th></tr></thead>
                        <tbody>
                        <?php if ($sampleSites && sqlsrv_has_rows($sampleSites)): while ($r = sqlsrv_fetch_array($sampleSites, SQLSRV_FETCH_ASSOC)): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['Id_Site'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['Id_Client'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['Nom'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['Ville'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['Adresse'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['TEL'] ?? '') ?></td>
                                <td><?= htmlspecialchars((string)($r['latitude'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($r['longitude'] ?? '')) ?></td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="8" class="text-center text-muted">Aucun site incomplet detecte sur cet echantillon.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
