<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['commercial', 'admin', 'accueil', 'dispatch', 'tac', 'tech', 'directeur', 'charge_de_compte']);

$role = $_SESSION['role'];
$can_manage_contracts = in_array($role, ['commercial', 'admin', 'directeur']);
$can_view_amount = in_array($role, ['commercial', 'admin', 'directeur']);

$sql = "SELECT CONTRAT.ID_CONTRAT as id, CONTRAT.CODE_CONTRAT as numero_contrat, CONTRAT.Date_Debut as date_debut, CONTRAT.Date_Fin as date_fin, CONTRAT.TYPE as type_contrat, CONTRAT.TYPE as categorie_contrat, CONTRAT.Montant_Contrat as montant_annuel, CONTRAT.ETAT as statut, CONTRAT.ID_CLIENT as client_id, CONTRAT.Code_Client as client_code, CONTRAT.VP as vp, CONTRAT.Mode_Facturation as mode_facturation, SAV_Clients.Nom as client_nom 
        FROM CONTRAT 
    LEFT JOIN SAV_Clients ON LTRIM(RTRIM(ISNULL(CONTRAT.ID_CLIENT, ''))) = LTRIM(RTRIM(SAV_Clients.ID_Client))
                OR LTRIM(RTRIM(ISNULL(CONTRAT.Code_Client, ''))) = LTRIM(RTRIM(SAV_Clients.ID_Client))
        ORDER BY CONTRAT.Date_Fin ASC";
$contrats = query($sql);

