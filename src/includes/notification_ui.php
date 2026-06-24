<!-- Notification UI Component -->
<div class="notification-container">
    <button id="notif-btn" class="btn-icon" type="button" aria-label="Ouvrir les notifications">
        <i class="fa-solid fa-bell"></i>
        <span id="notif-badge" class="badge-count hidden">0</span>
    </button>
    
    <div id="notif-dropdown" class="notif-dropdown">
        <div class="notif-header">
            <strong>Notifications</strong>
            <button id="mark-all-read" class="btn btn-sm btn-secondary" type="button">Tout marquer lu</button>
        </div>
        <ul id="notif-list">
            <li class="empty-notif">Aucune notification</li>
        </ul>
    </div>
</div>
