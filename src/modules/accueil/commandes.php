<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['accueil', 'admin']);

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT Commandes.*, SAV_Sites.Ville as ville, SAV_Sites.Nom as site_nom 
        FROM Commandes 
        LEFT JOIN SAV_Sites ON Commandes.site_id = SAV_Sites.Id_Site 
        WHERE Commandes.nom_client LIKE ? OR Commandes.numero_commande LIKE ? 
        ORDER BY Commandes.created_at DESC";

$commandes = query($sql, ['%'.$search.'%', '%'.$search.'%']);

$pageTitle = "Gestion des Commandes (NAV)";
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
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header>
             <div style="display:flex; align-items:center; gap:20px;">
                <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                <div style="display:flex; flex-direction:column;">
                    <h1 style="margin:0;"><i class="fa-solid fa-cart-shopping text-accent" style="margin-right:8px;"></i>Commandes Clients</h1>
                    <span style="font-size:.9rem; color:var(--text-muted); font-weight:600;">Gestion et Validation (NAV)</span>
                </div>
            </div>
            <div style="display:flex; gap:10px;">
                <a href="../admin/import_csv.php?type=commandes" class="btn btn-secondary"><i class="fa-solid fa-file-csv"></i> Importer</a>
                <a href="commande_create.php" class="btn"><i class="fa-solid fa-plus"></i> Nouvelle Commande</a>
            </div>
        </header>

        <div class="page-content">
        
            <form method="GET" class="filter-bar">
                <div class="form-group" style="position:relative;">
                    <i class="fa-solid fa-search input-icon" style="top:50%; transform:translateY(-50%);"></i>
                    <input type="text" name="search" placeholder="Rechercher par Client ou N° Commande..." class="form-control" style="padding-left: 40px; border-radius:30px;" value="<?= htmlspecialchars($search) ?>">
                </div>
                <button type="submit" class="btn" style="border-radius:30px; padding: 0 24px;"><i class="fa-solid fa-magnifying-glass"></i> Rechercher</button>
                <?php if(!empty($search)): ?>
                    <a href="commandes.php" class="btn btn-secondary" style="border-radius:30px;"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </form>

            <div class="table-wrap">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>N° Commande</th>
                                <th>Client</th>
                                <th>Site / Ville</th>
                                <th>Montant HT</th>
                                <th>Statut</th>
                                <th>Fichier</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(sqlsrv_has_rows($commandes)): ?>
                                <?php while($c = sqlsrv_fetch_array($commandes, SQLSRV_FETCH_ASSOC)): 
                                    $s = strtolower($c['statut']);
                                    $bClass = 'badge-normal';
                                    if(strpos($s, 'valid') !== false) $bClass = 'badge-resolu';
                                    if(strpos($s, 'attent') !== false) $bClass = 'badge-warning';
                                    if(strpos($s, 'annul') !== false || strpos($s, 'rejet') !== false) $bClass = 'badge-urgente';
                                ?>
                                <tr>
                                    <td><span class="badge badge-normal" style="font-family:monospace; font-size:.95rem;">#<?= htmlspecialchars($c['numero_commande']) ?></span></td>
                                    <td>
                                        <div style="font-weight:600;"><i class="fa-regular fa-building text-muted"></i> <?= htmlspecialchars($c['nom_client']) ?></div>
                                        <div class="text-sm text-muted">Code: <?= htmlspecialchars($c['code_client']) ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight:600; color:var(--text);"><?= htmlspecialchars($c['site_nom'] ?? '-') ?></div>
                                        <div class="text-sm text-muted"><i class="fa-solid fa-location-dot" style="opacity:.6;"></i> <?= htmlspecialchars($c['ville'] ?? 'N/A') ?></div>
                                    </td>
                                    <td style="font-weight:700; color:var(--dark-amethyst-3);"><?= number_format($c['montant_ht'], 2) ?> <span style="font-size:.85rem; color:var(--text-muted); font-weight:normal;">DH</span></td>
                                    <td><span class="badge <?= $bClass ?>"><?= strtoupper(htmlspecialchars($c['statut'])) ?></span></td>
                                    <td>
                                        <?php if(!empty($c['fichier_joint'])): ?>
                                            <a href="../../../uploads/commandes/<?= htmlspecialchars($c['fichier_joint']) ?>" target="_blank" class="btn btn-sm btn-secondary" style="padding:4px 10px;"><i class="fa-solid fa-file-pdf text-danger"></i> PDF</a>
                                        <?php else: ?>
                                            <span class="text-muted text-sm">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight:600;"><i class="fa-regular fa-calendar" style="color:var(--accent);margin-right:4px;"></i> <?= $c['created_at']->format('d/m/Y') ?></div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center text-muted" style="padding:40px;">Aucune commande trouvée.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

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
