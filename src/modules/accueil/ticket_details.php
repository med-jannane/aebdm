<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['accueil', 'admin', 'dispatch', 'tac', 'tech']);

if (!isset($_GET['id'])) {
    header("Location: tickets.php");
    exit;
}

$id = $_GET['id'];

// Récupérer le ticket et infos liées
$sql = "SELECT TICKET.ID_TICKET as id, TICKET.DATE as cree_le, TICKET.ETAT as statut, TICKET.PRIORITE as priorite, TICKET.COMMENT as description, NULL as technicien_assigne_id, 
        SAV_Clients.Nom as client_nom, SAV_Sites.Nom as site_nom, SAV_Sites.Ville as ville, SAV_Sites.Adresse as adresse,
        'Demande SAV' as sujet, 'Général' as type_probleme, '-' as contact_source, '-' as contact_sur_place, SAV_Clients.TEL as client_tel
        FROM TICKET 
        JOIN SAV_Clients ON TICKET.ID_CLIENT = SAV_Clients.ID_Client
        LEFT JOIN SAV_Sites ON TICKET.ID_SITE = SAV_Sites.Id_Site
        WHERE TICKET.ID_TICKET = ? OR TICKET.CODE = ?";
$ticket = sqlsrv_fetch_array(query($sql, [$id, $id]), SQLSRV_FETCH_ASSOC);

if (!$ticket) die("Ticket introuvable.");

