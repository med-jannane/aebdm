<?php
require_once __DIR__ . '/_bootstrap.php';

$flags = cc_schema_flags();
$team = cc_fetch_team($cc_id, $flags);
$teamIds = cc_team_ids($team);
$hasTeam = count($teamIds) > 0;

$success = '';
$error = '';

$ticketRows = [];
$recentAffectations = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $techId = trim($_POST['tech_id'] ?? '');
    $ticketId = trim($_POST['ticket_id'] ?? '');
    $datePlanRaw = trim($_POST['date_planifiee'] ?? '');
    $instructions = trim($_POST['instructions'] ?? '');
    $createVp = isset($_POST['create_vp']) && $_POST['create_vp'] === '1';

    if (!$hasTeam || empty($flags['has_manager'])) {
        $error = "Aucun technicien assigné (manager_id manquant ou non configuré).";
    } elseif ($techId === '' || $ticketId === '' || $datePlanRaw === '') {
        $error = 'Technicien, ticket et date sont obligatoires.';
    } elseif (!in_array($techId, $teamIds, true)) {
        $error = 'Ce technicien ne vous est pas affecté.';
    } else {
        $dateObj = DateTime::createFromFormat('Y-m-d\TH:i', $datePlanRaw);
        if (!$dateObj) {
            $error = 'Format de date invalide.';
        } else {
            $dateSql = $dateObj->format('Y-m-d H:i:s');

            $existing = sqlsrv_fetch_array(query("SELECT TOP 1 id FROM Interventions WHERE ticket_id = ?", [$ticketId]), SQLSRV_FETCH_ASSOC);

            if ($existing) {
                query("UPDATE Interventions
                       SET tech_id = ?, date_planifiee = ?, instructions = ?, statut = 'planifie'
                       WHERE id = ?", [$techId, $dateSql, $instructions, $existing['id']]);
                $interventionId = $existing['id'];
            } else {
                $interventionId = uniqid('INT-');
                query("INSERT INTO Interventions (id, ticket_id, tech_id, date_planifiee, instructions, statut)
                       VALUES (?, ?, ?, ?, ?, 'planifie')", [$interventionId, $ticketId, $techId, $dateSql, $instructions]);
            }

            query("UPDATE TICKET SET ETAT = 'assigne' WHERE ID_TICKET = ?", [$ticketId]);

            $vpMsg = '';
            if ($createVp) {
                if (empty($flags['has_vp']) || empty($flags['has_vp_tickets'])) {
                    $vpMsg = ' Visite préventive non créée: tables VP/VP_Tickets absentes.';
                } else {
                    $contract = sqlsrv_fetch_array(query("SELECT TOP 1 c.ID_CONTRAT
                            FROM TICKET t
                            LEFT JOIN CONTRAT c ON c.ID_CLIENT = t.ID_CLIENT
                            WHERE t.ID_TICKET = ?
                            ORDER BY c.Date_Fin DESC", [$ticketId]), SQLSRV_FETCH_ASSOC);

                    if (!empty($contract['ID_CONTRAT'])) {
                        $vpId = uniqid('VP-');
                        $vpCode = 'VP-' . date('YmdHis');
                        query("INSERT INTO VP (ID_VP, ID_CONTRAT, CODE_VP, DATE_PREVUE, STATUT, NOTES)
                               VALUES (?, ?, ?, ?, 'En attente', ?)", [$vpId, $contract['ID_CONTRAT'], $vpCode, $dateSql, $instructions]);

                        query("IF NOT EXISTS (SELECT 1 FROM VP_Tickets WHERE ID_VP = ? AND ID_TICKET = ?)
                               INSERT INTO VP_Tickets (ID_VP, ID_TICKET) VALUES (?, ?)", [$vpId, $ticketId, $vpId, $ticketId]);

                        $vpMsg = ' Visite préventive créée.';
                    } else {
                        $vpMsg = ' Visite préventive non créée: aucun contrat lié au ticket.';
                    }
                }
            }

            $success = "Affectation enregistrée avec succès (Intervention #{$interventionId})." . $vpMsg;
        }
    }
}

if ($hasTeam) {
    $ph = cc_placeholders(count($teamIds));

    $subjectExpr = cc_ticket_subject_sql($flags);
    $ticketRows = fetchAll(query("SELECT TOP 80
            T.ID_TICKET,
            $subjectExpr AS sujet,
            T.ETAT,
            T.PRIORITE,
            C.Nom AS client_nom,
            S.Nom AS site_nom,
            S.Ville AS ville
        FROM TICKET T
        LEFT JOIN SAV_Clients C ON C.ID_Client = T.ID_CLIENT
        LEFT JOIN SAV_Sites S ON S.Id_Site = T.ID_SITE
        WHERE T.ETAT NOT IN ('cloture','annule')
        ORDER BY T.ID_TICKET DESC"));

    $recentAffectations = fetchAll(query("SELECT TOP 20
            I.id,
            I.ticket_id,
            I.statut,
            I.date_planifiee,
            U.nom_complet AS tech_nom,
            T.ETAT AS ticket_statut,
            C.Nom AS client_nom,
            S.Nom AS site_nom
        FROM Interventions I
        JOIN Users U ON U.id = I.tech_id
        LEFT JOIN TICKET T ON T.ID_TICKET = I.ticket_id
        LEFT JOIN SAV_Clients C ON C.ID_Client = T.ID_CLIENT
        LEFT JOIN SAV_Sites S ON S.Id_Site = T.ID_SITE
        WHERE I.tech_id IN ($ph)
        ORDER BY I.date_planifiee DESC", $teamIds));
}

$pageTitle = 'Charge de Compte - Affectations';
$ccActivePage = 'affectations';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <style>
        .grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .form-card { background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:18px; }
        .row { display:grid; grid-template-columns:1fr; gap:12px; }
        .hint { color:var(--text-muted); font-size:.86rem; }
        @media (max-width: 980px) { .grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header>
            <div class="hdr-main">
                <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                <div>
                    <h1>Affecter interventions et visites préventives</h1>
                    <p class="text-muted text-sm">Planifiez les interventions de vos techniciens et créez les visites préventives liées.</p>
                </div>
            </div>
        </header>

        <div class="page-content">
            <?php if ($success): ?><div class="alert alert-success"><i class="fa-solid fa-check"></i><div><?= htmlspecialchars($success) ?></div></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i><div><?= htmlspecialchars($error) ?></div></div><?php endif; ?>

            <?php if (!$hasTeam): ?>
                <div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation"></i><div>Aucun technicien assigné à votre compte pour le moment.</div></div>
            <?php endif; ?>

            <div class="grid">
                <div class="form-card">
                    <h3><i class="fa-solid fa-user-check"></i> Nouvelle affectation</h3>
                    <p class="hint">Une intervention existante sur le ticket sera mise à jour, sinon une nouvelle sera créée.</p>
                    <form method="POST" class="row">
                        <div class="form-group">
                            <label for="tech_id">Technicien</label>
                            <select id="tech_id" name="tech_id" class="form-control" required>
                                <option value="">-- Sélectionner --</option>
                                <?php foreach ($team as $t): ?>
                                    <option value="<?= htmlspecialchars($t['id']) ?>"><?= htmlspecialchars($t['nom_complet']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="ticket_id">Ticket</label>
                            <select id="ticket_id" name="ticket_id" class="form-control" required>
                                <option value="">-- Sélectionner --</option>
                                <?php foreach ($ticketRows as $t): ?>
                                    <option value="<?= htmlspecialchars($t['ID_TICKET']) ?>">
                                        #<?= htmlspecialchars($t['ID_TICKET']) ?> - <?= htmlspecialchars($t['client_nom'] ?? 'Client') ?> / <?= htmlspecialchars($t['site_nom'] ?? 'Site') ?> - <?= htmlspecialchars($t['sujet'] ?? 'Sans sujet') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="date_planifiee">Date planifiée</label>
                            <input type="datetime-local" id="date_planifiee" name="date_planifiee" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="instructions">Instructions</label>
                            <textarea id="instructions" name="instructions" class="form-control" rows="4" placeholder="Détails de la mission..."></textarea>
                        </div>

                        <div class="form-group">
                            <label><input type="checkbox" name="create_vp" value="1"> Créer aussi une visite préventive liée (si contrat disponible)</label>
                        </div>

                        <button type="submit" class="btn"><i class="fa-solid fa-paper-plane"></i> Valider l'affectation</button>
                    </form>
                </div>

                <div class="form-card">
                    <h3><i class="fa-solid fa-clock-rotate-left"></i> Dernières affectations</h3>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Interv.</th>
                                    <th>Tech</th>
                                    <th>Ticket</th>
                                    <th>Date</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentAffectations)): ?>
                                    <tr><td colspan="5" class="text-center text-muted">Aucune affectation récente.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recentAffectations as $a): ?>
                                        <tr>
                                            <td>#<?= htmlspecialchars($a['id']) ?></td>
                                            <td><?= htmlspecialchars($a['tech_nom'] ?? '—') ?></td>
                                            <td>#<?= htmlspecialchars($a['ticket_id'] ?? '—') ?></td>
                                            <td><?= htmlspecialchars(cc_format_date($a['date_planifiee'] ?? null)) ?></td>
                                            <td><span class="badge badge-normal"><?= strtoupper(htmlspecialchars($a['statut'] ?? 'N/A')) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
