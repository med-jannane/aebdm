<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['accueil', 'admin']);

$pageTitle = "Historique Site";
$searchResults = [];
$selectedSite = null;
$error = "";
$tickets = [];
$interventions = [];

// Traitement Recherche
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
    if (!empty($search)) {
        // Recherche par ID ou Nom
        $sql = "SELECT SAV_Sites.Id_Site as id, SAV_Sites.Nom as nom, SAV_Sites.Ville as ville, SAV_Sites.Adresse as adresse, SAV_Clients.Nom as client_nom, SAV_Clients.ID_Client as code_client, SAV_Clients.ID_Client as client_id 
                FROM SAV_Sites 
                JOIN SAV_Clients ON SAV_Sites.Id_Client = SAV_Clients.ID_Client 
                WHERE SAV_Sites.Nom LIKE ? OR SAV_Sites.Id_Site = ?";
        
        $params = ["%$search%", $search];
        $searchResults = fetchAll(query($sql, $params));

        if (count($searchResults) === 1) {
            // Un seul résultat -> Sélection directe
            $selectedSite = $searchResults[0];
        }
    }
}

// Traitement Sélection Site
if (isset($_GET['site_id'])) {
    $siteId = $_GET['site_id'];
    $sql = "SELECT SAV_Sites.Id_Site as id, SAV_Sites.Nom as nom, SAV_Sites.Ville as ville, SAV_Sites.Adresse as adresse, SAV_Clients.Nom as client_nom, SAV_Clients.ID_Client as code_client, SAV_Clients.ID_Client as client_id 
            FROM SAV_Sites 
            JOIN SAV_Clients ON SAV_Sites.Id_Client = SAV_Clients.ID_Client 
            WHERE SAV_Sites.Id_Site = ?";
    $r = fetchAll(query($sql, [$siteId]));
    
    if (!empty($r)) {
        $selectedSite = $r[0];
    } else {
        $error = "Site introuvable.";
    }
}

// Si un site est sélectionné, récupérer l'historique
if ($selectedSite) {
    // Tickets
    $sqlTickets = "SELECT ID_TICKET as id, DATE as cree_le, ETAT as statut, COMMENT as description FROM TICKET WHERE ID_SITE = ? ORDER BY DATE DESC";
    $tickets = fetchAll(query($sqlTickets, [$selectedSite['id']]));

    // Interventions (via les tickets du site)
    $sqlInterventions = "SELECT I.*, T.ID_TICKET as ticket_id_ref, U.nom_complet as tech_nom
                         FROM Interventions I
                         JOIN TICKET T ON I.ticket_id = T.ID_TICKET
                         LEFT JOIN Users U ON I.tech_id = U.id
                         WHERE T.ID_SITE = ?
                         ORDER BY I.date_planifiee DESC";
    $interventions = fetchAll(query($sqlInterventions, [$selectedSite['id']]));
}

