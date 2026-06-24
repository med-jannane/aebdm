<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['tac', 'admin']);

$tickets = query(
    "SELECT TICKET.ID_TICKET as id, TICKET.PRIORITE as priorite, TICKET.ETAT as statut, TICKET.DATE as cree_le,
            SAV_Clients.Nom as client_nom, SAV_Sites.Nom as site_nom
     FROM TICKET
     JOIN SAV_Clients ON SAV_Clients.ID_Client = TICKET.ID_CLIENT
     JOIN SAV_Sites ON SAV_Sites.Id_Site = TICKET.ID_SITE
     WHERE TICKET.ETAT IN ('ouvert', 'en_cours_tac')
     ORDER BY TICKET.DATE ASC"
);

$statusLabels = [
    'ouvert' => 'Ouvert',
    'en_cours_tac' => 'En TAC'
];

$statusClassMap = [
    'ouvert' => 'pill-open',
    'en_cours_tac' => 'pill-progress'
];

$pageTitle = "Tickets a traiter";
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
             <div style="display:flex; align-items:center; gap:20px;">
                <button class="mobile-toggle"><i class="fa-solid fa-bars"></i></button>
                <h1>Tickets à traiter</h1>
            </div>
            <div style="display:flex; gap:10px;">
                <a href="../admin/import_csv.php?type=tickets" class="btn btn-secondary"><i class="fa-solid fa-file-csv"></i> Importer</a>
            </div>
        </header>

        <div class="card">
            <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Ticket</th>
                        <th>Client</th>
                        <th>Site</th>
                        <th>Priorité</th>
                        <th>Statut</th>
                        <th>Création</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (sqlsrv_has_rows($tickets)): ?>
                        <?php while ($ticket = sqlsrv_fetch_array($tickets, SQLSRV_FETCH_ASSOC)): ?>
                            <?php
                            $statut = $ticket['statut'];
                            $label = $statusLabels[$statut] ?? ucfirst($statut);
                            $pillClass = $statusClassMap[$statut] ?? 'pill-open';
                            ?>
                            <tr>
                                <td><span class="badge">#<?php echo $ticket['id']; ?></span></td>
                                <td><?php echo htmlspecialchars($ticket['client_nom']); ?></td>
                                <td><i class="fa-solid fa-location-dot" style="color:var(--text-muted); margin-right:5px;"></i> <?php echo htmlspecialchars($ticket['site_nom']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($ticket['priorite']); ?>">
                                        <?php echo htmlspecialchars(ucfirst($ticket['priorite'])); ?>
                                    </span>
                                </td>
                                <td><span class="badge badge-<?php echo strtolower($statut); ?>"><?php echo $label; ?></span></td>
                                <td><?php echo $ticket['cree_le'] ? $ticket['cree_le']->format('d/m/Y H:i') : ''; ?></td>
                                <td>
                                    <a class="btn btn-sm" href="ticket_detail.php?ticket_id=<?php echo $ticket['id']; ?>"><i class="fa-solid fa-stethoscope"></i> Traiter</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:20px;">
                                <i class="fa-solid fa-check-circle" style="font-size:2rem; color:var(--success); display:block; margin-bottom:10px;"></i>
                                Aucun ticket à traiter pour le moment.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</body>
</html>
