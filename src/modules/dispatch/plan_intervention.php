<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['dispatch', 'admin']);

if (!isset($_GET['ticket_id'])) {
    header("Location: dashboard.php");
    exit;
}

$ticket_id = $_GET['ticket_id'];
$error = "";
$success = "";

// Récupérer ticket avec infos complètes (Client, Site, Contrat)
$sql = "SELECT T.ID_TICKET as id, T.COMMENT as description, T.ID_CLIENT as client_id,
               C.Nom AS client_nom, C.Email AS client_email,
               S.Nom AS site_nom, '-' AS contact_nom, S.TEL AS contact_tel, S.Adresse as adresse, S.Ville as ville,
               (SELECT TOP 1 ID_CONTRAT FROM CONTRAT WHERE ID_CLIENT = C.ID_Client ORDER BY Date_Fin DESC) as contrat_id
        FROM TICKET T
        LEFT JOIN SAV_Clients C ON T.ID_CLIENT = C.ID_Client
        LEFT JOIN SAV_Sites S ON T.ID_SITE = S.Id_Site
        WHERE T.ID_TICKET = ?";

$ticket = sqlsrv_fetch_array(query($sql, [$ticket_id]), SQLSRV_FETCH_ASSOC);

if (!$ticket) die("Ticket introuvable.");

// Récupérer Techniciens
$techs = query("SELECT * FROM Users WHERE role = 'tech'");

// ... (Traitement POST inchangé) ...
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <style>
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .details-table td {
            padding: 8px;
            border-bottom: 1px solid var(--border);
        }
        .details-table td:first-child {
            font-weight: 500;
            color: var(--text-muted);
            width: 40%;
        }
        .details-table td:last-child {
            font-weight: 600;
            text-align: right;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header>
             <div style="display:flex; align-items:center; gap:20px;">
                <button class="mobile-toggle"><i class="fa-solid fa-bars"></i></button>
                <h1>Planifier Intervention</h1>
            </div>
            <a href="dashboard.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Annuler</a>
        </header>

        <?php if($error): ?><div class="card" style="color:var(--danger); border-left:4px solid var(--danger);"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?></div><?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="card">
                <h3><i class="fa-solid fa-circle-info"></i> Informations Client & Site</h3>
                <table class="details-table">
                    <tr><td>Code Client</td><td><?php echo htmlspecialchars($ticket['client_id']); ?></td></tr>
                    <tr><td>Nom Client</td><td><?php echo htmlspecialchars($ticket['client_nom']); ?></td></tr>
                    <tr><td>Code Contrat</td><td><?php echo htmlspecialchars($ticket['contrat_id'] ?? 'Aucun'); ?></td></tr>
                    <tr><td>Contact Sur Place</td><td><?php echo htmlspecialchars($ticket['contact_nom'] ?? 'N/A'); ?></td></tr>
                    <tr><td>Tél. Sur Place</td><td><?php echo htmlspecialchars($ticket['contact_tel'] ?? 'N/A'); ?></td></tr>
                    <tr><td>Adresse</td><td><?php echo htmlspecialchars($ticket['adresse'] . ', ' . $ticket['ville']); ?></td></tr>
                </table>

                <div style="background:var(--background-light); padding:15px; border-radius:var(--radius-sm); margin-top:20px;">
                    <p style="margin:0;"><strong><i class="fa-solid fa-quote-left"></i> Description Problème :</strong></p>
                    <p style="margin-top:5px; color:var(--text-muted);"><?php echo nl2br(htmlspecialchars($ticket['description'])); ?></p>
                </div>
            </div>
            
            <div class="card">
                <h3><i class="fa-solid fa-calendar-plus"></i> Planification</h3>
            
            <form method="POST" style="margin-top:20px;">
                <div class="form-group">
                    <label><i class="fa-solid fa-user-gear"></i> Technicien</label>
                    <select name="tech_id" required class="form-control">
                        <option value="">-- Choisir Technicien --</option>
                        <?php while($tech = sqlsrv_fetch_array($techs, SQLSRV_FETCH_ASSOC)): ?>
                            <option value="<?php echo $tech['id']; ?>"><?php echo htmlspecialchars($tech['nom_complet']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fa-regular fa-calendar"></i> Date & Heure Planifiée</label>
                    <input type="datetime-local" name="date_intervention" required class="form-control">
                </div>

                <button type="submit" class="btn btn-full"><i class="fa-solid fa-check"></i> Valider Intervention</button>
            </form>
            </div>
        </div>
    </div>
</body>
</html>
