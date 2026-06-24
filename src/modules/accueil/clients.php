<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['accueil', 'admin']);

// Récupération des clients avec leurs sites
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Dans SQL Server, string_agg est dispo depuis 2017. Si version antérieure, on devra faire autrement.
// Supposons >= 2017 pour facilité, sinon on fera une requête imbriquée dans la boucle (moins perf mais stable).
// Pour éviter complexité version SQL, on fait une requête simple Clients et on chopera les sites dedans.

$sql = "SELECT SAV_Clients.ID_Client as id, SAV_Clients.Nom as nom, SAV_Clients.Ville as ville, SAV_Clients.TEL as telephone1, SAV_Clients.ID_Client as code_client,
        (SELECT STRING_AGG(SAV_Sites.Id_Site + ':' + SAV_Sites.Nom, ', ') FROM SAV_Sites WHERE SAV_Sites.Id_Client = SAV_Clients.ID_Client) as sites_info
        FROM SAV_Clients 
        WHERE SAV_Clients.Nom LIKE ? OR SAV_Clients.Ville LIKE ? OR SAV_Clients.TEL LIKE ? OR SAV_Clients.ID_Client LIKE ? 
        ORDER BY SAV_Clients.Nom";
$params = ["%$search%", "%$search%", "%$search%", "%$search%"];
$clients = query($sql, $params);

$pageTitle = "Sélection Client";
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
                <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                <h1>Sélectionner un Client</h1>
            </div>
            <div style="display:flex; gap:10px;">
                <a href="../admin/import_csv.php?type=clients" class="btn btn-secondary"><i class="fa-solid fa-file-csv"></i> Importer CSV</a>
            </div>
        </header>

        <div class="card">
            <form method="GET" class="form-row" style="margin-bottom:20px;">
                <input type="text" name="search" placeholder="Nom, Ville, Téléphone..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn">Rechercher</button>
            </form>

            <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Nom</th>
                        <th>Sites (ID - Nom)</th>
                        <th>Ville</th>
                        <th>Téléphone</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($c = sqlsrv_fetch_array($clients, SQLSRV_FETCH_ASSOC)): ?>
                    <tr>
                        <td>
                            <a href="client_details.php?id=<?php echo $c['id']; ?>" style="font-weight:bold; text-decoration:underline;">
                                <?php echo htmlspecialchars($c['code_client']); ?>
                            </a>
                        </td>
                        <td><strong><?php echo htmlspecialchars($c['nom']); ?></strong></td>
                        <td>
                            <?php 
                            if (!empty($c['sites_info'])) {
                                $sites_list = explode(', ', $c['sites_info']);
                                foreach($sites_list as $site_str) {
                                    // format: ID:Nom
                                    $parts = explode(':', $site_str);
                                    if(count($parts) >= 2) {
                                        echo "<div style='margin-bottom:2px;'><span class='badge' style='background:#e0e7ff; color:#3730a3;'>#" . $parts[0] . "</span> " . htmlspecialchars($parts[1]) . "</div>";
                                    }
                                }
                            } else {
                                echo "<span style='color:#999; font-style:italic;'>Aucun site</span>";
                            }
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($c['ville']); ?></td>
                        <td><?php echo htmlspecialchars($c['telephone1']); ?></td>
                        <td>
                            <a href="ticket_create.php?client_id=<?php echo $c['id']; ?>" class="btn btn-sm">＋ Créer Ticket</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</body>
</html>
