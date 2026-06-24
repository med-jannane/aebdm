<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['tac', 'admin']);

$pageTitle = "Historique Site (TAC)";
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
    // Tickets (Triés par date décroissante)
    $sqlTickets = "SELECT ID_TICKET as id, DATE as cree_le, ETAT as statut, COMMENT as description FROM TICKET WHERE ID_SITE = ? ORDER BY DATE DESC";
    $tickets = fetchAll(query($sqlTickets, [$selectedSite['id']]));

    // Interventions (Triées par date décroissante)
    $sqlInterventions = "SELECT I.*, T.ID_TICKET as ticket_id_ref, U.nom_complet as tech_nom
                         FROM Interventions I
                         JOIN TICKET T ON I.ticket_id = T.ID_TICKET
                         LEFT JOIN Users U ON I.tech_id = U.id
                         WHERE T.ID_SITE = ?
                         ORDER BY I.date_planifiee DESC";
    $interventions = fetchAll(query($sqlInterventions, [$selectedSite['id']]));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <style>
        .search-box {
            background: var(--background-light);
            padding: 20px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        .search-box input {
            flex: 1;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
        }
        .history-section {
            margin-top: 30px;
        }
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .history-table th, .history-table td {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid var(--border);
        }
        .history-table th {
            background-color: var(--background-light);
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header>
             <div style="display:flex; align-items:center; gap:20px;">
                <button class="mobile-toggle"><i class="fa-solid fa-bars"></i></button>
                <h1>Historique Site (TAC)</h1>
            </div>
        </header>

        <form method="GET" class="search-box">
            <input type="text" name="search" placeholder="Rechercher un site (Nom ou ID)..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
            <button type="submit" class="btn"><i class="fa-solid fa-search"></i> Rechercher</button>
        </form>

        <?php if ($error): ?>
            <div class="card" style="color:var(--danger); border-left:4px solid var(--danger);">
                <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($searchResults) && !$selectedSite): ?>
            <div class="card">
                <h3>Résultats de la recherche</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Nom Site</th>
                            <th>Client</th>
                            <th>Ville</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($searchResults as $site): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($site['nom']); ?></td>
                            <td><?php echo htmlspecialchars($site['client_nom']); ?></td>
                            <td><?php echo htmlspecialchars($site['ville']); ?></td>
                            <td>
                                <a href="historique_site.php?site_id=<?php echo $site['id']; ?>&search=<?php echo urlencode($_GET['search']); ?>" class="btn btn-sm"><i class="fa-solid fa-eye"></i> Voir Historique</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($selectedSite): ?>
            <div class="card" style="margin-bottom: 20px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div>
                        <h2 style="margin:0; color:var(--primary);"><?php echo htmlspecialchars($selectedSite['nom']); ?></h2>
                        <p style="color:var(--text-muted); margin-top:5px;">
                            <i class="fa-solid fa-building"></i> Client : <?php echo htmlspecialchars($selectedSite['client_nom']); ?>
                        </p>
                        <p><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($selectedSite['adresse'] . ', ' . $selectedSite['ville']); ?></p>
                        
                        <!-- Mini infos techniques -->
                        <div style="margin-top:10px; font-size:0.9em; background:var(--background-light); padding:10px; border-radius:var(--radius-sm);">
                            <strong><i class="fa-solid fa-server"></i> Infos Tech :</strong> 
                            Modem: <?php echo htmlspecialchars($selectedSite['modem'] ?? 'N/A'); ?> | 
                            Abo: <?php echo htmlspecialchars($selectedSite['type_abonnement'] ?? 'N/A'); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="history-section">
                <h3 style="border-bottom: 2px solid var(--primary); padding-bottom: 10px; margin-bottom: 20px;">
                    <i class="fa-solid fa-ticket"></i> Historique des Tickets
                </h3>
                <?php if (empty($tickets)): ?>
                    <p style="color:var(--text-muted); font-style:italic;">Aucun ticket trouvé.</p>
                <?php else: ?>
                    <div class="card">
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th># Ticket</th>
                                    <th>Statut</th>
                                    <th>Description</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $t): ?>
                                <tr>
                                    <td><?php echo $t['cree_le'] ? $t['cree_le']->format('d/m/Y') : '-'; ?></td>
                                    <td><strong>#<?php echo $t['id']; ?></strong></td>
                                    <td><span class="badge badge-<?php echo strtolower($t['statut']); ?>"><?php echo ucfirst($t['statut']); ?></span></td>
                                    <td><?php echo htmlspecialchars(substr($t['description'], 0, 80)) . '...'; ?></td>
                                    <td>
                                        <a href="ticket_detail.php?ticket_id=<?php echo $t['id']; ?>" class="btn btn-sm btn-secondary"><i class="fa-solid fa-eye"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="history-section">
                <h3 style="border-bottom: 2px solid var(--accent); padding-bottom: 10px; margin-bottom: 20px;">
                    <i class="fa-solid fa-screwdriver-wrench"></i> Historique des Interventions (PDF)
                </h3>
                <?php if (empty($interventions)): ?>
                    <p style="color:var(--text-muted); font-style:italic;">Aucune intervention trouvée.</p>
                <?php else: ?>
                    <div class="card">
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Date Planifiée</th>
                                    <th>Technicien</th>
                                    <th>Statut</th>
                                    <th>Ticket Réf.</th>
                                    <th>Rapport PDF</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($interventions as $i): ?>
                                <tr>
                                    <td><?php echo $i['date_planifiee'] ? $i['date_planifiee']->format('d/m/Y') : '-'; ?></td>
                                    <td><?php echo htmlspecialchars($i['tech_nom'] ?? 'Non assigné'); ?></td>
                                    <td><span class="badge"><?php echo ucfirst($i['statut']); ?></span></td>
                                    <td>#<?php echo $i['ticket_id_ref']; ?></td>
                                    <td>
                                        <?php if ($i['statut'] === 'termine' || $i['statut'] === 'cloture'): ?>
                                            <a href="../tech/generate_pdf.php?id=<?php echo $i['id']; ?>" target="_blank" class="btn btn-sm" style="background-color: #D32F2F; color: white;">
                                                <i class="fa-solid fa-file-pdf"></i> Voir le Rapport
                                            </a>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted); font-style:italic; font-size:0.9em;">Pas encore disponible</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
