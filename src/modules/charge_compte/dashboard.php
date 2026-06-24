<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role('charge_de_compte');

$cc_id = $_SESSION['user_id'];
$cc_nom = $_SESSION['nom_complet'] ?? $_SESSION['nom'] ?? $_SESSION['username'] ?? 'Chargé de compte';

// Schéma dynamique: certaines instances n'ont pas encore toutes les colonnes.
$schemaRow = sqlsrv_fetch_array(query("SELECT
    CASE WHEN COL_LENGTH('Users', 'manager_id') IS NULL THEN 0 ELSE 1 END AS has_manager,
    CASE WHEN COL_LENGTH('Users', 'telephone') IS NULL THEN 0 ELSE 1 END AS has_phone,
    CASE WHEN COL_LENGTH('Users', 'region') IS NULL THEN 0 ELSE 1 END AS has_region,
    CASE WHEN COL_LENGTH('TICKET', 'OBJET') IS NULL THEN 0 ELSE 1 END AS has_objet,
    CASE WHEN COL_LENGTH('TICKET', 'sujet') IS NULL THEN 0 ELSE 1 END AS has_sujet,
    CASE WHEN COL_LENGTH('TICKET', 'COMMENT') IS NULL THEN 0 ELSE 1 END AS has_comment"), SQLSRV_FETCH_ASSOC);

$hasManager = (int)($schemaRow['has_manager'] ?? 0) === 1;
$phoneExpr = ((int)($schemaRow['has_phone'] ?? 0) === 1) ? 'telephone' : "''";
$regionExpr = ((int)($schemaRow['has_region'] ?? 0) === 1) ? 'region' : "''";

if ((int)($schemaRow['has_objet'] ?? 0) === 1) {
    $subjectExpr = 'T.OBJET';
} elseif ((int)($schemaRow['has_sujet'] ?? 0) === 1) {
    $subjectExpr = 'T.sujet';
} elseif ((int)($schemaRow['has_comment'] ?? 0) === 1) {
    $subjectExpr = 'T.COMMENT';
} else {
    $subjectExpr = "'Sans sujet'";
}

// 1. Récupérer l'équipe (les techniciens/ingénieurs assignés à ce chargé de compte)
$equipeSql = "SELECT id, nom_complet, {$phoneExpr} AS telephone, {$regionExpr} AS region FROM Users";
$equipeParams = [];
if ($hasManager) {
    $equipeSql .= " WHERE manager_id = ?";
    $equipeParams[] = $cc_id;
} else {
    // Instance non migrée pour manager_id: ne pas casser la page.
    $equipeSql .= " WHERE 1 = 0";
}
$equipeSql .= " ORDER BY nom_complet ASC";
$stmtEquipe = query($equipeSql, $equipeParams);

$techs = [];
$techIds = [];
while ($row = sqlsrv_fetch_array($stmtEquipe, SQLSRV_FETCH_ASSOC)) {
    $techs[] = $row;
    $techIds[] = "'" . $row['id'] . "'";
}

// 2. Récupérer les interventions des membres de l'équipe
$interventionsSql = "
    SELECT 
        I.id as intervention_id, I.date_planifiee, I.statut as statut_inter, I.instructions,
        T.ID_TICKET as ticket_id, {$subjectExpr} as sujet, T.PRIORITE as priorite, T.ETAT as statut_ticket,
        C.Nom as client_nom, S.Nom as site_nom, S.Ville as ville,
        U.nom_complet as tech_nom
    FROM Interventions I
    JOIN TICKET T ON I.ticket_id = T.ID_TICKET
    JOIN SAV_Clients C ON T.ID_CLIENT = C.ID_Client
    JOIN SAV_Sites S ON T.ID_SITE = S.Id_Site
    JOIN Users U ON I.tech_id = U.id
";

$interParams = [];
if ($hasManager) {
    $interventionsSql .= "WHERE I.tech_id IN (SELECT id FROM Users WHERE manager_id = ?)\n";
    $interParams[] = $cc_id;
} else {
    $interventionsSql .= "WHERE 1 = 0\n";
}

$interventionsSql .= "
    ORDER BY I.date_planifiee DESC
";
$stmtInter = query($interventionsSql, $interParams);

$pageTitle = "Dashboard Chargé de Compte";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <style>
        .equipe-card {
            background: white;
            padding: 20px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            text-align: center;
        }
        .hdr-wrap { display:flex; align-items:center; gap:20px; }
        .team-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap:15px; margin-bottom:30px; }
        .tech-ico { color:var(--primary); margin-bottom:10px; }
        .m0 { margin:0; }
        .mb30 { margin-bottom:30px; }
        .table-scroll { overflow-x:auto; }
        .empty-row { text-align:center; color:var(--text-muted); padding:20px; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header>
             <div class="hdr-wrap">
                <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                <h1>Espace Chargé de Compte : <?php echo htmlspecialchars($cc_nom); ?></h1>
            </div>
        </header>

        <h2 class="section-title"><i class="fa-solid fa-people-group"></i> Mon équipe (Techniciens/Ingénieurs)</h2>
        <?php if (count($techs) > 0): ?>
            <div class="team-grid">
                <?php foreach($techs as $tech): ?>
                    <div class="equipe-card">
                        <i class="fa-solid fa-user-gear fa-2x tech-ico"></i>
                        <h3 class="m0"><?php echo htmlspecialchars($tech['nom_complet']); ?></h3>
                        <p class="subtle-text m0"><i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($tech['telephone'] ?? 'N/A'); ?></p>
                        <p class="subtle-text m0"><i class="fa-solid fa-map-location-dot"></i> <?php echo htmlspecialchars($tech['region'] ?? 'N/A'); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card mb30">
                <p class="subtle-text">Aucun technicien ne vous a été assigné pour le moment. Veuillez contacter le Directeur ou l'Administrateur.</p>
            </div>
        <?php endif; ?>

        <h2 class="section-title"><i class="fa-solid fa-list-check"></i> Interventions en cours et Historique de l'équipe</h2>
        <div class="card">
            <div class="table-scroll">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Tech / Ingénieur</th>
                            <th>Ticket</th>
                            <th>Date Prévue/Réalisée</th>
                            <th>Client / Site</th>
                            <th>Sujet</th>
                            <th>Statut Interv.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $hasInterventions = false;
                        if ($stmtInter) {
                            while($i = sqlsrv_fetch_array($stmtInter, SQLSRV_FETCH_ASSOC)): 
                                $hasInterventions = true;
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($i['tech_nom']); ?></strong></td>
                            <td><span class="badge">#<?php echo htmlspecialchars($i['ticket_id']); ?></span></td>
                            <td><?php echo $i['date_planifiee'] ? $i['date_planifiee']->format('d/m/Y H:i') : '-'; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($i['client_nom']); ?></strong><br>
                                <small><?php echo htmlspecialchars($i['site_nom']); ?> (<?php echo htmlspecialchars($i['ville']); ?>)</small>
                            </td>
                            <td><?php echo htmlspecialchars($i['sujet']); ?></td>
                            <td>
                                <?php 
                                    $badgeClass = 'badge-secondary';
                                    if(strtolower($i['statut_inter']) == 'planifie') $badgeClass = 'badge-info';
                                    if(strtolower($i['statut_inter']) == 'en_route') $badgeClass = 'badge-warning';
                                    if(strtolower($i['statut_inter']) == 'en_cours') $badgeClass = 'badge-primary';
                                    if(strtolower($i['statut_inter']) == 'terminee') $badgeClass = 'badge-success';
                                    if(strtolower($i['statut_inter']) == 'annulee') $badgeClass = 'badge-danger';
                                ?>
                                <span class="badge <?php echo $badgeClass; ?>"><?php echo strtoupper($i['statut_inter']); ?></span>
                            </td>
                        </tr>
                        <?php 
                            endwhile; 
                        }
                        if (!$hasInterventions): 
                        ?>
                        <tr>
                            <td colspan="6" class="empty-row">Aucune intervention affectée à votre équipe pour le moment.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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
