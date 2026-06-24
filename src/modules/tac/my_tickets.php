<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['tac', 'admin']);

// Tickets 'en_cours'
$sql = "SELECT TICKET.ID_TICKET as id, TICKET.ETAT as statut, 'Général' as type_probleme, SAV_Clients.Nom as client_nom 
        FROM TICKET 
        JOIN SAV_Clients ON TICKET.ID_CLIENT = SAV_Clients.ID_Client
        WHERE TICKET.ETAT = 'en_cours_tac'
        ORDER BY TICKET.DATE DESC";
$tickets = query($sql);

$pageTitle = "Mes Dossiers En Cours";
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
                <h1>Mes Dossiers En Cours</h1>
            </div>
        </header>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Client</th>
                        <th>Problème</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($t = sqlsrv_fetch_array($tickets, SQLSRV_FETCH_ASSOC)): ?>
                    <tr>
                        <td><strong>#<?php echo $t['id']; ?></strong></td>
                        <td>
                            <i class="fa-regular fa-building"></i> <?php echo htmlspecialchars($t['client_nom']); ?>
                        </td>
                        <td><?php echo htmlspecialchars($t['type_probleme']); ?></td>
                        <td>
                            <a href="ticket_process.php?id=<?php echo $t['id']; ?>" class="btn btn-sm" style="background-color: var(--primary);">
                                <i class="fa-solid fa-play"></i> Continuer
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