$role = $_SESSION['role'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <style>
        .filter-bar { background: var(--surface); padding: 16px; border-radius: var(--r-md); box-shadow: 0 4px 15px rgba(24,8,44,.05); border: 1px solid rgba(58,1,92,.08); display: flex; gap: 16px; align-items: center; margin-bottom: 24px; flex-wrap: wrap; }
        .filter-bar .form-group { margin: 0; flex: 1; min-width: 250px; }

        .table-wrap { background: var(--surface); border-radius: var(--r-md); padding: 0; overflow: hidden; box-shadow: 0 4px 15px rgba(24,8,44,.05); border: 1px solid rgba(58,1,92,.08); }
        .table-wrap table { margin: 0; }
        .table-wrap th { background: var(--surface-2); border-bottom: 2px solid rgba(58,1,92,.08); }
        .table-wrap td { vertical-align: middle; }
        
        .timeline-section {
            background: var(--surface);
            border-radius: var(--r-md);
            padding: 24px;
            box-shadow: 0 4px 15px rgba(24,8,44,.05);
            border: 1px solid rgba(58,1,92,.08);
            margin-bottom: 24px;
        }
        .timeline-header {
            display: flex; gap: 12px; align-items: center;
            padding-bottom: 16px; margin-bottom: 20px;
            border-bottom: 2px solid rgba(58,1,92,.08);
        }
        .timeline-header h3 { margin: 0; color: var(--dark-amethyst-3); font-size: 1.3rem; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header>
             <div style="display:flex; align-items:center; gap:20px;">
                <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                <div style="display:flex; flex-direction:column;">
                    <h1 style="margin:0;"><i class="fa-solid fa-clock-rotate-left text-accent" style="margin-right:8px;"></i>Historique par Site</h1>
                    <span style="font-size:.9rem; color:var(--text-muted); font-weight:600;">Recherche de l'historique d'interventions</span>
                </div>
            </div>
            <div style="display:flex; gap:10px;">
                <span class="badge badge-normal"><i class="fa-solid fa-phone-volume"></i> Hotliner</span>
            </div>
        </header>

        <div class="page-content">
        
            <form method="GET" class="filter-bar">
                <div class="form-group" style="position:relative;">
                    <i class="fa-solid fa-search input-icon" style="top:50%; transform:translateY(-50%);"></i>
                    <input type="text" name="search" placeholder="Rechercher un site par Nom ou ID..." class="form-control" style="padding-left: 40px; border-radius:30px;" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                </div>
                <button type="submit" class="btn" style="border-radius:30px; padding: 0 24px;"><i class="fa-solid fa-magnifying-glass"></i> Rechercher</button>
                <?php if(isset($_GET['search']) && !empty($_GET['search'])): ?>
                    <a href="historique_site.php" class="btn btn-secondary" style="border-radius:30px;"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </form>

            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Liste des résultats si plusieurs -->
            <?php if (!empty($searchResults) && !$selectedSite): ?>
                <div class="card" style="margin-bottom: 24px;">
                    <h3 style="margin-top:0; color:var(--dark-amethyst-3);"><i class="fa-solid fa-list-ul text-accent"></i> Résultats de la recherche (<?= count($searchResults) ?>)</h3>
                    <div class="table-wrap" style="margin-top:16px;">
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nom du Site</th>
                                        <th>Appartenance Client</th>
                                        <th>Localisation</th>
                                        <th class="text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($searchResults as $site): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight:600; font-size:1.05rem;"><?= htmlspecialchars($site['nom']) ?></div>
                                            <div class="text-sm text-muted">ID: <?= htmlspecialchars($site['id']) ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight:600; color:var(--primary);"><i class="fa-regular fa-building"></i> <?= htmlspecialchars($site['client_nom']) ?></div>
                                        </td>
                                        <td><i class="fa-solid fa-location-dot" style="opacity:.6;"></i> <?= htmlspecialchars($site['ville']) ?></td>
                                        <td class="text-right">
                                            <a href="historique_site.php?site_id=<?= $site['id'] ?>&search=<?= urlencode($_GET['search']) ?>" class="btn btn-sm btn-secondary"><i class="fa-solid fa-eye text-accent"></i> Voir Historique</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Détails et Historique du Site Sélectionné -->
            <?php if ($selectedSite): ?>
                <div class="card" style="margin-bottom: 24px; border-left:4px solid var(--accent); background:linear-gradient(to right, rgba(155,93,229,.05), transparent);">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px;">
                        <div>
                            <h2 style="margin:0 0 8px; color:var(--dark-amethyst-3); font-size:1.6rem;"><i class="fa-solid fa-map-location-dot text-accent" style="margin-right:8px;"></i> <?= htmlspecialchars($selectedSite['nom']) ?></h2>
                            <p style="margin:0 0 4px; font-size:1.1rem;">
                                <i class="fa-solid fa-building text-muted"></i> Client Associé : <strong style="color:var(--primary);"><?= htmlspecialchars($selectedSite['client_nom']) ?></strong> <span class="badge badge-normal" style="font-size:.8rem; margin-left:8px;">Code: <?= htmlspecialchars($selectedSite['code_client']) ?></span>
                            </p>
                            <p style="margin:0; color:var(--text-muted);"><i class="fa-solid fa-location-arrow" style="opacity:.6;"></i> <?= htmlspecialchars($selectedSite['adresse'] . ', ' . $selectedSite['ville']) ?></p>
                        </div>
                        <div>
                            <a href="ticket_create.php?client_id=<?= $selectedSite['client_id'] ?>&site_id=<?= $selectedSite['id'] ?>" class="btn">
                                <i class="fa-solid fa-plus"></i> Créer Ticket pour ce Site
                            </a>
                        </div>
                    </div>
                </div>

                <div class="timeline-section">
                    <div class="timeline-header">
                        <div style="background:var(--accent); color:white; width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.2rem; box-shadow:0 4px 10px rgba(155,93,229,.3);">
                            <i class="fa-solid fa-ticket"></i>
                        </div>
                        <h3>Historique des Demandes (Tickets)</h3>
                    </div>
                    
                    <?php if (empty($tickets)): ?>
                        <div class="text-center text-muted" style="padding:30px; border:1px dashed rgba(58,1,92,.1); border-radius:var(--r-md);"><i class="fa-solid fa-mug-hot" style="font-size:2rem; margin-bottom:10px; opacity:.5;"></i><br>Aucun ticket d'incident enregistré pour ce site.</div>
                    <?php else: ?>
                        <div class="table-wrap">
                            <div class="table-wrapper">
                                <table>
                                    <thead>
                                        <tr>
                                            <th># Ticket</th>
                                            <th>Date d'Ouverture</th>
                                            <th>Motif / Description</th>
                                            <th>Statut Actuel</th>
                                            <th class="text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tickets as $t): 
                                            $s = strtolower($t['statut']);
                                            $bClass = 'badge-normal';
                                            if($s == 'resolu' || $s == 'cloture' || $s == 'traite') $bClass = 'badge-resolu';
                                            if(strpos($s, 'attente') !== false) $bClass = 'badge-info';
                                        ?>
                                        <tr>
                                            <td><span class="badge badge-normal" style="font-family:monospace; font-size:1rem;">#<?= htmlspecialchars($t['id']) ?></span></td>
                                            <td>
                                                <div style="font-weight:600;"><i class="fa-regular fa-calendar" style="color:var(--accent);margin-right:4px;"></i> <?= $t['cree_le'] ? $t['cree_le']->format('d/m/Y') : '-' ?></div>
                                                <div class="text-sm text-muted" style="margin-left:20px;"><?= $t['cree_le'] ? $t['cree_le']->format('H:i') : '' ?></div>
                                            </td>
                                            <td style="max-width:300px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($t['description']) ?>">
                                                <?= htmlspecialchars($t['description']) ?>
                                            </td>
                                            <td><span class="badge <?= $bClass ?>"><?= strtoupper(htmlspecialchars($t['statut'])) ?></span></td>
                                            <td class="text-right">
                                                <a href="ticket_details.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-secondary"><i class="fa-solid fa-eye text-accent"></i> Dossier complet</a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="timeline-section">
                    <div class="timeline-header">
                        <div style="background:var(--primary); color:white; width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.2rem; box-shadow:0 4px 10px rgba(58,1,92,.3);">
                            <i class="fa-solid fa-screwdriver-wrench"></i>
                        </div>
                        <h3>Historique des Interventions sur Site</h3>
                    </div>
                    
                    <?php if (empty($interventions)): ?>
                        <div class="text-center text-muted" style="padding:30px; border:1px dashed rgba(58,1,92,.1); border-radius:var(--r-md);"><i class="fa-solid fa-toolbox" style="font-size:2rem; margin-bottom:10px; opacity:.5;"></i><br>Aucune visite de technicien référencée pour ce site.</div>
                    <?php else: ?>
                        <div class="table-wrap">
                            <div class="table-wrapper">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Date de l'Intervention</th>
                                            <th>Technicien Délégué</th>
                                            <th>Ticket Réf.</th>
                                            <th>Statut de la Visite</th>
                                            <th>Extrait Rapport</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($interventions as $i): 
                                            $si = strtolower($i['statut']);
                                            $biClass = 'badge-info';
                                            if(strpos($si, 'termine') !== false || strpos($si, 'cloture') !== false) $biClass = 'badge-resolu';
                                        ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight:600;"><i class="fa-regular fa-calendar-check" style="color:var(--primary);margin-right:4px;"></i> <?= $i['date_planifiee'] ? $i['date_planifiee']->format('d/m/Y') : '-' ?></div>
                                            </td>
                                            <td><strong style="color:var(--text);"><i class="fa-solid fa-helmet-safety text-muted"></i> <?= htmlspecialchars($i['tech_nom'] ?? 'Non assigné') ?></strong></td>
                                            <td><a href="ticket_details.php?id=<?= htmlspecialchars($i['ticket_id_ref']) ?>" style="font-family:monospace; color:var(--accent); font-weight:bold;">#<?= htmlspecialchars($i['ticket_id_ref']) ?></a></td>
                                            <td><span class="badge <?= $biClass ?>"><?= strtoupper(htmlspecialchars($i['statut'])) ?></span></td>
                                            <td style="font-size:.9rem; color:var(--text-muted); max-width:250px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($i['rapport'] ?? '') ?>">
                                                <i class="fa-solid fa-quote-left text-muted"></i> <?= htmlspecialchars(substr($i['rapport'] ?? 'Aucun rapport rédigé', 0, 80)) . (strlen($i['rapport'] ?? '') > 80 ? '...' : '') ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script>
        document.getElementById('menuBtn') && document.getElementById('menuBtn').addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        });
        document.getElementById('sidebarOverlay') && document.getElementById('sidebarOverlay').addEventListener('click', () => {
            document.getElementById('sidebar').classList.remove('active');
            document.getElementById('sidebarOverlay').classList.remove('active');
        });
    </script>
</body>
</html>
