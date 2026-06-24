<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['dispatch', 'admin']);

$openChatId = isset($_GET['open_chat']) ? trim((string)$_GET['open_chat']) : '';
if ($openChatId !== '') {
    $openChatId = preg_replace('/[^A-Za-z0-9\-]/', '', $openChatId);
}

// Inclure la logique de clôture (traitement POST)
require_once __DIR__ . '/../../includes/cloture_modal_logic.php';

$sql = "SELECT Interventions.*, Users.nom_complet as tech_nom, SAV_Clients.Nom as client_nom, SAV_Sites.Ville as ville, TICKET.ID_TICKET as t_id 
        FROM Interventions 
        JOIN Users ON Interventions.tech_id = Users.id
        JOIN TICKET ON Interventions.ticket_id = TICKET.ID_TICKET
        JOIN SAV_Clients ON TICKET.ID_CLIENT = SAV_Clients.ID_Client
        LEFT JOIN SAV_Sites ON TICKET.ID_SITE = SAV_Sites.Id_Site
        ORDER BY Interventions.date_planifiee DESC";
$interventions = query($sql);

$role = $_SESSION['role'];
$pageTitle = "Planning Global";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <style>
        .interv-table-wrap {
            background: var(--surface); border-radius: var(--r-md); padding: 0; overflow: hidden;
            box-shadow: 0 4px 15px rgba(24,8,44,.05); border: 1px solid rgba(58,1,92,.08);
        }
        .interv-table-wrap table { margin: 0; }
        .interv-table-wrap th { background: var(--surface-2); border-bottom: 2px solid rgba(58,1,92,.08); }
        .interv-table-wrap td { vertical-align: middle; }
        .hdr-main { display:flex; align-items:center; gap:16px; }
        .title-no-margin { margin:0; }
        .title-icon { color:var(--accent); margin-right:8px; }
        .hdr-actions { display:flex; align-items:center; gap:10px; }
        .date-main { font-weight:600; color:var(--dark-amethyst-3); }
        .date-main i { color:var(--accent); margin-right:4px; }
        .time-sub { margin-left:20px; }
        .tech-strong { color:var(--primary); }
        .city-line i { opacity:.6; }
        .action-group { display:inline-flex; gap:6px; }
        .btn-success-solid { background:var(--success); border-color:var(--success); }
        .cell-empty { padding:40px; }
        .chat-modal-content { max-width:650px; height:80vh; display:flex; flex-direction:column; padding:0; overflow:hidden; }
        .chat-modal-header { padding:20px 24px; background:var(--blue-800); color:white; }
        .chat-modal-title { margin:0; color:white; }
        .chat-close { color:white; opacity:.8; }
        .chat-messages { flex:1; overflow-y:auto; padding:24px; display:flex; flex-direction:column; gap:14px; background:var(--surface-2); }
        .chat-loading { text-align:center; color:var(--text-muted); }
        .chat-input-wrap { padding:16px 24px; border-top:1px solid rgba(58,1,92,.08); background:var(--surface); }
        .chat-input { border-radius:20px 0 0 20px; border-right:none; }
        .chat-send-btn { border-radius:0 20px 20px 0; padding:0 24px; }
        .ticket-modal-content { max-width:1000px; padding:0; overflow:hidden; }
        .ticket-modal-header { background:var(--surface-2); padding:24px; border-bottom:1px solid rgba(58,1,92,.08); }
        .ticket-modal-title { margin:0; color:var(--primary); }
        .ticket-modal-body { padding:24px; font-size:1rem; line-height:1.7; background:var(--background); }
        .ticket-loading { text-align:center; color:var(--text-muted); padding:30px; }
        .ticket-modal-footer { padding:16px 24px; background:var(--surface-2); border-top:1px solid rgba(58,1,92,.08); }
        .empty-chat-state { text-align:center; color:var(--text-muted); margin-top:20px; }
        .empty-chat-state i { font-size:2rem; opacity:.5; margin-bottom:10px; display:block; }
        .author-info { display:block; margin-bottom:4px; font-size:.85rem; color:var(--primary); }
        .author-info .author-role { opacity:.6; font-weight:normal; }
        .loading-state-box { text-align:center; padding:40px; color:var(--text-muted); }
        .loading-state-box i { display:block; margin-bottom:10px; }
        .ticket-detail-card-clean { box-shadow:none !important; border:1px solid rgba(58,1,92,.08) !important; background:var(--surface) !important; }
        #chatModal, #ticketDetailsModal { display:none; }
        
        /* CHAT BUBBLES */
        .message-bubble { max-width: 85%; padding: 12px 16px; border-radius: 16px; position: relative; font-size: .95rem; line-height:1.5; }
        .message-mine { align-self: flex-end; background: var(--primary); color: white; border-bottom-right-radius: 4px; box-shadow: 0 4px 10px rgba(37,99,235,.2); }
        .message-other { align-self: flex-start; background: #f1f5f9; color: var(--text-dark); border-bottom-left-radius: 4px; border: 1px solid #e2e8f0; }
        .message-meta { font-size: .75rem; opacity: .7; margin-top: 6px; display: block; text-align:right;}
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header>
            <div class="hdr-main">
                <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                <h1 class="title-no-margin"><i class="fa-solid fa-calendar-week title-icon"></i>Planning Global</h1>
            </div>
            <div class="hdr-actions">
                 <span class="badge badge-admin"><i class="fa-solid fa-traffic-light"></i> Dispatcher</span>
            </div>
        </header>

        <div class="page-content">

            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-auto-dismiss"><i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="interv-table-wrap">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Date & Heure</th>
                                <th>Technicien</th>
                                <th>Client / Site</th>
                                <th>Statut</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(sqlsrv_has_rows($interventions)): ?>
                                <?php while($i = sqlsrv_fetch_array($interventions, SQLSRV_FETCH_ASSOC)): 
                                    $date = $i['date_planifiee']->format('d/m/Y');
                                    $time = $i['date_planifiee']->format('H:i');
                                    $s = strtolower($i['statut']);
                                    $bClass = 'badge-info';
                                    if(strpos($s, 'termine') !== false || strpos($s, 'terminé') !== false) $bClass = 'badge-resolu';
                                    if(strpos($s, 'cloture') !== false) $bClass = 'badge-normal';
                                ?>
                                <tr>
                                    <td>
                                        <div class="date-main"><i class="fa-regular fa-calendar"></i> <?= $date ?></div>
                                        <div class="text-sm text-muted time-sub"><?= $time ?></div>
                                    </td>
                                    <td><strong class="tech-strong"><i class="fa-solid fa-helmet-safety"></i> <?= htmlspecialchars($i['tech_nom']) ?></strong></td>
                                    <td>
                                        <strong><?= htmlspecialchars($i['client_nom']) ?></strong>
                                        <div class="text-sm text-muted city-line"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($i['ville']) ?></div>
                                    </td>
                                    <td><span class="badge <?= $bClass ?>"><?= strtoupper(htmlspecialchars($i['statut'])) ?></span></td>
                                    <td class="text-right">
                                        <div class="action-group">
                                            <button type="button" class="btn btn-sm btn-secondary" onclick="openChatModal('<?= htmlspecialchars($i['id']) ?>')" title="Chat"><i class="fa-solid fa-comments"></i> Chat</button>
                                            <button type="button" class="btn btn-sm btn-secondary" onclick="openTicketDetailsModal('<?= htmlspecialchars($i['t_id']) ?>')" title="Aperçu Ticket"><i class="fa-solid fa-eye"></i> Ticket</button>
                                            <?php if ($s == 'termine' || $s == 'terminé'): ?>
                                                <button type="button" class="btn btn-sm btn-success-solid" onclick="openClotureModal('<?= htmlspecialchars($i['id']) ?>')"><i class="fa-solid fa-check-double"></i> Clôturer</button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center text-muted cell-empty">Aucune intervention planifiée dans le système.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>


    <!-- MODALE CHAT -->
    <div id="chatModal" class="modal">
        <div class="modal-content chat-modal-content">
            <div class="modal-header chat-modal-header">
                <h3 class="chat-modal-title"><i class="fa-solid fa-comments text-accent"></i> Chat Intervention #<span id="chatInterventionId"></span></h3>
                <div class="close chat-close" onclick="closeChatModal()"><i class="fa-solid fa-xmark"></i></div>
            </div>
            
            <div id="chatMessages" class="chat-messages">
                <div class="chat-loading"><i class="fa-solid fa-spinner fa-spin"></i> Chargement...</div>
            </div>
            
            <div class="chat-input-wrap">
                <div class="input-group">
                    <input type="text" id="chatInput" placeholder="Saisissez votre message..." class="form-control chat-input" onkeypress="if(event.key === 'Enter') sendChatMessage()">
                    <button type="button" class="btn chat-send-btn" onclick="sendChatMessage()"><i class="fa-solid fa-paper-plane"></i> Envoyer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Détails Ticket -->
    <div id="ticketDetailsModal" class="modal">
        <div class="modal-content ticket-modal-content">
            <div class="modal-header ticket-modal-header">
                <h3 class="ticket-modal-title"><i class="fa-solid fa-file-invoice text-accent"></i> Aperçu du Ticket Associé</h3>
                <div class="close" onclick="closeTicketDetailsModal()"><i class="fa-solid fa-xmark"></i></div>
            </div>
            
            <div id="ticketDetailsContent" class="ticket-modal-body">
                <div class="ticket-loading"><i class="fa-solid fa-spinner fa-spin fa-2x"></i><br>Chargement des données...</div>
            </div>
            
            <div class="modal-footer ticket-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeTicketDetailsModal()">Fermer</button>
            </div>
        </div>
    </div>

    <!-- Interface utilisateur de la modale de clôture -->
    <?php require_once __DIR__ . '/../../includes/cloture_modal_ui.php'; ?>


    <script>
        document.getElementById('menuBtn') && document.getElementById('menuBtn').addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        });
        document.getElementById('sidebarOverlay') && document.getElementById('sidebarOverlay').addEventListener('click', () => {
            document.getElementById('sidebar').classList.remove('active');
            document.getElementById('sidebarOverlay').classList.remove('active');
        });

        // CHAT LOGIC
        let currentInterventionId = null;
        let chatPollInterval = null;

        function openChatModal(interventionId) {
            currentInterventionId = interventionId;
            document.getElementById('chatInterventionId').textContent = interventionId;
            document.getElementById('chatModal').style.display = 'flex';
            loadChatMessages();
            chatPollInterval = setInterval(loadChatMessages, 5000);
            setTimeout(() => document.getElementById('chatInput').focus(), 100);
        }

        function closeChatModal() {
            document.getElementById('chatModal').style.display = 'none';
            currentInterventionId = null;
            if (chatPollInterval) { clearInterval(chatPollInterval); chatPollInterval = null; }
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
                        messagesContainer.innerHTML = '<div class="empty-chat-state"><i class="fa-regular fa-message"></i>Pas encore de message. Dites bonjour !</div>';
                        return;
                    }
                    data.messages.forEach(msg => {
                        const bubbleClass = msg.is_mine ? 'message-mine' : 'message-other';
                        const authorInfo = msg.is_mine ? '' : `<strong class="author-info">${msg.auteur} <span class="author-role">(${msg.role})</span></strong>`;
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

        async function sendChatMessage() {
            if (!currentInterventionId) return;
            const input = document.getElementById('chatInput');
            const message = input.value.trim();
            if (!message) return;
            
            try {
                const response = await fetch(`../api/chat_intervention.php?action=post&intervention_id=${currentInterventionId}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message: message })
                });
                const data = await response.json();
                if (data.success) {
                    input.value = '';
                    loadChatMessages();
                } else { alert(data.error || 'Erreur lors de l\'envoi'); }
            } catch (error) { alert('Erreur réseau'); }
        }

        function maybeOpenChatFromQuery() {
            const openChatId = <?= json_encode($openChatId, JSON_UNESCAPED_UNICODE) ?>;
            if (!openChatId) {
                return;
            }

            openChatModal(openChatId);

            if (window.history && window.history.replaceState) {
                const cleanUrl = window.location.pathname;
                window.history.replaceState({}, document.title, cleanUrl);
            }
        }

        async function openTicketDetailsModal(ticketId) {
            document.getElementById('ticketDetailsModal').style.display = 'flex';
            document.getElementById('ticketDetailsContent').innerHTML = '<div class="loading-state-box"><i class="fa-solid fa-spinner fa-spin fa-2x"></i>Chargement des données...</div>';
            
            try {
                const response = await fetch(`../accueil/ticket_details.php?id=${ticketId}`);
                const html = await response.text();
                
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                const headerCard = doc.querySelector('.ticket-header-card');
                const gridArea = headerCard ? headerCard.nextElementSibling : null;
                
                if (headerCard && gridArea) {
                    document.getElementById('ticketDetailsContent').innerHTML = headerCard.outerHTML + gridArea.outerHTML;
                    
                    document.getElementById('ticketDetailsContent').querySelectorAll('.card').forEach(card => {
                        card.classList.add('ticket-detail-card-clean');
                    });
                } else {
                    document.getElementById('ticketDetailsContent').innerHTML = '<div class="alert alert-error">Impossible de charger les détails complets du ticket.</div>';
                }
            } catch (error) {
                document.getElementById('ticketDetailsContent').innerHTML = '<div class="alert alert-error">Erreur réseau lors du chargement.</div>';
            }
        }

        function closeTicketDetailsModal() { document.getElementById('ticketDetailsModal').style.display = 'none'; }

        maybeOpenChatFromQuery();
    </script>
</body>
</html>
