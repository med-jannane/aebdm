<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['accueil', 'admin',]);

if (!isset($_GET['id'])) {
    header("Location: clients.php");
    exit;
}

$id = $_GET['id'];
$sql = "SELECT ID_Client as id, Nom as nom, ID_Client as code_client, Adresse as adresse, Ville as ville, TEL as telephone1, Email as email, GETDATE() as created_at FROM SAV_Clients WHERE ID_Client = ?";
$client = sqlsrv_fetch_array(query($sql, [$id]), SQLSRV_FETCH_ASSOC);

if (!$client) die("Client introuvable.");

// Sites
$sites = query("SELECT Id_Site as id, Nom as nom, Ville as ville, Adresse as adresse FROM SAV_Sites WHERE Id_Client = ?", [$id]);

// Tickets liés
$tickets = query("SELECT ID_TICKET as id FROM TICKET WHERE ID_CLIENT = ? ORDER BY DATE DESC", [$id]);

$pageTitle = "Détails Client : " . $client['nom'];
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
                <h1><?php echo htmlspecialchars($client['nom']); ?></h1>
                <span class="badge" style="font-size:1.2em; background:var(--primary-light); color:white;">
                    Code : <?php echo htmlspecialchars($client['code_client']); ?>
                </span>
            </div>
            <a href="clients.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Retour Liste Clients</a>
        </header>

        <div class="card">
            <h3>Informations</h3>
            <div class="grid-2">
                <div>
                    <p><strong>Adresse :</strong> <?php echo htmlspecialchars($client['adresse']); ?></p>
                    <p><strong>Ville :</strong> <?php echo htmlspecialchars($client['ville']); ?></p>
                </div>
                <div>
                    <p><strong>Téléphone :</strong> <?php echo htmlspecialchars($client['telephone1']); ?></p>
                    <p><strong>Email :</strong> <?php echo htmlspecialchars($client['email']); ?></p>
                    <p><strong>Ajouté le :</strong> <?php echo $client['created_at']->format('d/m/Y'); ?></p>
                </div>
            </div>
            <div style="margin-top:20px;">
                <a href="ticket_create.php?client_id=<?php echo $client['id']; ?>" class="btn">＋ Créer un Ticket pour ce client</a>
            </div>
        </div>

        <div class="card">
            <h3>Sites Géographiques</h3>
            <?php if(sqlsrv_has_rows($sites)): ?>
            <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Ville</th>
                        <th>Adresse</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($s = sqlsrv_fetch_array($sites, SQLSRV_FETCH_ASSOC)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($s['nom']); ?></td>
                        <td><?php echo htmlspecialchars($s['ville']); ?></td>
                        <td><?php echo htmlspecialchars($s['adresse']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            </div>
            <?php else: ?>
                <p>Aucun site enregistré.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
