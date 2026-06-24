<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';
require_once __DIR__ . '/../../utils/Logger.php';

check_role('admin');

$pageTitle = "Logs Système";

Logger::log('Accès Page', 'L\'administrateur a consulté les logs système.');

// Pagination & Filtres
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
$logs = Logger::getLogs($limit);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <!-- DataTables pour trier facilement -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <style>
        .table-wrap {
            background: var(--surface); border-radius: var(--r-md); padding: 0; overflow: hidden;
            box-shadow: 0 4px 15px rgba(24,8,44,.05); border: 1px solid rgba(58,1,92,.08);
        }
        .table-wrap table { margin: 0; width: 100%; border-collapse: collapse; }
        .table-wrap th { background: var(--surface-2); border-bottom: 2px solid rgba(58,1,92,.08); text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.05em; color: var(--text-muted); padding: 16px; text-align: left;}
        .table-wrap td { vertical-align: middle; padding: 14px 16px; border-bottom: 1px solid rgba(58,1,92,.04); }
        .table-wrap tr:last-child td { border-bottom: none; }
        
        .role-badge { 
            padding: 4px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; 
            display: inline-flex; align-items: center; justify-content: center;
        }

        /* DT Overrides */
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 6px 16px;
            background: var(--background);
            font-family: inherit;
        }
        .dataTables_wrapper .dataTables_filter input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(155,93,229,.1);
        }
        .dataTables_wrapper .dataTables_length select {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 4px 8px;
            font-family: inherit;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current, .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: var(--primary);
            color: white !important;
            border: 1px solid var(--dark-amethyst);
            border-radius: 8px;
        }
        table.dataTable.no-footer { border-bottom: none; }
        .dataTables_wrapper { padding: 20px; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header>
            <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
                <div style="display:flex; align-items:center; gap:20px;">
                    <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                    <div style="display:flex; flex-direction:column;">
                        <h1 style="margin:0;"><i class="fa-solid fa-server text-accent" style="margin-right:8px;"></i>Journal Système</h1>
                        <span style="font-size:.9rem; color:var(--text-muted); font-weight:600;">Traçabilité & Événements</span>
                    </div>
                </div>
            </div>
        </header>

        <div class="page-content">
            
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:16px;">
                <p style="color:var(--text-muted); font-size:1.05rem; margin:0;"><i class="fa-solid fa-shield-halved" style="color:var(--primary); margin-right:8px;"></i> Suivi des actions de la plateforme d'Assistance.</p>
                <form method="GET" style="display:flex; align-items:center; gap:12px; background:var(--surface); padding:8px 16px; border-radius:30px; border:1px solid rgba(58,1,92,.08); box-shadow:0 2px 10px rgba(24,8,44,.05);">
                    <i class="fa-solid fa-filter text-muted"></i>
                    <label style="margin:0; font-size:.9rem; font-weight:600; color:var(--text); white-space:nowrap;">Afficher :</label>
                    <select name="limit" style="border:none; background:transparent; font-weight:700; color:var(--primary); font-family:inherit; outline:none; cursor:pointer;" onchange="this.form.submit()">
                        <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50 derniers</option>
                        <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100 derniers</option>
                        <option value="500" <?= $limit == 500 ? 'selected' : '' ?>>500 derniers</option>
                        <option value="1000" <?= $limit == 1000 ? 'selected' : '' ?>>1000 derniers</option>
                    </select>
                </form>
            </div>

            <div class="table-wrap">
                <table id="logsTable" class="display" style="width:100%;">
                    <thead>
                        <tr>
                            <th style="width: 15%">Horodatage</th>
                            <th style="width: 20%">Utilisateur (Auteur)</th>
                            <th style="width: 20%">Action / Événement</th>
                            <th style="width: 45%">Détails techniques</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <div style="font-family:monospace; font-size:.9rem; color:var(--text-muted); background:rgba(0,0,0,.03); padding:4px 8px; border-radius:4px; display:inline-block;">
                                        <i class="fa-regular fa-clock" style="opacity:.6; margin-right:4px;"></i>
                                        <?= $log['created_at'] instanceof DateTime ? $log['created_at']->format('d/m/Y <strong style="color:var(--text);">H:i:s</strong>') : htmlspecialchars($log['created_at']) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($log['user_name']): ?>
                                        <div style="font-weight:700; color:var(--dark-amethyst-3);"><i class="fa-solid fa-user-astronaut text-muted"></i> <?= htmlspecialchars($log['user_name']) ?></div>
                                        <div style="margin-top:4px;"><span class="badge badge-normal" style="font-size:.7rem; letter-spacing:1px;"><?= strtoupper($log['user_role']) ?></span></div>
                                    <?php else: ?>
                                        <span class="badge" style="background:var(--surface-2); color:var(--text-muted);"><i class="fa-solid fa-robot"></i> Système Automatisé</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                        $actionStr = $log['action'];
                                        $bClass = 'badge-normal';
                                        if (stripos($actionStr, 'suppr') !== false || stripos($actionStr, 'erreur') !== false) $bClass = 'badge-urgente';
                                        if (stripos($actionStr, 'créa') !== false || stripos($actionStr, 'ajout') !== false) $bClass = 'badge-resolu';
                                        if (stripos($actionStr, 'modif') !== false || stripos($actionStr, 'authenti') !== false) $bClass = 'badge-warning';
                                    ?>
                                    <span class="badge <?= $bClass ?>"><?= strtoupper(htmlspecialchars($actionStr)) ?></span>
                                </td>
                                <td>
                                    <div style="background:rgba(0,0,0,.02); border-left:3px solid var(--border); padding:8px 12px; font-size:.9rem; color:var(--text); border-radius:0 4px 4px 0; line-height:1.5;">
                                        <?= nl2br(htmlspecialchars($log['description'])) ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
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

        $(document).ready(function() {
            $('#logsTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json"
                },
                "order": [[0, 'desc']], // Tri par date décroissante
                "pageLength": 25,
                "lengthMenu": [10, 25, 50, 100],
                "dom": '<"top"f>rt<"bottom"lip><"clear">',
                "drawCallback": function(settings) {
                    $('.dataTables_wrapper .top').css('display', 'flex').css('justify-content', 'flex-end').css('margin-bottom', '16px');
                    $('.dataTables_wrapper .bottom').css('display', 'flex').css('justify-content', 'space-between').css('align-items', 'center').css('margin-top', '16px');
                }
            });
        });
    </script>
</body>
</html>