$pageTitle = "Tous les Contrats";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <style>
        .filter-bar {
            background: var(--surface); padding: 16px 24px; border-radius: var(--r-md); margin-bottom: 24px;
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;
            box-shadow: 0 4px 15px rgba(24,8,44,.05); border: 1px solid rgba(58,1,92,.08);
        }
        
        .search-form { display: flex; gap: 12px; flex: 1; max-width: 450px; }
        .search-form .input-group { flex: 1; position: relative; }
        .search-form .input-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
        .search-form input { width: 100%; padding: 12px 16px 12px 42px; border: 1px solid var(--border); border-radius: 30px; font-family: inherit; transition: all .2s; }
        .search-form input:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(155,93,229,.1); }
        .filter-actions { display: flex; gap: 12px; flex-wrap: wrap; }
        .filter-actions .btn { border-radius: 30px; white-space: nowrap; }

        .contrat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; }

        .contrat-card {
            background: var(--surface); border-radius: 16px; padding: 0;
            box-shadow: 0 2px 12px rgba(24,8,44,.06); border: 1px solid rgba(58,1,92,.06);
            text-decoration: none; color: inherit; transition: all .35s cubic-bezier(0.25, 0.8, 0.25, 1);
            position: relative; overflow: hidden; display: flex; flex-direction: column;
        }
        .contrat-card::before {
            content: ''; position: absolute; top: 0; left: 0; width: 5px; height: 100%;
            background: linear-gradient(180deg, #6366f1, #8b5cf6); transition: width .3s;
        }
        .contrat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(58,1,92,.12); }
        .contrat-card:hover::before { width: 6px; }
        .contrat-card.status-actif::before { background: linear-gradient(180deg, #10b981, #34d399); }
        .contrat-card.status-expire::before { background: linear-gradient(180deg, #ef4444, #f87171); }
        .contrat-card.status-attente::before { background: linear-gradient(180deg, #f59e0b, #fbbf24); }
        .contrat-card.status-resilie::before { background: linear-gradient(180deg, #6b7280, #9ca3af); }

        .contrat-body { padding: 20px 22px 16px 26px; flex: 1; }

        .contrat-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
        .contrat-header-left { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .contrat-ref {
            font-size: .78rem; font-family: 'Courier New', monospace; font-weight: 700; letter-spacing: .02em;
            color: var(--dark-amethyst-3); background: var(--surface-2); padding: 5px 12px;
            border-radius: 20px; border: 1px solid rgba(58,1,92,.06);
        }
        .contrat-icon {
            width: 44px; height: 44px; border-radius: 12px;
            background: linear-gradient(135deg, rgba(99,102,241,.12), rgba(139,92,246,.12));
            color: #6366f1; display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; flex-shrink: 0; transition: all .3s;
        }
        .contrat-card:hover .contrat-icon {
            background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white;
            transform: scale(1.08); box-shadow: 0 4px 12px rgba(99,102,241,.3);
        }

        .contrat-client {
            font-size: 1.05rem; color: var(--dark-amethyst-3); font-weight: 700;
            margin: 0 0 14px; line-height: 1.35; display: flex; align-items: center; gap: 8px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .contrat-client i { color: #8b5cf6; font-size: .9rem; flex-shrink: 0; }

        .contrat-dates {
            display: flex; gap: 16px; margin-bottom: 12px; padding: 10px 14px;
            background: var(--surface-2); border-radius: 10px; font-size: .82rem;
        }
        .contrat-date-item { display: flex; align-items: center; gap: 6px; color: var(--text-muted); }
        .contrat-date-item i { font-size: .75rem; }
        .contrat-date-item.date-start i { color: #10b981; }
        .contrat-date-item.date-end i { color: #ef4444; }
        .contrat-date-item strong { color: var(--dark-amethyst-3); font-weight: 600; }
        .contrat-date-item.expired strong { color: #ef4444; }

        .contrat-details { display: flex; flex-direction: column; gap: 6px; font-size: .84rem; color: var(--text-muted); }
        .contrat-details p { margin: 0; display: flex; align-items: center; gap: 8px; }
        .contrat-details i { color: #8b5cf6; width: 15px; text-align: center; font-size: .8rem; }
        .contrat-details strong { color: var(--dark-amethyst-3); font-weight: 600; min-width: 42px; }

        .contrat-montant {
            display: inline-flex; align-items: baseline; gap: 4px; margin-top: 10px;
            background: linear-gradient(135deg, rgba(16,185,129,.08), rgba(52,211,153,.08));
            padding: 6px 14px; border-radius: 8px; border: 1px solid rgba(16,185,129,.15);
        }
        .contrat-montant .amount { font-weight: 800; color: #059669; font-size: 1.1rem; }
        .contrat-montant .currency { font-size: .75rem; font-weight: 600; color: #6ee7b7; }

        .contrat-footer {
            padding: 12px 22px 12px 26px; border-top: 1px solid rgba(58,1,92,.05);
            display: flex; justify-content: space-between; align-items: center;
            background: rgba(248,247,252,.5);
        }
        .contrat-footer-text { color: #8b5cf6; font-weight: 600; font-size: .82rem; display: flex; align-items: center; gap: 6px; }
        .contrat-card:hover .contrat-footer-text i { transform: translateX(4px); transition: transform .3s; }

        .action-btn {
            width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center;
            justify-content: center; transition: all .2s; cursor: pointer; border: none;
            text-decoration: none; font-size: .85rem;
        }
        .action-view { background: rgba(33,150,243,.08); color: #2196F3; }
        .action-view:hover { background: rgba(33,150,243,.18); transform: translateY(-2px); }
        .action-edit { background: rgba(99,102,241,.08); color: #6366f1; }
        .action-edit:hover { background: rgba(99,102,241,.18); transform: translateY(-2px); }
        .action-delete { background: rgba(239,68,68,.06); color: #ef4444; }
        .action-delete:hover { background: rgba(239,68,68,.15); transform: translateY(-2px); }

        @media (max-width: 980px) {
            .filter-bar {
                padding: 14px;
                gap: 12px;
            }

            .search-form {
                width: 100%;
                max-width: none;
            }

            .filter-actions {
                width: 100%;
                gap: 8px;
            }

            .filter-actions .btn {
                flex: 1 1 180px;
                text-align: center;
                justify-content: center;
                font-size: .9rem;
                padding: 10px 12px;
            }

            .contrat-grid {
                grid-template-columns: 1fr;
                gap: 14px;
            }
        }

        @media (max-width: 640px) {
            .main-content {
                padding: 10px !important;
            }

            .page-content {
                padding: 0 !important;
            }

            .filter-actions .btn {
                flex: 1 1 calc(50% - 8px);
                min-width: 0;
                border-radius: 20px;
            }

            .contrat-card {
                width: 100%;
                max-width: 100%;
            }

            .contrat-body {
                padding: 16px 14px 12px 16px;
            }

            .contrat-header {
                align-items: flex-start;
                gap: 8px;
            }

            .contrat-header-left {
                max-width: calc(100% - 54px);
            }

            .contrat-ref {
                max-width: 100%;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .contrat-client {
                font-size: .98rem;
                margin-bottom: 10px;
            }

            .contrat-dates {
                padding: 8px 10px;
                gap: 10px;
                flex-wrap: wrap;
            }

            .contrat-footer {
                padding: 10px 14px 10px 16px;
                gap: 8px;
                flex-wrap: wrap;
            }

            .contrat-footer-text {
                margin-left: auto;
                font-size: .78rem;
            }
        }

        @media (max-width: 420px) {
            .filter-actions .btn {
                flex: 1 1 100%;
            }

            .contrat-icon {
                width: 38px;
                height: 38px;
                border-radius: 10px;
                font-size: 1rem;
            }

            .contrat-details {
                font-size: .8rem;
            }
        }
    </style>
</head>
<body>



    <!-- SIDEBAR -->
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <!-- MAIN -->
    <div class="main-content">
        <header>
            <div style="display:flex;align-items:center;gap:20px;">
                <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                <div style="display:flex; flex-direction:column;">
                    <h1 style="margin:0;"><i class="fa-solid fa-file-signature text-accent" style="margin-right:8px;"></i>Liste des Contrats</h1>
                    <span style="font-size:.9rem; color:var(--text-muted); font-weight:600;">Parc de contrats et suivi des échéances</span>
                </div>
            </div>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <span class="badge badge-normal" style="font-size:1rem; padding:8px 16px;"><i class="fa-solid fa-user-tag text-accent"></i> Rôle: <?= ucfirst($role) ?></span>
                <?php include __DIR__ . '/../../includes/notification_ui.php'; ?>
            </div>
        </header>

        <div class="page-content">

            <div class="filter-bar">
                <div class="search-form">
                    <div class="input-group">
                        <i class="fa-solid fa-magnifying-glass input-icon"></i>
                        <input type="text" id="searchInput" placeholder="Chercher un contrat, client, Réf...">
                    </div>
                </div>

                <?php if($can_manage_contracts): ?>
                <div class="filter-actions">
                    <a href="../admin/import_csv.php?type=contrats" class="btn btn-secondary"><i class="fa-solid fa-file-csv"></i> Importer</a>
                    <a href="../admin/import_csv.php?type=sites" class="btn btn-secondary"><i class="fa-solid fa-map-location-dot"></i> Importer Sites</a>
                    <a href="contrat_create.php" class="btn"><i class="fa-solid fa-plus" style="margin-right:8px;"></i>Nouveau Contrat</a>
                </div>
                <?php endif; ?>
            </div>

            <?php if(sqlsrv_has_rows($contrats)): ?>
            <div class="contrat-grid" id="contratsGrid">
                <?php while($c = sqlsrv_fetch_array($contrats, SQLSRV_FETCH_ASSOC)): 
                    $fin = $c['date_fin'];
                    $now = new DateTime();
                    $isExpired = ($fin && $fin < $now);
                    
                    $statusDb = !empty($c['statut']) ? $c['statut'] : ($isExpired ? "Expiré" : "Actif");
                    $s = strtolower($statusDb);
                    
                    $badgeClass = "badge-resolu";
                    $statusClass = "status-actif";
                    if(strpos($s, 'expir') !== false || strpos($s, 'termin') !== false) { $badgeClass = "badge-urgente"; $statusClass = "status-expire"; }
                    if(strpos($s, 'attente') !== false || strpos($s, 'signature') !== false) { $badgeClass = "badge-warning"; $statusClass = "status-attente"; }
                    if($s == 'actif') { $badgeClass = "badge-resolu"; $statusClass = "status-actif"; }
                    if(strpos($s, 'resil') !== false || strpos($s, 'résil') !== false) { $badgeClass = "badge-info"; $statusClass = "status-resilie"; }
                ?>
                <a href="contrat_edit.php?id=<?= urlencode($c['id']) ?>" class="contrat-card <?= $statusClass ?>">
                    <div class="contrat-body">
                        <div class="contrat-header">
                            <div class="contrat-header-left">
                                <span class="contrat-ref"><?= htmlspecialchars($c['numero_contrat']??'N/A') ?></span>
                                <span class="badge <?= $badgeClass ?>" style="padding:3px 10px; font-size:.7rem; border-radius:12px;"><?= strtoupper(htmlspecialchars($statusDb)) ?></span>
                            </div>
                            <div class="contrat-icon"><i class="fa-solid fa-file-contract"></i></div>
                        </div>

                        <h3 class="contrat-client"><i class="fa-regular fa-building"></i> <?= htmlspecialchars($c['client_nom'] ?? 'Client inconnu') ?></h3>
                        <div class="contrat-details" style="margin-bottom:12px;">
                            <p><i class="fa-solid fa-hashtag"></i> <strong>Code client</strong> <?= htmlspecialchars($c['client_code'] ?? $c['client_id'] ?? '—') ?></p>
                        </div>

                        <div class="contrat-dates">
                            <div class="contrat-date-item date-start">
                                <i class="fa-solid fa-play"></i>
                                <strong><?= $c['date_debut'] ? $c['date_debut']->format('d/m/Y') : '—' ?></strong>
                            </div>
                            <span style="color:var(--border);">→</span>
                            <div class="contrat-date-item date-end <?= $isExpired ? 'expired' : '' ?>">
                                <i class="fa-solid fa-flag-checkered"></i>
                                <strong><?= $c['date_fin'] ? $c['date_fin']->format('d/m/Y') : '—' ?></strong>
                            </div>
                        </div>

                        <div class="contrat-details">
                            <?php if(!empty($c['categorie_contrat'])): ?>
                                <p><i class="fa-solid fa-tag"></i> <strong>Type</strong> <?= htmlspecialchars($c['categorie_contrat']) ?></p>
                            <?php endif; ?>
                            <?php if(!empty($c['vp'])): ?>
                                <p><i class="fa-solid fa-user-tie"></i> <strong>VP</strong> <?= htmlspecialchars($c['vp']) ?></p>
                            <?php endif; ?>
                            <?php if(!empty($c['mode_facturation'])): ?>
                                <p><i class="fa-solid fa-receipt"></i> <strong>Fact.</strong> <?= htmlspecialchars($c['mode_facturation']) ?></p>
                            <?php endif; ?>
                        </div>

                        <?php if($can_view_amount && ($c['montant_annuel'] ?? 0) > 0): ?>
                            <div class="contrat-montant">
                                <span class="amount"><?= number_format($c['montant_annuel'], 2, ',', ' ') ?></span>
                                <span class="currency">MAD</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="contrat-footer">
                        <div style="display:flex; gap:6px;">
                            <span class="action-btn action-view" title="Fiche Client"><i class="fa-solid fa-address-book"></i></span>
                            <span class="action-btn action-edit" title="Modifier"><i class="fa-solid fa-pen-to-square"></i></span>
                            <?php if($can_manage_contracts): ?>
                            <span class="action-btn action-delete" onclick="event.preventDefault(); if(confirm('Supprimer ce contrat ?')) window.location='contrat_delete.php?id=<?= $c['id'] ?>';" title="Supprimer"><i class="fa-solid fa-trash-can"></i></span>
                            <?php endif; ?>
                        </div>
                        <span class="contrat-footer-text">Voir le contrat <i class="fa-solid fa-arrow-right"></i></span>
                    </div>
                </a>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div style="background:var(--surface); border-radius:var(--r-md); padding:60px 20px; text-align:center; border:1px dashed rgba(58,1,92,.2);">
                <i class="fa-solid fa-folder-open" style="font-size:4rem; color:var(--text-muted); opacity:.5; margin-bottom:24px;"></i>
                <h3 style="color:var(--dark-amethyst-3); margin-top:0;">Aucun contrat enregistré</h3>
                <p style="color:var(--text-muted); max-width:400px; margin:0 auto;">La base de données des contrats est vide.</p>
            </div>
            <?php endif; ?>


        </div>
    </div>

    <!-- JS Basics -->
    <script>
        document.getElementById('menuBtn').addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        });
        document.getElementById('sidebarOverlay').addEventListener('click', () => {
            document.getElementById('sidebar').classList.remove('active');
            document.getElementById('sidebarOverlay').classList.remove('active');
        });

        // Search Filter for Cards
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase().trim();
            let cards = document.querySelectorAll('#contratsGrid .contrat-card');
            
            cards.forEach(card => {
                let text = card.textContent.toLowerCase();
                if(text.includes(filter)) {
                    card.style.display = '';
                    card.style.animation = 'fadeIn 0.3s ease-out';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>

