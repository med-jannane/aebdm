<?php
// Determine the user's role
$role = $_SESSION['role'] ?? '';

// Determine current script and directory to highlight the active menu item
$current_script = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
?>
<div class="sidebar" id="sidebar">
    <?php if ($role === 'directeur' || $role === 'admin'): ?>
        <h2><i class="fa-solid fa-shapes"></i> SAV Admin</h2>
        <nav>
            <ul>
                <li><a href="../admin/dashboard.php" class="<?= ($current_dir === 'admin' && $current_script === 'dashboard.php') ? 'active' : '' ?>"><i class="fa-solid fa-chart-line"></i> Vue Globale</a></li>
                <li><a href="../admin/statistics.php" class="<?= ($current_dir === 'admin' && $current_script === 'statistics.php') ? 'active' : '' ?>"><i class="fa-solid fa-chart-simple"></i> Statistiques & Export</a></li>
                <li><a href="../admin/users.php" class="<?= ($current_dir === 'admin' && in_array($current_script, ['users.php', 'user_create.php', 'user_edit.php'])) ? 'active' : '' ?>"><i class="fa-solid fa-users-gear"></i> Utilisateurs</a></li>
                <li><a href="../commercial/clients.php" class="<?= ($current_dir === 'commercial' && in_array($current_script, ['clients.php', 'client_details.php', 'client_edit.php', 'site_edit.php'])) ? 'active' : '' ?>"><i class="fa-solid fa-building-user"></i> Clients & Sites</a></li>
                <li><a href="../commercial/contrats.php" class="<?= ($current_dir === 'commercial' && in_array($current_script, ['contrats.php', 'contrat_create.php', 'contrat_edit.php'])) ? 'active' : '' ?>"><i class="fa-solid fa-file-contract"></i> Contrats</a></li>
                <li><a href="../admin/repair_imported_contrat.php" class="<?= ($current_dir === 'admin' && $current_script === 'repair_imported_contrat.php') ? 'active' : '' ?>"><i class="fa-solid fa-file-pen"></i> Réparer Contrat Importé</a></li>
                <li><a href="../admin/fix_contrat_fields_confusion.php" class="<?= ($current_dir === 'admin' && $current_script === 'fix_contrat_fields_confusion.php') ? 'active' : '' ?>"><i class="fa-solid fa-wrench"></i> Corriger Champs Contrats</a></li>
                <li><a href="../admin/system_logs.php" class="<?= ($current_dir === 'admin' && $current_script === 'system_logs.php') ? 'active' : '' ?>"><i class="fa-solid fa-timeline"></i> Logs Système</a></li>
                <li><a href="../../auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Déconnexion</a></li>
            </ul>
        </nav>
    <?php elseif ($role === 'commercial'): ?>
        <h2><i class="fa-solid fa-briefcase"></i> SAV Commercial</h2>
        <nav>
            <ul>
                <li><a href="../commercial/dashboard.php" class="<?= ($current_dir === 'commercial' && $current_script === 'dashboard.php') ? 'active' : '' ?>"><i class="fa-solid fa-chart-line"></i> Tableau de Bord</a></li>
                <li><a href="../commercial/clients.php" class="<?= ($current_dir === 'commercial' && in_array($current_script, ['clients.php', 'client_details.php', 'client_edit.php', 'site_edit.php'])) ? 'active' : '' ?>"><i class="fa-solid fa-building-user"></i> Clients</a></li>
                <li><a href="../commercial/contrats.php" class="<?= ($current_dir === 'commercial' && in_array($current_script, ['contrats.php', 'contrat_create.php', 'contrat_edit.php'])) ? 'active' : '' ?>"><i class="fa-solid fa-file-contract"></i> Contrats</a></li>
                <li><a href="../admin/import_csv.php?type=contrats" class="<?= ($current_dir === 'admin' && $current_script === 'import_csv.php') ? 'active' : '' ?>"><i class="fa-solid fa-upload"></i> Importer Contrats</a></li>
                <li><a href="../../auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Déconnexion</a></li>
            </ul>
        </nav>
    <?php elseif ($role === 'accueil'): ?>
        <h2><i class="fa-solid fa-headset"></i> SAV Accueil</h2>
        <nav>
            <ul>
                <li><a href="../accueil/dashboard.php" class="<?= ($current_dir === 'accueil' && $current_script === 'dashboard.php') ? 'active' : '' ?>"><i class="fa-solid fa-chart-line"></i> Tableau de Bord</a></li>
                <li><a href="../accueil/tickets.php" class="<?= ($current_dir === 'accueil' && in_array($current_script, ['tickets.php', 'ticket_create.php', 'ticket_edit.php', 'ticket_details.php', 'create_ticket_form.php', 'create_ticket_search.php'])) ? 'active' : '' ?>"><i class="fa-solid fa-ticket"></i> Gérer les Tickets</a></li>
                <li><a href="../accueil/historique_site.php" class="<?= ($current_dir === 'accueil' && $current_script === 'historique_site.php') ? 'active' : '' ?>"><i class="fa-solid fa-clock-rotate-left"></i> Historique Sites</a></li>
                <li><a href="../accueil/commandes.php" class="<?= ($current_dir === 'accueil' && in_array($current_script, ['commandes.php', 'commande_create.php'])) ? 'active' : '' ?>"><i class="fa-solid fa-cart-shopping"></i> Commandes (NAV)</a></li>
                <li><a href="../commercial/clients.php" class="<?= ($current_dir === 'commercial' && in_array($current_script, ['clients.php', 'client_details.php', 'client_edit.php', 'site_edit.php'])) ? 'active' : '' ?>"><i class="fa-solid fa-building-user"></i> Clients & Sites</a></li>
                <li><a href="../commercial/contrats.php" class="<?= ($current_dir === 'commercial' && in_array($current_script, ['contrats.php', 'contrat_create.php', 'contrat_edit.php'])) ? 'active' : '' ?>"><i class="fa-solid fa-file-contract"></i> Contrats</a></li>
                <li><a href="../accueil/audit_imports.php" class="<?= ($current_dir === 'accueil' && $current_script === 'audit_imports.php') ? 'active' : '' ?>"><i class="fa-solid fa-clipboard-list"></i> Audit Imports</a></li>
                <li><a href="../../auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Déconnexion</a></li>
            </ul>
        </nav>
    <?php elseif ($role === 'dispatch'): ?>
        <h2><i class="fa-solid fa-shapes"></i> SAV Dispatch</h2>
        <nav>
            <ul>
                <li><a href="../dispatch/dashboard.php" class="<?= ($current_dir === 'dispatch' && $current_script === 'dashboard.php') ? 'active' : '' ?>"><i class="fa-solid fa-calendar-check"></i> À Planifier</a></li>
                <li><a href="../dispatch/interventions_list.php" class="<?= ($current_dir === 'dispatch' && in_array($current_script, ['interventions_list.php', 'plan_intervention.php', 'assign_tech.php'])) ? 'active' : '' ?>"><i class="fa-solid fa-calendar-days"></i> Planning Global</a></li>
                <li><a href="../commercial/clients.php" class="<?= ($current_dir === 'commercial' && in_array($current_script, ['clients.php', 'client_details.php', 'client_edit.php', 'site_edit.php'])) ? 'active' : '' ?>"><i class="fa-solid fa-building-user"></i> Clients & Sites</a></li>
                <li><a href="../commercial/contrats.php" class="<?= ($current_dir === 'commercial' && in_array($current_script, ['contrats.php', 'contrat_create.php', 'contrat_edit.php'])) ? 'active' : '' ?>"><i class="fa-solid fa-file-contract"></i> Contrats</a></li>
                <li><a href="../../auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Déconnexion</a></li>
            </ul>
        </nav>
    <?php elseif ($role === 'tac'): ?>
        <?php
        $routeModeKey = 'route_accueil_direct_dispatch';
        $isDirectDispatchMode = get_app_setting($routeModeKey, '0') === '1';
        ?>
        <h2><i class="fa-solid fa-shapes"></i> SAV TAC</h2>
        <nav>
            <ul>
                <li><a href="../tac/dashboard.php" class="<?= ($current_dir === 'tac' && in_array($current_script, ['dashboard.php', 'my_tickets.php', 'tickets_list.php', 'ticket_detail.php', 'ticket_process.php'])) ? 'active' : '' ?>"><i class="fa-solid fa-list-check"></i> Tableau de Bord</a></li>
                <li>
                    <form method="POST" action="../tac/dashboard.php" style="margin:0;">
                        <input type="hidden" name="action" value="toggle_direct_dispatch_mode">
                        <input type="hidden" name="mode" value="<?= $isDirectDispatchMode ? '0' : '1' ?>">
                        <button type="submit" class="btn btn-sm <?= $isDirectDispatchMode ? '' : 'btn-secondary' ?>" style="width:100%; text-align:left; justify-content:flex-start;">
                            <i class="fa-solid <?= $isDirectDispatchMode ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                            Routage Direct Dispatch: <?= $isDirectDispatchMode ? 'ON' : 'OFF' ?>
                        </button>
                    </form>
                </li>
                <li><a href="../tac/historique_site.php" class="<?= ($current_dir === 'tac' && $current_script === 'historique_site.php') ? 'active' : '' ?>"><i class="fa-solid fa-clock-rotate-left"></i> Historique Sites</a></li>
                <li><a href="../commercial/clients.php" class="<?= ($current_dir === 'commercial' && in_array($current_script, ['clients.php', 'client_details.php', 'client_edit.php', 'site_edit.php'])) ? 'active' : '' ?>"><i class="fa-solid fa-building-user"></i> Clients & Sites</a></li>
                <li><a href="../commercial/contrats.php" class="<?= ($current_dir === 'commercial' && in_array($current_script, ['contrats.php', 'contrat_create.php', 'contrat_edit.php'])) ? 'active' : '' ?>"><i class="fa-solid fa-file-contract"></i> Contrats</a></li>
                <li><a href="../../auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Déconnexion</a></li>
            </ul>
        </nav>
    <?php elseif ($role === 'tech'): ?>
        <h2><i class="fa-solid fa-shapes"></i> SAV Tech</h2>
        <nav>
            <ul>
                <li><a href="../tech/dashboard.php" class="<?= ($current_dir === 'tech' && in_array($current_script, ['dashboard.php', 'report_intervention.php'])) ? 'active' : '' ?>"><i class="fa-solid fa-truck"></i> Mes Interventions</a></li>
                <li><a href="../commercial/clients.php" class="<?= ($current_dir === 'commercial' && in_array($current_script, ['clients.php', 'client_details.php', 'client_edit.php', 'site_edit.php'])) ? 'active' : '' ?>"><i class="fa-solid fa-building-user"></i> Clients & Sites</a></li>
                <li><a href="../tech/history.php" class="<?= ($current_dir === 'tech' && $current_script === 'history.php') ? 'active' : '' ?>"><i class="fa-solid fa-clock-rotate-left"></i> Historique</a></li>
                <li><a href="../../auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Déconnexion</a></li>
            </ul>
        </nav>
    <?php elseif ($role === 'charge_de_compte'): ?>
        <h2><i class="fa-solid fa-users-viewfinder"></i> SAV Charge Compte</h2>
        <nav>
            <ul>
                <li><a href="../charge_compte/dashboard.php" class="<?= ($current_dir === 'charge_compte' && $current_script === 'dashboard.php') ? 'active' : '' ?>"><i class="fa-solid fa-chart-line"></i> Tableau de Bord</a></li>
                <li><a href="../charge_compte/affectations.php" class="<?= ($current_dir === 'charge_compte' && $current_script === 'affectations.php') ? 'active' : '' ?>"><i class="fa-solid fa-calendar-check"></i> Affecter Interventions</a></li>
                <li><a href="../charge_compte/interventions.php" class="<?= ($current_dir === 'charge_compte' && $current_script === 'interventions.php') ? 'active' : '' ?>"><i class="fa-solid fa-screwdriver-wrench"></i> Interventions Equipe</a></li>
                <li><a href="../charge_compte/visites_preventives.php" class="<?= ($current_dir === 'charge_compte' && $current_script === 'visites_preventives.php') ? 'active' : '' ?>"><i class="fa-solid fa-shield-heart"></i> Visites Preventives</a></li>
                <li><a href="../charge_compte/statistiques.php" class="<?= ($current_dir === 'charge_compte' && $current_script === 'statistiques.php') ? 'active' : '' ?>"><i class="fa-solid fa-chart-column"></i> Statistiques Equipe</a></li>
                <li><a href="../charge_compte/techniciens.php" class="<?= ($current_dir === 'charge_compte' && in_array($current_script, ['techniciens.php', 'technicien_detail.php'])) ? 'active' : '' ?>"><i class="fa-solid fa-people-group"></i> Techniciens</a></li>
                <li><a href="../commercial/clients.php" class="<?= ($current_dir === 'commercial' && in_array($current_script, ['clients.php', 'client_details.php', 'client_edit.php', 'site_edit.php'])) ? 'active' : '' ?>"><i class="fa-solid fa-building-user"></i> Clients & Sites</a></li>
                <li><a href="../../auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Deconnexion</a></li>
            </ul>
        </nav>
    <?php endif; ?>
    <div class="sidebar-footer">© <?= date('Y') ?> AEBDM SAV</div>
</div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
