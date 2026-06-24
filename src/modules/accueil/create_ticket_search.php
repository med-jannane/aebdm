<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['accueil', 'admin']);

$search = isset($_GET['search']) ? $_GET['search'] : '';
$clients = [];

if ($search) {
    if (is_numeric($search)) {
        // Recherche par Id_Client ou TEL ou TEL2
        $sql = "SELECT ID_Client as id, Nom as nom, Ville as ville, TEL as telephone1, Email as email 
                FROM SAV_Clients 
                WHERE ID_Client = ? OR TEL LIKE ? OR TEL2 LIKE ?";
        $clients = query($sql, [$search, '%'.$search.'%', '%'.$search.'%']);
    } else {
        // Recherche par Nom
        $sql = "SELECT ID_Client as id, Nom as nom, Ville as ville, TEL as telephone1, Email as email 
                FROM SAV_Clients 
                WHERE Nom LIKE ?";
        $clients = query($sql, ['%'.$search.'%']);
    }
}

$pageTitle = "Ouverture Ticket - Recherche Client";
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
            <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
            <h1>Nouveau Ticket : Identifier le Client</h1>
        </header>

        <div class="card">
            <form method="GET" class="form-row">
                <input type="text" name="search" placeholder="Nom, Téléphone ou ID Client..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn">Rechercher</button>
            </form>
        </div>

        <?php if($search): ?>
        <div class="card">
            <h3>Résultats</h3>
            <?php if(sqlsrv_has_rows($clients)): ?>
                <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Ville</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($c = sqlsrv_fetch_array($clients, SQLSRV_FETCH_ASSOC)): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($c['nom']); ?></strong></td>
                            <td><small><?php echo htmlspecialchars($c['email'] ?? ''); ?></small></td>
                            <td>
                                <a href="create_ticket_form.php?client_id=<?php echo $c['id']; ?>" class="btn btn-sm">Sélectionner</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                </div>
            <?php else: ?>
                <p>Aucun client trouvé.</p>
                <p><em>Si le client n'existe pas, contactez un commercial pour le créer.</em></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
