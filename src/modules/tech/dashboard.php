<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['tech', 'admin']);

$openChatId = isset($_GET['open_chat']) ? trim((string)$_GET['open_chat']) : '';
if ($openChatId !== '') {
    $openChatId = preg_replace('/[^A-Za-z0-9\-]/', '', $openChatId);
}

$user_id = $_SESSION['user_id'];

// Interventions assignées (statut 'planifiee')
$sql = "SELECT Interventions.*, TICKET.COMMENT as ticket_desc, SAV_Clients.Nom as client_nom, SAV_Sites.Nom as site_nom, SAV_Sites.Adresse as adresse, SAV_Sites.Ville as ville, TICKET.PRIORITE as priorite, TICKET.ID_CLIENT as client_id
        FROM Interventions 
        JOIN TICKET ON Interventions.ticket_id = TICKET.ID_TICKET
        JOIN SAV_Clients ON TICKET.ID_CLIENT = SAV_Clients.ID_Client
        LEFT JOIN SAV_Sites ON TICKET.ID_SITE = SAV_Sites.Id_Site
        WHERE Interventions.tech_id = ? AND Interventions.statut = 'planifie'
        ORDER BY Interventions.date_planifiee ASC";
$interventions = query($sql, [$user_id]);

$pageTitle = "Dashboard Technicien";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <style>
        .interv-card {
            background: var(--surface); padding: 24px; border-radius: var(--r-lg);
            border: 1px solid rgba(58,1,92,.08); box-shadow: 0 4px 15px rgba(24,8,44,.05);
            display: flex; flex-direction: column; gap: 16px; transition: transform .2s, box-shadow .2s;
        }
        .interv-card:hover { transform: none; box-shadow: 0 8px 18px rgba(15,23,42,.08); border-color: rgba(15,23,42,.16); }
        .interv-card.urgent { border-left: 6px solid var(--danger); background: #fff7f7; }
        
        .interv-head { display: flex; justify-content: space-between; align-items: flex-start; gap:16px; }
        .interv-head h3 { margin: 0 0 6px; font-size: 1.3rem; color: var(--dark-amethyst-3); font-weight: 800; letter-spacing: -.02em; }
        .interv-date { font-size:1.1rem; color:var(--primary); font-weight:800; display:flex; align-items:center; gap:8px; }
        
        .interv-body { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .interv-section { border-radius: var(--r-md); padding: 16px; background: var(--surface-2); border: 1px dashed rgba(58,1,92,.15); }
        
        @media (max-width: 768px) {
            .interv-body { grid-template-columns: 1fr; }
            .interv-head { flex-direction: column; }
            .interv-actions { flex-direction: column; }
        }

        /* CHAT BUBBLES */
        .message-bubble { max-width: 85%; padding: 12px 16px; border-radius: 16px; position: relative; font-size: .95rem; line-height:1.5; }
        .message-mine { align-self: flex-end; background: var(--primary); color: white; border-bottom-right-radius: 4px; box-shadow: 0 4px 10px rgba(37,99,235,.2); }
        .message-other { align-self: flex-start; background: #f1f5f9; color: var(--text-dark); border-bottom-left-radius: 4px; border: 1px solid #e2e8f0; }
        .message-meta { font-size: .75rem; opacity: .7; margin-top: 6px; display: block; text-align:right;}
        .hdr-main { display:flex; align-items:center; gap:16px; }
        .hdr-col { display:flex; flex-direction:column; }
        .title-no-margin { margin:0; }
        .subtitle-sm { font-size:.9rem; color:var(--text-muted); font-weight:600; }
        .hdr-actions { display:flex; align-items:center; gap:10px; }
        .interv-list { display:grid; gap:24px; }
        .ticket-top { display:flex; align-items:center; gap:10px; margin-bottom:4px; }
        .badge-ticket { font-family:monospace; }
        .site-title-sub { font-weight:600; color:var(--text-muted); font-size:1.1rem; opacity:.7; }
        .date-right { text-align:right; }
        .date-label { color:var(--text-muted); font-size:.85rem; font-weight:600; text-transform:uppercase; }
        .date-icons i { color:var(--accent); }
        .section-h4 { margin:0 0 10px; color:var(--dark-amethyst); font-size:1rem; }
        .client-row { margin-bottom:12px; }
        .label-sm { font-size:.85rem; display:block; }
        .client-name-strong { color:var(--text); font-size:1.05rem; }
        .client-btn { padding:2px 8px; font-size:.8rem; margin-left:8px; }
        .address-value { font-weight:600; margin-bottom:10px; }
        .map-links { display:flex; gap:8px; }
        .gmap-btn { background:#4285F4; border-color:#4285F4; }
        .waze-btn { background:#33ccff; border-color:#33ccff; color:var(--text-dark); }
        .section-gradient { background: var(--surface-2); }
        .desc-text { font-size:.95rem; line-height:1.6; color:var(--text); margin-bottom:15px; }
        .chat-open-btn { background:var(--info); border:none; }
        .interv-actions { margin-top:8px; }
        .report-btn { padding:14px; font-size:1.05rem; }
        .empty-card { padding:60px 20px; border:2px dashed rgba(16,185,129,.3); }
        .empty-icon { font-size:4rem; margin-bottom:20px; opacity:.7; }
        .empty-title { font-size:1.5rem; }
        .empty-subtitle { font-size:1.1rem; }
        .chat-modal-content { max-width:650px; height:80vh; display:flex; flex-direction:column; }
        .chat-modal-title { margin:0; color:white; }
        .chat-close { color:white; opacity:.8; }
        .chat-messages { flex:1; overflow-y:auto; padding:24px; display:flex; flex-direction:column; gap:14px; background:var(--surface-2); }
        .chat-loading { text-align:center; color:var(--text-muted); }
        .chat-input-wrap { padding:16px 24px; border-top:1px solid rgba(58,1,92,.08); background:var(--surface); }
        .chat-input { border-radius:20px 0 0 20px; border-right:none; }
        .chat-send-btn { border-radius:0 20px 20px 0; padding:0 24px; }
        .client-modal-content { max-width:600px; }
        .client-modal-title { margin:0; color:var(--primary); }
        .client-content { padding:24px; font-size:1.05rem; line-height:1.7; }
        .loading-lg { text-align:center; color:var(--text-muted); }
        .client-footer { padding:16px 24px; background:var(--surface-2); }
        .empty-chat-state { text-align:center; color:var(--text-muted); margin-top:20px; }
        .empty-chat-state i { font-size:2rem; opacity:.5; margin-bottom:10px; display:block; }
        .author-info { display:block; margin-bottom:4px; font-size:.85rem; color:var(--primary); }
        .author-info .author-role { opacity:.6; font-weight:normal; }
        .loading-state-box { text-align:center; padding:40px; color:var(--text-muted); }
        .loading-state-box i { display:block; margin-bottom:10px; }
        .client-detail-grid { display:grid; grid-template-columns:1fr; gap:12px; }
        .client-detail-item { background:var(--surface-2); padding:12px 16px; border-radius:8px; display:flex; flex-direction:column; gap:4px; }
        .client-detail-label { font-size:.85rem; color:var(--text-muted); text-transform:uppercase; font-weight:700; }
        .client-detail-value { word-break:break-word; font-weight:600; color:var(--text); }
        .client-email-link { color:var(--accent); font-weight:600; }
        #chatModal, #clientDetailsModal { display:none; }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <!-- MAIN -->
    <div class="main-content">
        <header>
            <div class="hdr-main">
                <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                <div class="hdr-col">
                    <h1 class="title-no-margin"><i class="fa-solid fa-helmet-safety text-accent"></i> Bonjour, <?= htmlspecialchars($_SESSION['nom_complet']) ?></h1>
                    <span class="subtitle-sm">Voici votre plan de tournée</span>
                </div>
            </div>
            <div class="hdr-actions">
                <span class="badge badge-normal"><i class="fa-solid fa-truck"></i> Tech. Terrain</span>
                <?php include __DIR__ . '/../../includes/notification_ui.php'; ?>
            </div>
        </header>

        <div class="page-content">

            <?php if(sqlsrv_has_rows($interventions)): ?>
            <div class="interv-list">
                <?php while($i = sqlsrv_fetch_array($interventions, SQLSRV_FETCH_ASSOC)): 
                    $date = $i['date_planifiee']->format('d/m/Y');
                    $time = $i['date_planifiee']->format('H:i');
                    $isUrgent = ($i['priorite'] == 'urgente');
                ?>
                <div class="interv-card <?= $isUrgent ? 'urgent' : '' ?>">
                    
                    <div class="interv-head">
                        <div>
                            <div class="ticket-top">
                                <span class="badge badge-normal badge-ticket">#<?= htmlspecialchars($i['ticket_id']) ?></span>
                                <span class="badge badge-<?= strtolower($i['priorite']) ?>"><i class="fa-solid fa-triangle-exclamation"></i> <?= strtoupper($i['priorite']) ?></span>
                            </div>
                            <h3><?= htmlspecialchars($i['site_nom']) ?> <span class="site-title-sub">(<?= htmlspecialchars($i['ville']) ?>)</span></h3>
                        </div>
                        <div class="interv-date">
                            <div class="date-right">
                                <div class="date-label">Planifiée le</div>
                                <div class="date-icons"><i class="fa-regular fa-calendar"></i> <?= $date ?> &nbsp;<i class="fa-regular fa-clock"></i> <?= $time ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="interv-body">
                        <!-- Adresse & Client -->
                        <div class="interv-section">
                            <h4 class="section-h4"><i class="fa-solid fa-map-location-dot"></i> Localisation & Contact</h4>
                            <div class="client-row">
                                <span class="text-muted label-sm">Client</span>
                                <strong class="client-name-strong"><?= htmlspecialchars($i['client_nom']) ?></strong>
                                <button type="button" class="btn btn-sm btn-secondary client-btn" onclick="openClientDetailsModal('<?= htmlspecialchars($i['client_id']) ?>')" title="Infos Client"><i class="fa-solid fa-address-card"></i> Fiche</button>
                            </div>
                            <div>
                                <span class="text-muted label-sm">Adresse</span>
                                <div class="address-value"><?= htmlspecialchars($i['adresse']) ?></div>
                                <div class="map-links">
                                    <?php $mapQuery = urlencode($i['adresse'] . ' ' . $i['ville']); ?>
                                    <a href="https://maps.google.com/?q=<?= $mapQuery ?>" target="_blank" class="btn btn-sm gmap-btn"><i class="fa-solid fa-map-pin"></i> gMaps</a>
                                    <a href="https://waze.com/ul?q=<?= $mapQuery ?>" target="_blank" class="btn btn-sm waze-btn"><i class="fa-brands fa-waze"></i> Waze</a>
                                </div>
                            </div>
                        </div>

                        <!-- Problème & Chat -->
                        <div class="interv-section section-gradient">
                            <h4 class="section-h4"><i class="fa-solid fa-clipboard-list"></i> Motif de l'intervention</h4>
                            <p class="desc-text"><?= nl2br(htmlspecialchars($i['ticket_desc'])) ?></p>
                            
                            <button type="button" class="btn btn-sm chat-open-btn" onclick="openChatModal('<?= htmlspecialchars($i['id']) ?>')"><i class="fa-solid fa-comments"></i> Discussion TAC / Tech</button>
                        </div>
                    </div>

                    <div class="interv-actions">
                        <a href="report_intervention.php?id=<?= $i['id'] ?>" class="btn btn-full report-btn"><i class="fa-solid fa-file-signature"></i> Valider et Saisir le Rapport d'Intervention</a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
                <div class="card text-center empty-card">
                     <i class="fa-solid fa-calendar-check text-success empty-icon"></i>
                    <h3 class="text-success empty-title">Journée Libre !</h3>
                    <p class="text-muted empty-subtitle">Aucune intervention planifiée pour le moment sur votre secteur.</p>
                </div>
            <?php endif; ?>

        </div>
    </div>


    <!-- MODALE CHAT -->
    <div id="chatModal" class="modal">
        <div class="modal-content chat-modal-content">
            <div class="modal-header">
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


    <!-- MODALE CLIENT DETAILS -->
    <div id="clientDetailsModal" class="modal">
        <div class="modal-content client-modal-content">
            <div class="modal-header">
                <h3 class="client-modal-title"><i class="fa-solid fa-address-card text-accent"></i> Fiche Client Rapide</h3>
                <div class="close" onclick="closeClientDetailsModal()"><i class="fa-solid fa-xmark"></i></div>
            </div>
            
            <div id="clientDetailsContent" class="client-content">
                <div class="loading-lg"><i class="fa-solid fa-spinner fa-spin fa-2x"></i></div>
            </div>
            
            <div class="modal-footer client-footer">
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

        // CLIENT DETAILS LOGIC
        async function openClientDetailsModal(clientId) {
            document.getElementById('clientDetailsModal').style.display = 'flex';
            document.getElementById('clientDetailsContent').innerHTML = '<div class="loading-state-box"><i class="fa-solid fa-spinner fa-spin fa-2x"></i>Chargement des infos...</div>';
            
            try {
                const response = await fetch(`../api/get_client.php?id=${clientId}`);
                const data = await response.json();
                
                if (data.success) {
                    let html = `<div class="client-detail-grid">`;
                    for (const [key, value] of Object.entries(data.client)) {
                        if (value === null || value === '' || key.toLowerCase() === 'id') continue;
                        let displayValue = value;
                        if (key.toLowerCase() === 'email') {
                            displayValue = `<a href="mailto:${value}" class="client-email-link">${value}</a>`;
                        } else if (typeof value === 'string' && value.length > 50) {
                            displayValue = value.replace(/\n/g, '<br>');
                        }
                        const label = key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ');
                        html += `
                            <div class="client-detail-item">
                                <span class="client-detail-label">${label}</span>
                                <span class="client-detail-value">${displayValue}</span>
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