$role = $_SESSION['role'];
$pageTitle = "Ticket #" . $ticket['id'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <style>
        .ticket-header-card {
            background: linear-gradient(145deg, var(--surface) 0%, #faf8fc 100%);
            padding: 28px;
            border-radius: var(--r-xl);
            box-shadow: 0 4px 18px rgba(24,8,44,.06);
            margin-bottom: 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 6px solid var(--primary);
            position: relative;
            overflow: hidden;
        }
        .ticket-header-card::before {
            content: ''; position: absolute; top: -50%; right: -10%; width: 200px; height: 200px;
            background: radial-gradient(circle, rgba(155,93,229,.1) 0%, transparent 60%);
            border-radius: 50%;
        }
        .statut-badge {
            font-size: .95rem;
            padding: 10px 20px;
            border-radius: 999px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
            box-shadow: 0 4px 12px rgba(0,0,0,.08);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .statut-ouvert { background: #e3f2fd; color: #1976d2; border: 1px solid #1976d2; }
        .statut-ferme, .statut-resolu { background: #e8f5e9; color: #388e3c; border: 1px solid #388e3c; }
        .statut-attente, .statut-planifie, .statut-en_cours_tac { background: #fff3e0; color: #f57c00; border: 1px solid #f57c00; }
        .statut-urgence { background: #fee2e2; color: #dc2626; border: 1px solid #dc2626; }
        
        .grid-details { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
        
        .detail-row {
            display: flex; justify-content: space-between; align-items: flex-start;
            padding: 14px 0; border-bottom: 1px solid rgba(58,1,92,.06);
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: var(--text-muted); font-size: .9rem; font-weight: 600; display:flex; align-items:center; gap:8px; }
        .detail-value { font-weight: 600; color: var(--text); text-align: right; max-width: 60%; }
        
        @media (max-width: 960px) {
            .grid-details { grid-template-columns: 1fr; }
            .ticket-header-card { flex-direction: column; align-items: flex-start; gap: 18px; }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <!-- MAIN -->
    <div class="main-content">
        
        <header>
            <div style="display:flex; align-items:center; gap:16px;">
                <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                <h1 style="margin:0;"><i class="fa-solid fa-ticket" style="color:var(--accent);margin-right:8px;"></i>Détails du Ticket #<?= htmlspecialchars($ticket['id']); ?></h1>
            </div>
            <div style="display:flex; gap:10px;">
                <button onclick="window.print()" class="btn btn-secondary"><i class="fa-solid fa-print"></i> Imprimer</button>
                <a href="javascript:history.back()" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Retour</a>
            </div>
        </header>

        <div class="page-content">

            <div class="ticket-header-card">
                <div>
                    <h2 style="margin:0; font-size:1.6rem; color:var(--dark-amethyst-3); font-weight:800;"><?= htmlspecialchars($ticket['sujet'] ?? 'Demande d\'Intervention'); ?></h2>
                    <p style="margin:8px 0 0; color:var(--text-muted); font-size:.9rem;">
                        <i class="fa-regular fa-calendar" style="color:var(--accent);"></i> Créé le <?= ($ticket['cree_le'] instanceof DateTime) ? $ticket['cree_le']->format('d/m/Y à H:i') : '—' ?>
                    </p>
                </div>
                <div class="statut-badge statut-<?= strtolower($ticket['priorite'] == 'urgente' ? 'urgence' : $ticket['statut']) ?>">
                    <?php if($ticket['statut'] == 'ouvert') echo '<i class="fa-solid fa-folder-open"></i>'; ?>
                    <?php if($ticket['statut'] == 'resolu') echo '<i class="fa-solid fa-check-double"></i>'; ?>
                    <?= strtoupper(htmlspecialchars($ticket['statut'])) ?>
                </div>
            </div>

            <div class="grid-details">
                <!-- COL LEFT -->
                <div style="display:flex; flex-direction:column; gap:24px;">
                    <div class="card">
                        <div class="sec-head" style="margin-bottom:18px; display:flex; gap:12px;">
                            <h3 style="margin:0; font-size:1.1rem;"><i class="fa-solid fa-circle-info" style="color:var(--accent);"></i> Description du problème</h3>
                            <div class="badge badge-info"><i class="fa-solid fa-tag"></i> <?= htmlspecialchars($ticket['type_probleme']) ?></div>
                            <div class="badge badge-<?= strtolower($ticket['priorite']) ?>"><i class="fa-solid fa-triangle-exclamation"></i> Priorité <?= strtoupper(htmlspecialchars($ticket['priorite'])) ?></div>
                        </div>
                        <div style="background:var(--surface-2); padding:20px; border-radius:12px; border:1px solid rgba(58,1,92,.05); font-size:.95rem; line-height:1.7; color:var(--text);">
                            <?= nl2br(htmlspecialchars($ticket['description'])) ?>
                        </div>
                    </div>

                    <div class="card">
                        <div class="sec-head" style="margin-bottom:18px;">
                            <h3 style="margin:0; font-size:1.1rem;"><i class="fa-solid fa-timeline" style="color:var(--accent);"></i> Traitement & Suivi</h3>
                        </div>
                        <div style="text-align:center; padding:30px; color:var(--text-muted);">
                            <i class="fa-solid fa-shoe-prints" style="font-size:2rem; opacity:.3; margin-bottom:10px;"></i>
                            <p>L'historique des actions sur ce ticket apparaîtra ici.</p>
                        </div>
                    </div>
                </div>

                <!-- COL RIGHT -->
                <div style="display:flex; flex-direction:column; gap:24px;">
                    <div class="card p-0">
                        <div style="padding: 20px 24px; background: linear-gradient(135deg, var(--dark-amethyst-3), var(--dark-amethyst)); border-radius: var(--r-md) var(--r-md) 0 0; color:white;">
                            <h3 style="margin:0; font-size:1.1rem; color:white;"><i class="fa-regular fa-building"></i> Client & Site</h3>
                        </div>
                        <div style="padding: 10px 24px 24px;">
                            <div class="detail-row">
                                <span class="detail-label"><i class="fa-solid fa-user-tie"></i> Client</span>
                                <span class="detail-value text-primary"><?= htmlspecialchars($ticket['client_nom']) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="fa-solid fa-phone"></i> Tél. Client</span>
                                <span class="detail-value"><?= htmlspecialchars($ticket['client_tel'] ?? '—') ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="fa-solid fa-location-dot"></i> Site</span>
                                <span class="detail-value"><?= htmlspecialchars($ticket['site_nom'] ?? 'Aucun site') ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="fa-solid fa-map"></i> Ville</span>
                                <span class="detail-value"><?= htmlspecialchars($ticket['ville'] ?? '—') ?></span>
                            </div>
                            <div class="detail-row" style="flex-direction:column; gap:8px;">
                                <span class="detail-label"><i class="fa-solid fa-map-pin"></i> Adresse</span>
                                <span class="detail-value" style="text-align:left; max-width:100%; font-size:.9rem; line-height:1.4;">
                                    <a href="https://maps.google.com/?q=<?= urlencode(($ticket['adresse']??'').' '.($ticket['ville']??'')) ?>" target="_blank" style="color:var(--accent);">
                                        <?= htmlspecialchars($ticket['adresse'] ?? '—') ?>
                                    </a>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="card p-0">
                         <div style="padding: 20px 24px; border-bottom: 1px solid rgba(58,1,92,.08);">
                            <h3 style="margin:0; font-size:1.1rem; color:var(--dark-amethyst-3);"><i class="fa-solid fa-address-book" style="color:var(--accent);"></i> Contacts</h3>
                        </div>
                        <div style="padding: 10px 24px 24px;">
                            <div class="detail-row">
                                <span class="detail-label"><i class="fa-solid fa-satellite-dish"></i> Source Info</span>
                                <span class="detail-value"><?= htmlspecialchars($ticket['contact_source'] ?? '—') ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="fa-solid fa-user-shield"></i> Sur Place</span>
                                <span class="detail-value"><?= htmlspecialchars($ticket['contact_sur_place'] ?? '—') ?></span>
                            </div>
                            <?php if(!empty($ticket['technicien_assigne_id'])): ?>
                            <div class="detail-row" style="background:rgba(155,93,229,.05); border-radius:8px; padding:12px; margin-top:10px;">
                                <span class="detail-label"><i class="fa-solid fa-helmet-safety" style="color:var(--primary);"></i> Tech. Assigné</span>
                                <span class="detail-value" style="color:var(--primary);"><?= htmlspecialchars($ticket['technicien_assigne_id']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
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
