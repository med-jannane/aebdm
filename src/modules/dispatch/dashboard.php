<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['dispatch', 'admin']);

// Tickets à planifier (statut = 'attente_dispatch')
$sql = "SELECT TICKET.ID_TICKET as id, TICKET.DATE as cree_le, TICKET.ETAT as statut, TICKET.PRIORITE as priorite, TICKET.MESSAGE_DISPATCH as message_relais_dispatch, TICKET.COMMENT as description,
        SAV_Clients.Nom as client_nom, SAV_Sites.Nom as site_nom, SAV_Sites.Ville as ville 
        FROM TICKET 
        JOIN SAV_Clients ON TICKET.ID_CLIENT = SAV_Clients.ID_Client
        LEFT JOIN SAV_Sites ON TICKET.ID_SITE = SAV_Sites.Id_Site
        WHERE TICKET.ETAT = 'attente_dispatch'
        ORDER BY TICKET.DATE ASC";
$tickets = query($sql);

$pageTitle = "Dashboard Dispatch";
$role = $_SESSION['role'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <style>
        .ticket-card {
            background: var(--surface);
            border-radius: var(--r-md);
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid rgba(58,1,92,.08);
            box-shadow: 0 4px 15px rgba(24,8,44,.05);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            transition: transform .2s, box-shadow .2s;
            position: relative; overflow: hidden;
        }
        .ticket-card:hover {
            transform: none;
            box-shadow: 0 8px 18px rgba(15,23,42,.08);
            border-color: rgba(15,23,42,.16);
        }
        .urgent::before { content:''; position:absolute; top:0; left:0; width:6px; height:100%; background:var(--danger); }
        .haute::before { content:''; position:absolute; top:0; left:0; width:6px; height:100%; background:var(--warning); }
        .urgent { background: #fff7f7; }
        .haute { background: #fffaf0; }

        .t-info { display: flex; flex-direction: column; gap: 12px; flex: 1; }
        .t-actions { display: flex; flex-direction: column; gap: 10px; min-width: 160px; margin-left: 24px; align-items: flex-end; }
        .hdr-main { display:flex; align-items:center; gap:20px; }
        .hdr-col { display:flex; flex-direction:column; }
        .title-no-margin { margin:0; }
        .subtitle-sm { font-size:.9rem; color:var(--text-muted); font-weight:600; }
        .hdr-actions { display:flex; gap:10px; align-items:center; }
        .tickets-list { display:flex; flex-direction:column; }
        .ticket-top { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
        .badge-ticket { font-family:monospace; font-size:1.05rem; }
        .ticket-client-title { margin:0; font-size:1.3rem; color:var(--dark-amethyst-3); }
        .site-line { color: var(--text-muted); font-size:1.05rem; font-weight:600; }
        .site-line i { color:var(--accent); }
        .site-line .city { opacity:.7; }
        .dispatch-note { background: var(--surface-2); padding: 16px; border-radius: var(--r-sm); font-size: .95rem; border-left:4px solid var(--accent); margin-top:8px; }
        .dispatch-note strong { color:var(--dark-amethyst-3); display:block; margin-bottom:6px; }
        .dispatch-note-body { line-height:1.6; color:var(--text); }
        .ticket-time { font-size:.85rem; color:var(--text-muted); margin-top:4px; }
        .assign-btn { padding:14px; }
        .empty-card { padding:60px; border:2px dashed rgba(16,185,129,.3); }
        .empty-icon { font-size:4rem; margin-bottom:20px; opacity:.7; }
        .empty-title { font-size:1.6rem; margin-bottom:10px; }
        .empty-sub { font-size:1.1rem; }
        .details-modal-content { max-width:600px; }
        .details-body { font-size:1rem; line-height:1.7; color:var(--text); max-height:60vh; overflow-y:auto; }
        .detail-block { margin-bottom:20px; }
        .detail-title-main { color:var(--primary); display:block; margin-bottom:8px; font-size:1.1rem; }
        .detail-title-tac { color:var(--accent); display:block; margin-bottom:8px; font-size:1.1rem; }
        .detail-box-main { background:var(--surface-2); padding:16px; border-radius:8px; border:1px solid rgba(58,1,92,.05); white-space:pre-wrap; }
        .detail-box-tac { background:rgba(155,93,229,.1); padding:16px; border-radius:8px; border:1px dashed var(--accent); white-space:pre-wrap; }
        #detailsModal { display:none; }
        
        @media (max-width: 768px) {
            .ticket-card { flex-direction: column; gap: 20px; }
            .t-actions { margin-left: 0; min-width: 100%; align-items: stretch; }
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header>
            <div class="hdr-main">
                <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                <div class="hdr-col">
                    <h1 class="title-no-margin"><i class="fa-solid fa-calendar-plus text-accent"></i> Tickets à Planifier</h1>
                    <span class="subtitle-sm">Dispatch et Assignation Tech</span>
                </div>
            </div>
            <div class="hdr-actions">
                <span class="badge badge-warning"><i class="fa-solid fa-clock"></i> En attente</span>
                <?php include __DIR__ . '/../../includes/notification_ui.php'; ?>
            </div>
        </header>

        <div class="page-content">

            <?php if(sqlsrv_has_rows($tickets)): ?>
            <div class="tickets-list">
                <?php while($t = sqlsrv_fetch_array($tickets, SQLSRV_FETCH_ASSOC)): 
                    $class = ($t['priorite'] == 'urgente') ? 'urgent' : (($t['priorite'] == 'haute') ? 'haute' : '');
                ?>
                <div class="ticket-card <?= $class ?>">
                    <div class="t-info">
                        <div class="ticket-top">
                             <span class="badge badge-normal badge-ticket">#<?= htmlspecialchars($t['id']) ?></span>
                             <h3 class="ticket-client-title"><i class="fa-regular fa-building text-muted"></i> <?= htmlspecialchars($t['client_nom']) ?></h3>
                             <span class="badge badge-<?= strtolower($t['priorite']) ?>"><i class="fa-solid fa-triangle-exclamation"></i> <?= strtoupper($t['priorite']) ?></span>
                        </div>
                        
                        <div class="site-line">
                            <i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($t['site_nom']) ?> <span class="city">(<?= htmlspecialchars($t['ville']) ?>)</span>
                        </div>
                        
                        <div class="dispatch-note">
                            <strong><i class="fa-solid fa-message text-accent"></i> Note du Support Expert (TAC) :</strong>
                            <div class="dispatch-note-body">
                                <?php 
                                if (!empty($t['message_relais_dispatch'])) {
                                    echo nl2br(htmlspecialchars($t['message_relais_dispatch']));
                                } else {
                                    echo "<em class='text-muted'>Aucune consigne spécifique transmise par le TAC.</em>";
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div class="ticket-time">
                            <i class="fa-regular fa-clock"></i> Transmis le <?= $t['cree_le'] ? $t['cree_le']->format('d/m/Y H:i') : '—' ?>
                        </div>
                    </div>

                    <div class="t-actions">
                        <a href="assign_tech.php?ticket_id=<?= $t['id'] ?>" class="btn btn-full assign-btn"><i class="fa-solid fa-calendar-check"></i> Assigner / Planifier</a>
                        <button onclick="openDetailsModal('<?= $t['id'] ?>', `<?= addslashes(htmlspecialchars($t['description'] ?? '')) ?>`, `<?= addslashes(htmlspecialchars($t['message_relais_dispatch'] ?? '')) ?>`)" class="btn btn-sm btn-secondary btn-full"><i class="fa-solid fa-eye"></i> Voir Détails Problème</button>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
                <div class="card text-center empty-card">
                    <i class="fa-solid fa-calendar-check text-success empty-icon"></i>
                    <h3 class="text-success empty-title">Tout est à jour !</h3>
                    <p class="text-muted empty-sub">Aucun ticket en attente de planification actuellement.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>


    <!-- Modale Détails Ticket -->
    <div id="detailsModal" class="modal">
        <div class="modal-content details-modal-content">
            <div class="modal-header">
                <h3><i class="fa-solid fa-circle-info text-accent"></i> Détails Techniques du Ticket</h3>
                <div class="close" onclick="closeDetailsModal()"><i class="fa-solid fa-xmark"></i></div>
            </div>
            <div id="detailsModalContent" class="details-body">
                Chargement...
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDetailsModal()">Fermer</button>
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

        function openDetailsModal(ticketId, desc, message) {
            document.getElementById('detailsModal').style.display = 'flex';
            let content = `
                <div class="detail-block">
                    <strong class="detail-title-main"><i class="fa-solid fa-clipboard-list"></i> Motif Initial du Client :</strong>
                    <div class="detail-box-main">${desc || "<em>Non renseigné</em>"}</div>
                </div>
            `;
            if (message && message.trim() !== "") {
                content += `
                <div>
                    <strong class="detail-title-tac"><i class="fa-solid fa-user-doctor"></i> Diagnostic / Note du TAC :</strong>
                    <div class="detail-box-tac">${message}</div>
                </div>
                `;
            }
            document.getElementById('detailsModalContent').innerHTML = content;
        }

        function closeDetailsModal() {
            document.getElementById('detailsModal').style.display = 'none';
        }
    </script>
</body>
</html>
