<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['tech', 'admin']);

$openChatId = isset($_GET['open_chat']) ? trim((string)$_GET['open_chat']) : '';
if ($openChatId !== '') {
    $openChatId = preg_replace('/[^A-Za-z0-9\-]/', '', $openChatId);
}

$user_id = $_SESSION['user_id'];

// Historique
$sql = "SELECT Interventions.*, SAV_Clients.Nom as client_nom, SAV_Sites.Nom as site_nom, SAV_Sites.Ville as ville, TICKET.ID_CLIENT as client_id 
        FROM Interventions 
        JOIN TICKET ON Interventions.ticket_id = TICKET.ID_TICKET
        JOIN SAV_Clients ON TICKET.ID_CLIENT = SAV_Clients.ID_Client
        LEFT JOIN SAV_Sites ON TICKET.ID_SITE = SAV_Sites.Id_Site
        WHERE Interventions.tech_id = ? AND Interventions.statut = 'termine'
        ORDER BY Interventions.date_intervention DESC";
$history = query($sql, [$user_id]);

$pageTitle = "Historique Tech";
$role = $_SESSION['role'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <style>
        .table-wrap {
            background: var(--surface); border-radius: var(--r-md); padding: 0; overflow: hidden;
            box-shadow: 0 4px 15px rgba(24,8,44,.05); border: 1px solid rgba(58,1,92,.08);
        }
        .table-wrap table { margin: 0; }
        .table-wrap th { background: var(--surface-2); border-bottom: 2px solid rgba(58,1,92,.08); }
        .table-wrap td { vertical-align: middle; }

        /* CHAT BUBBLES */
        .message-bubble { max-width: 85%; padding: 12px 16px; border-radius: 16px; position: relative; font-size: .95rem; line-height:1.5; }
        .message-mine { align-self: flex-end; background: linear-gradient(135deg, var(--dark-amethyst), var(--primary)); color: white; border-bottom-right-radius: 4px; box-shadow: 0 4px 10px rgba(155,93,229,.2); }
        .message-other { align-self: flex-start; background: #f1f5f9; color: var(--text-dark); border-bottom-left-radius: 4px; border: 1px solid #e2e8f0; }
        .message-meta { font-size: .75rem; opacity: .7; margin-top: 6px; display: block; text-align:right;}
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header>
            <div style="display:flex; align-items:center; gap:20px;">
                <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                <div style="display:flex; flex-direction:column;">
                    <h1 style="margin:0;"><i class="fa-solid fa-clock-rotate-left text-accent" style="margin-right:8px;"></i>Historique</h1>
                    <span style="font-size:.9rem; color:var(--text-muted); font-weight:600;">Interventions Terminées</span>
                </div>
            </div>
            <div style="display:flex; align-items:center;">
                <span class="badge badge-resolu"><i class="fa-solid fa-check-double"></i> <?= sqlsrv_has_rows($history) ? '' : '0' ?> Traitées</span>
            </div>
        </header>

        <div class="page-content">

            <div class="table-wrap">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Date Réalisation</th>
                                <th>Client</th>
                                <th>Site / Ville</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(sqlsrv_has_rows($history)): ?>
                                <?php while($h = sqlsrv_fetch_array($history, SQLSRV_FETCH_ASSOC)): 
                                     $date = $h['date_intervention'] ? $h['date_intervention']->format('d/m/Y H:i') : '—';
                                ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:600; color:var(--dark-amethyst-3);"><i class="fa-regular fa-calendar-check text-success" style="margin-right:4px;"></i> <?= explode(' ', $date)[0] ?></div>
                                        <div class="text-sm text-muted" style="margin-left:20px;"><?= explode(' ', $date)[1] ?? '' ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight:600;"><?= htmlspecialchars($h['client_nom']) ?></div>
                                        <button type="button" class="btn btn-sm btn-secondary" onclick="openClientDetailsModal('<?= htmlspecialchars($h['client_id']) ?>')" style="padding: 2px 8px; font-size: .8rem; margin-top:4px;" title="Fiche Client"><i class="fa-solid fa-address-card"></i> Voir Client</button>
                                    </td>
                                    <td>
                                         <div style="font-weight:600; color:var(--text);"><?= htmlspecialchars($h['site_nom']) ?></div>
                                         <div style="color:var(--text-muted); font-size:.9rem;"><i class="fa-solid fa-location-dot" style="opacity:.6;"></i> <?= htmlspecialchars($h['ville']) ?></div>
                                    </td>
                                    <td class="text-right">
                                        <div style="display:inline-flex; gap:6px;">
                                            <button type="button" class="btn btn-sm btn-secondary" onclick="openChatModal('<?= htmlspecialchars($h['id']) ?>')" title="Voir Discussion"><i class="fa-solid fa-comments text-info"></i> Chat</button>
                                            <a href="generate_pdf.php?id=<?= $h['id'] ?>" target="_blank" class="btn btn-sm" style="background-color:rgba(239,68,68,.1); color:var(--danger); border-color:transparent;"><i class="fa-solid fa-file-pdf"></i> PDF</a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center text-muted" style="padding:40px;">Aucune intervention terminée trouvée pour le moment.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- MODALE CHAT -->
    <div id="chatModal" class="modal">
        <div class="modal-content" style="max-width:650px; height:80vh; display:flex; flex-direction:column; padding:0; overflow:hidden;">
            <div class="modal-header" style="padding:20px 24px; background:linear-gradient(135deg, var(--dark-amethyst-3), var(--dark-amethyst)); color:white;">
                <h3 style="margin:0; color:white;"><i class="fa-solid fa-comments text-accent"></i> Historique Chat #<span id="chatInterventionId"></span></h3>
                <div class="close" onclick="closeChatModal()" style="color:white; opacity:.8;"><i class="fa-solid fa-xmark"></i></div>
            </div>
            
            <div id="chatMessages" style="flex:1; overflow-y:auto; padding:24px; display:flex; flex-direction:column; gap:14px; background:var(--surface-2);">
                <div style="text-align:center; color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin"></i> Chargement...</div>
            </div>
            
            <div style="padding:16px 24px; border-top:1px solid rgba(58,1,92,.08); background:var(--surface);">
                <div class="input-group">
                    <input type="text" id="chatInput" placeholder="Intervention terminée (Lecture seule...)" class="form-control" style="border-radius:20px 0 0 20px; border-right:none;" disabled>
                    <button type="button" class="btn btn-secondary" style="border-radius:0 20px 20px 0; padding:0 24px;" disabled><i class="fa-solid fa-paper-plane"></i></button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODALE CLIENT DETAILS -->
    <div id="clientDetailsModal" class="modal">
        <div class="modal-content" style="max-width:600px; padding:0; overflow:hidden;">
            <div class="modal-header" style="background:var(--surface-2); padding:24px; border-bottom:1px solid rgba(58,1,92,.08);">
                <h3 style="margin:0; color:var(--primary);"><i class="fa-solid fa-address-card text-accent"></i> Fiche Client Rapide</h3>
                <div class="close" onclick="closeClientDetailsModal()"><i class="fa-solid fa-xmark"></i></div>
            </div>
            
            <div id="clientDetailsContent" style="padding:24px; font-size:1.05rem; line-height:1.7;">
                <div style="text-align:center; color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin fa-2x"></i></div>
            </div>
            
            <div class="modal-footer" style="padding:16px 24px; background:var(--surface-2);">
                <button type="button" class="btn btn-secondary" onclick="closeClientDetailsModal()">Fermer</button>
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

        // CHAT LOGIC (READ-ONLY)
        let currentInterventionId = null;

        function openChatModal(interventionId) {
            currentInterventionId = interventionId;
            document.getElementById('chatInterventionId').textContent = interventionId;
            document.getElementById('chatModal').style.display = 'flex';
            loadChatMessages();
        }

        function closeChatModal() {
            document.getElementById('chatModal').style.display = 'none';
            currentInterventionId = null;
        }

        async function loadChatMessages() {
            if (!currentInterventionId) return;
            try {
                const response = await fetch(`../api/chat_intervention.php?action=get&intervention_id=${currentInterventionId}`);
                const data = await response.json();
                if (data.success) {
                    const messagesContainer = document.getElementById('chatMessages');
                    messagesContainer.innerHTML = '';
                    if (data.messages.length === 0) {
                        messagesContainer.innerHTML = '<div style="text-align:center; color:var(--text-muted); margin-top:20px;"><i class="fa-regular fa-message" style="font-size:2rem;opacity:.5;margin-bottom:10px;display:block;"></i>Aucun échange enregistré.</div>';
                        return;
                    }
                    data.messages.forEach(msg => {
                        const bubbleClass = msg.is_mine ? 'message-mine' : 'message-other';
                        const authorInfo = msg.is_mine ? '' : `<strong style="display:block;margin-bottom:4px;font-size:.85rem;color:var(--primary);">${msg.auteur} <span style="opacity:.6;font-weight:normal;">(${msg.role})</span></strong>`;
                        const msgHtml = `
                            <div class="message-bubble ${bubbleClass}">
                                ${authorInfo}
                                ${msg.message.replace(/\n/g, '<br>')}
                                <span class="message-meta">${msg.date}</span>
                            </div>
                        `;
                        messagesContainer.insertAdjacentHTML('beforeend', msgHtml);
                    });
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                }
            } catch (error) { console.error('Erreur chat:', error); }
        }

        // CLIENT DETAILS LOGIC
        async function openClientDetailsModal(clientId) {
            document.getElementById('clientDetailsModal').style.display = 'flex';
            document.getElementById('clientDetailsContent').innerHTML = '<div style="text-align:center; padding: 40px; color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin fa-2x"></i><br><br>Chargement des infos...</div>';
            
            try {
                const response = await fetch(`../api/get_client.php?id=${clientId}`);
                const data = await response.json();
                
                if (data.success) {
                    let html = `<div style="display:grid; grid-template-columns:1fr; gap:12px;">`;
                    for (const [key, value] of Object.entries(data.client)) {
                        if (value === null || value === '' || key.toLowerCase() === 'id') continue;
                        let displayValue = value;
                        if (key.toLowerCase() === 'email') {
                            displayValue = `<a href="mailto:${value}" style="color:var(--accent); font-weight:600;">${value}</a>`;
                        } else if (typeof value === 'string' && value.length > 50) {
                            displayValue = value.replace(/\n/g, '<br>');
                        }
                        const label = key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ');
                        html += `
                            <div style="background:var(--surface-2); padding:12px 16px; border-radius:8px; display:flex; flex-direction:column; gap:4px;">
                                <span style="font-size:.85rem; color:var(--text-muted); text-transform:uppercase; font-weight:700;">${label}</span>
                                <span style="word-break: break-word; font-weight:600; color:var(--text);">${displayValue}</span>
                            </div>`;
                    }
                    html += `</div>`;
                    document.getElementById('clientDetailsContent').innerHTML = html;
                } else {
                    document.getElementById('clientDetailsContent').innerHTML = '<div class="alert alert-error">Client introuvable ou erreur de chargement.</div>';
                }
            } catch (error) {
                document.getElementById('clientDetailsContent').innerHTML = '<div class="alert alert-error">Erreur réseau lors du chargement.</div>';
            }
        }

        function closeClientDetailsModal() { document.getElementById('clientDetailsModal').style.display = 'none'; }

        (function maybeOpenChatFromQuery() {
            const openChatId = <?= json_encode($openChatId, JSON_UNESCAPED_UNICODE) ?>;
            if (!openChatId) {
                return;
            }

            openChatModal(openChatId);

            if (window.history && window.history.replaceState) {
                const cleanUrl = window.location.pathname;
                window.history.replaceState({}, document.title, cleanUrl);
            }
        })();
    </script>
</body>
</html>
