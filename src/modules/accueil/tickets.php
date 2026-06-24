<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['accueil', 'admin']);

$filter = isset($_GET['statut']) ? $_GET['statut'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT TICKET.*, SAV_Clients.Nom as client_nom, SAV_Sites.Nom as site_nom 
        FROM TICKET 
        JOIN SAV_Clients ON TICKET.ID_CLIENT = SAV_Clients.ID_Client
        LEFT JOIN SAV_Sites ON TICKET.ID_SITE = SAV_Sites.Id_Site
        WHERE 1=1";
$params = [];

if ($filter != 'all') {
    $sql .= " AND TICKET.ETAT = ?";
    $params[] = $filter;
}

if ($search) {
    $sql .= " AND (SAV_Clients.Nom LIKE ? OR TICKET.COMMENT LIKE ?)";
    $params[] = '%'.$search.'%';
    $params[] = '%'.$search.'%';
}

$sql .= " ORDER BY TICKET.DATE DESC";
$tickets = query($sql, $params);

$pageTitle = "Gestion des Tickets — SAV Accueil";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <style>
        .filter-bar {
            background: var(--surface-2);
            padding: 20px;
            border-radius: var(--r-md);
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
            align-items: flex-end;
            flex-wrap: wrap;
            border: 1px solid rgba(58,1,92,.08);
            box-shadow: 0 2px 10px rgba(24,8,44,.03);
        }
        .filter-bar .form-group { margin-bottom: 0; flex: 1; min-width: 240px; }
        .filter-bar .form-group.w-auto { flex: 0 1 auto; min-width: 200px; }
        .filter-bar .btn { height: 44px; }
        
        .ticket-id { font-size:1.05rem; color:var(--primary); font-family:monospace; font-weight:800; }
        tr td:nth-child(3) { max-width: 320px; }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <!-- MAIN -->
    <div class="main-content">

        <!-- HEADER -->
        <header>
            <div style="display:flex;align-items:center;gap:14px;">
                <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                <div>
                    <h1><i class="fa-solid fa-ticket" style="color:var(--accent);margin-right:8px;"></i>Gestion des Tickets</h1>
                </div>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                <a href="../admin/import_csv.php?type=tickets" class="btn btn-sm btn-secondary"><i class="fa-solid fa-file-csv"></i> Importer</a>
                <a href="ticket_create.php" class="btn"><i class="fa-solid fa-plus"></i> Créer Ticket</a>
            </div>
        </header>

        <div class="page-content">
            <div class="card">
                
                <!-- FILTRES -->
                <form method="GET" class="filter-bar">
                    <div class="form-group">
                        <label><i class="fa-solid fa-magnifying-glass"></i> Recherche (Client, Description)</label>
                        <div class="input-group">
                            <i class="fa-solid fa-magnifying-glass input-icon"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Saisir un mot-clé..." class="form-control">
                        </div>
                    </div>
                    <div class="form-group w-auto">
                        <label><i class="fa-solid fa-filter"></i> Filtrer par statut</label>
                        <select name="statut" class="form-control">
                            <option value="all" <?= ($filter=='all')?'selected':'' ?>>Tous les statuts</option>
                            <option value="ouvert" <?= ($filter=='ouvert')?'selected':'' ?>>Ouvert</option>
                            <option value="en_cours" <?= ($filter=='en_cours')?'selected':'' ?>>En Cours</option>
                            <option value="resolu" <?= ($filter=='resolu')?'selected':'' ?>>Résolu</option>
                        </select>
                    </div>
                    <button type="submit" class="btn"><i class="fa-solid fa-check"></i> Filtrer</button>
                    <?php if($search || $filter !== 'all'): ?>
                        <a href="tickets.php" class="btn btn-secondary" title="Réinitialiser"><i class="fa-solid fa-xmark"></i></a>
                    <?php endif; ?>
                </form>

                <!-- TABLE -->
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Client & Site</th>
                                <th>Description</th>
                                <th>Statut</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if(sqlsrv_has_rows($tickets)):
                                while($t = sqlsrv_fetch_array($tickets, SQLSRV_FETCH_ASSOC)): 
                                    $badgeClass = 'badge-' . strtolower($t['ETAT']);
                                    $t_id = $t['ID_TICKET'] ?? $t['CODE'];
                            ?>
                            <tr>
                                <td><span class="ticket-id">#<?= htmlspecialchars($t['CODE'] ?? $t['ID_TICKET']) ?></span></td>
                                <td>
                                    <strong><i class="fa-solid fa-building" style="color:var(--accent);margin-right:6px;"></i><?= htmlspecialchars($t['client_nom']) ?></strong>
                                    <div class="text-sm text-muted mt-1" style="display:flex;align-items:center;gap:5px;">
                                        <i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($t['site_nom'] ?? 'Aucun site') ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="truncate" style="color:var(--text);font-size:.92rem;">
                                        <?= htmlspecialchars(substr($t['COMMENT'], 0, 80)) ?><?= strlen($t['COMMENT'])>80 ? '...' : '' ?>
                                    </div>
                                    <div class="text-sm text-muted mt-1">
                                        <?= ($t['DATE'] instanceof DateTime) ? $t['DATE']->format('d/m/Y H:i') : '' ?>
                                    </div>
                                </td>
                                <td><span class="badge <?= $badgeClass ?>"><?= strtoupper(htmlspecialchars($t['ETAT'])) ?></span></td>
                                <td class="text-right">
                                    <div style="display:inline-flex; gap:6px;">
                                        <a href="ticket_details.php?id=<?= $t_id ?>" class="btn btn-sm btn-secondary" title="Voir les détails"><i class="fa-solid fa-eye"></i></a>
                                        <a href="ticket_edit.php?id=<?= $t_id ?>" class="btn btn-sm btn-secondary" title="Modifier"><i class="fa-solid fa-pen"></i></a>
                                        <form method="POST" action="ticket_delete.php" style="display:inline;">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($t_id) ?>">
                                            <button type="submit" class="btn btn-sm btn-secondary" style="color:var(--danger);border-color:rgba(239,68,68,.3);" onclick="return confirm('Confirmer la suppression définitive de ce ticket ?');" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php 
                                endwhile; 
                            else: 
                            ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted" style="padding:40px;">
                                    <i class="fa-solid fa-inbox" style="font-size:2rem;margin-bottom:10px;color:rgba(58,1,92,.2);"></i>
                                    <br>Aucun ticket trouvé avec ces critères.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>

</body>
</html>
