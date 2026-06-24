<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['accueil', 'admin']);

$error = "";
$success = "";


// Initialiser les clients pour la liste
$clients = query("SELECT ID_Client as id, Nom as nom, Ville as ville, ID_Client as code_client, TEL as telephone1
                  FROM SAV_Clients
                  ORDER BY Nom");
$clientsList = fetchAll($clients);

$preselected_client = isset($_GET['client_id']) ? $_GET['client_id'] : '';
$preselected_site = isset($_GET['site_id']) ? $_GET['site_id'] : '';

// Check if there's a success message from redirection
if (isset($_GET['msg']) && $_GET['msg'] === 'created' && isset($_GET['new_id'])) {
    $success = "✅ Le ticket #" . htmlspecialchars($_GET['new_id']) . " a été créé avec succès.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $isDirectDispatchMode = get_app_setting('route_accueil_direct_dispatch', '0') === '1';
    $initialStatus = $isDirectDispatchMode ? 'attente_dispatch' : 'ouvert';

    $client_id = $_POST['client_id'];
    $site_id = !empty($_POST['site_id']) ? $_POST['site_id'] : null;
    $contrat_id = !empty($_POST['contrat_id']) ? $_POST['contrat_id'] : null;

    $sujet = $_POST['sujet'];
    $type = $_POST['type_probleme']; // name adjusted to match DB
    $desc = $_POST['description'];
    $prio = $_POST['priorite'];

    $contact_source = $_POST['contact_source'];
    $contact_nom = $_POST['contact_nom'] ?? '';
    $contact_tel = $_POST['contact_tel'] ?? '';
    // Combiner pour stockage base de données (format: Nom - Tel)
    $contact_sur_place = trim($contact_nom . ' ' . $contact_tel);

    if (empty($client_id) || empty($desc) || empty($sujet)) {
        $error = "Client, Sujet et Description sont obligatoires.";
    } else {
        $id_ticket = uniqid('TIC-');
        $code = 'TIC-' . date('Ymd-His');

        // Assembler les informations non supportées directement par le schéma dans le commentaire
        $full_comment = "[Contrat: " . ($contrat_id ?? 'Aucun') . "]\n" .
                        "[Type: " . $type . "]\n" .
                        "[Contact Source: " . $contact_source . "]\n\n" .
                        $desc;

        if (empty($site_id)) {
            $sql = "INSERT INTO TICKET (
                ID_TICKET, ID_USER, ID_CLIENT, CODE, DATE,
                OBJET, COMMENT, PRIORITE, ETAT, NOM_USER, TEL_USER
            ) VALUES (?, ?, ?, ?, GETDATE(), ?, ?, ?, ?, ?, ?)";

            $params = [
                $id_ticket, $_SESSION['user_id'], $client_id, $code,
                $sujet, $full_comment, $prio, $initialStatus, $contact_nom, $contact_tel
            ];
        } else {
            $sql = "INSERT INTO TICKET (
                ID_TICKET, ID_USER, ID_CLIENT, ID_SITE, CODE, DATE,
                OBJET, COMMENT, PRIORITE, ETAT, NOM_USER, TEL_USER
            ) VALUES (?, ?, ?, ?, ?, GETDATE(), ?, ?, ?, ?, ?, ?)";

            $params = [
                $id_ticket, $_SESSION['user_id'], $client_id, $site_id, $code,
                $sujet, $full_comment, $prio, $initialStatus, $contact_nom, $contact_tel
            ];
        }

        if (sqlsrv_query($conn, $sql, $params)) {
             // Create Notification for TAC
            require_once __DIR__ . '/../../utils/NotificationManager.php';
            $nm = new NotificationManager($conn);

            $newTicketId = $id_ticket; // Utilisation de l'ID VARCHAR généré

            if ($isDirectDispatchMode) {
                $nm->create("Ticket #$newTicketId - Nouveau ticket à planifier (routage direct Accueil).", 'dispatch', null, "/sav/src/modules/dispatch/assign_tech.php?ticket_id=$newTicketId");
            } else {
                $nm->create("Ticket #$newTicketId - Nouveau ticket créé par l'Accueil, à traiter par le TAC.", 'tac', null, "/sav/src/modules/tac/ticket_process.php?id=$newTicketId");
            }
            $nm->create("Ticket #$newTicketId - Nouveau ticket créé par l'Accueil.", 'admin', null, "/sav/src/modules/accueil/tickets.php");

            header("Location: ticket_create.php?msg=created&new_id=" . urlencode($newTicketId)); // Redirect to stay and show success
            exit;
        } else {
            error_log('[ACCUEIL_TICKET_CREATE] ' . db_last_error_message());
            $error = "Erreur lors de la creation du ticket.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <style>
        .card {
            border-radius: 24px;
            box-shadow: 0 18px 50px rgba(18, 7, 42, .08);
            border: 1px solid rgba(58,1,92,.08);
            background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(255,255,255,.96));
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 18px;
        }
        .form-col { min-width: 0; }
        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 12px;
            margin-bottom: 18px;
            border-bottom: 1px solid var(--border);
        }
        .section-title i {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(16, 0, 43, .08);
            color: var(--primary);
            flex-shrink: 0;
        }
        .section-title h3 {
            margin: 0;
            border: none !important;
            padding: 0 !important;
        }
        .ticket-form {
            display: flex;
            flex-direction: column;
            gap: 22px;
        }
        .field-shell {
            position: relative;
            border-radius: 18px;
            padding: 14px;
            background: var(--surface-2);
            border: 1px solid rgba(0,0,0,.08);
            transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
        }
        .field-shell:focus-within {
            transform: translateY(-1px);
            border-color: rgba(16, 0, 43, .18);
            box-shadow: 0 10px 26px rgba(16, 0, 43, .08);
        }
        .search-top-input {
            width: 100%;
            padding: 12px 14px;
            border-radius: 16px;
            border: 1px solid var(--border);
            background: var(--surface);
            transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
            outline: none;
        }
        .search-top-input:focus {
            border-color: rgba(16, 0, 43, .22);
            box-shadow: 0 0 0 4px rgba(16, 0, 43, .08);
        }
        .autocomplete-field { position: relative; }
        .autocomplete-panel {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            z-index: 40;
            max-height: 190px;
            overflow-y: auto;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: 0 18px 34px rgba(0,0,0,.14);
            display: none;
            backdrop-filter: blur(10px);
        }
        .autocomplete-option {
            padding: 12px 14px;
            border-bottom: 1px solid rgba(0,0,0,.06);
            cursor: pointer;
            font-size: .95rem;
            line-height: 1.35;
            transition: background .15s ease, padding-left .15s ease;
        }
        .autocomplete-option:last-child { border-bottom: none; }
        .autocomplete-option:hover,
        .autocomplete-option.active {
            background: rgba(16, 0, 43, 0.08);
            padding-left: 16px;
        }
        .autocomplete-option strong {
            display: block;
            font-size: .95rem;
            margin-bottom: 3px;
        }
        .autocomplete-option .option-meta {
            display: block;
            font-size: .82rem;
            color: var(--text-muted);
        }
        .autocomplete-empty {
            padding: 12px 14px;
            color: var(--text-muted);
            font-size: .92rem;
        }
        .form-label-inline {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-weight: 700;
            color: var(--dark-amethyst-3);
        }
        .form-label-inline i { color: var(--primary); }
        .submit-bar {
            display: flex;
            justify-content: flex-end;
            padding-top: 8px;
        }
        .submit-bar .btn {
            min-width: 220px;
            border-radius: 18px;
            box-shadow: 0 12px 28px rgba(16, 0, 43, .18);
        }
        @media (max-width: 900px) {
            .form-row { grid-template-columns: 1fr; gap: 12px; }
            .submit-bar { justify-content: stretch; }
            .submit-bar .btn { width: 100%; min-width: 0; }
            .autocomplete-field {
                display: flex;
                flex-direction: column;
            }
            .autocomplete-panel {
                position: static;
                top: auto;
                left: auto;
                right: auto;
                width: 100%;
                margin-top: 8px;
                max-height: 150px;
                border-radius: 16px;
                box-shadow: 0 10px 22px rgba(0,0,0,.10);
            }
        }

        @media (max-width: 640px) {
            .card {
                border-radius: 20px;
                box-shadow: 0 12px 32px rgba(18, 7, 42, .08);
            }

            .section-title {
                margin-bottom: 14px;
                padding-bottom: 10px;
            }

            .field-shell {
                padding: 12px;
                border-radius: 16px;
            }

            .search-top-input {
                padding: 11px 12px;
                border-radius: 14px;
            }

            .autocomplete-panel {
                max-height: 140px;
                border-radius: 16px;
            }
            .autocomplete-option {
                padding: 10px 12px;
                font-size: .9rem;
            }
            .autocomplete-option strong {
                font-size: .9rem;
            }
            .autocomplete-option .option-meta {
                font-size: .78rem;
            }
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header>
            <div style="display:flex; align-items:center; gap:20px;">
                <button class="mobile-toggle"><i class="fa-solid fa-bars"></i></button>
                <h1>Nouveau Ticket</h1>
            </div>
            <a href="dashboard.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Retour</a>
        </header>

        <?php if($error): ?><div class="card" style="color:red; background:rgba(239, 68, 68, 0.1); border-left:4px solid var(--danger);"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?></div><?php endif; ?>

        <div class="card">
            <form method="POST" class="ticket-form">

                <!-- CLIENT SECTION -->
                <div class="section-title">
                    <i class="fa-solid fa-user-tag"></i>
                    <h3 style="color:var(--primary);">Identification Client</h3>
                </div>

                <!-- DYNAMIC DETAIL SECTION -->
                <div class="form-row">
                    <div class="form-col form-group">
                        <label class="form-label-inline"><i class="fa-solid fa-magnifying-glass"></i> Rechercher Client *</label>
                        <div class="autocomplete-field field-shell">
                            <input type="text" id="client_search" class="search-top-input" placeholder="Tapez pour filtrer les clients..." autocomplete="off">
                            <input type="hidden" name="client_id" id="client_id">
                            <div id="client_panel" class="autocomplete-panel"></div>
                        </div>
                    </div>
                    <div class="form-col form-group">
                        <label class="form-label-inline"><i class="fa-solid fa-location-dot"></i> Rechercher Site</label>
                        <div class="autocomplete-field field-shell">
                            <input type="text" id="site_search" class="search-top-input" placeholder="Tapez pour filtrer les sites..." autocomplete="off">
                            <input type="hidden" name="site_id" id="site_id">
                            <div id="site_panel" class="autocomplete-panel"></div>
                        </div>
                    </div>
                    <div class="form-col form-group">
                        <label class="form-label-inline"><i class="fa-solid fa-file-contract"></i> Rechercher Contrat (Optionnel)</label>
                        <div class="autocomplete-field field-shell">
                            <input type="text" id="contrat_search" class="search-top-input" placeholder="Tapez pour filtrer les contrats..." autocomplete="off">
                            <input type="hidden" name="contrat_id" id="contrat_id">
                            <div id="contrat_panel" class="autocomplete-panel"></div>
                        </div>
                    </div>
                </div>

                <div class="section-title">
                    <i class="fa-solid fa-address-book"></i>
                    <h3 style="color:var(--primary);">Contact</h3>
                </div>
                <div class="form-row">
                    <div class="form-col form-group">
                        <label class="form-label-inline"><i class="fa-solid fa-phone"></i> Source du Contact</label>
                        <div style="position:relative;">
                            <i class="fa-solid fa-phone input-icon" style="top:12px; left:10px;"></i>
                            <select name="contact_source" id="contact_source" style="padding-left:35px; width:100%; height:45px; border-radius:var(--radius-md); border:1px solid var(--border);">
                                <option value="telephone">Téléphone</option>
                                <option value="email">Email</option>
                                <option value="fax">Fax</option>
                                <option value="guichet">Guichet/Sur place</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-col form-group">
                        <label class="form-label-inline"><i class="fa-solid fa-user"></i> Nom Contact sur Place</label>
                        <input type="text" name="contact_nom" id="contact_nom" placeholder="Nom du contact" style="width:100%; padding:10px; border-radius:var(--radius-md); border:1px solid var(--border);">
                    </div>
                    <div class="form-col form-group">
                        <label class="form-label-inline"><i class="fa-solid fa-square-phone"></i> Tél Contact sur Place</label>
                        <input type="text" name="contact_tel" id="contact_tel" placeholder="06..." style="width:100%; padding:10px; border-radius:var(--radius-md); border:1px solid var(--border);">
                    </div>
                </div>

                <div class="section-title">
                    <i class="fa-solid fa-circle-question"></i>
                    <h3 style="color:var(--primary);">Détails du Ticket</h3>
                </div>
                <div class="form-group">
                    <label class="form-label-inline"><i class="fa-solid fa-pen-to-square"></i> Sujet / Titre *</label>
                    <input type="text" name="sujet" required placeholder="Ex: Panne Internet, Imprimante bloquée..." style="width:100%; padding:12px 14px; border-radius:16px; border:1px solid var(--border); background:var(--surface);">
                </div>

                <div class="form-row">
                    <div class="form-col form-group">
                        <label class="form-label-inline"><i class="fa-solid fa-tag"></i> Catégorie / Type</label>
                        <select name="type_probleme" style="width:100%; padding:12px 14px; border-radius:16px; border:1px solid var(--border); background:var(--surface);">
                            <option value="Panne Matérielle">Panne Matérielle</option>
                            <option value="Panne Logicielle">Panne Logicielle</option>
                            <option value="Réseau / Internet">Réseau / Internet</option>
                            <option value="Demande d'accès">Demande d'accès</option>
                            <option value="Autre">Autre</option>
                        </select>
                    </div>
                    <div class="form-col form-group">
                        <label class="form-label-inline"><i class="fa-solid fa-triangle-exclamation"></i> Priorité</label>
                        <select name="priorite" required style="width:100%; padding:12px 14px; border-radius:16px; border:1px solid var(--border); background:var(--surface);">
                            <option value="normale">Normale</option>
                            <option value="haute">Haute</option>
                            <option value="urgente" style="color:red; font-weight:bold;">URGENTE</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label-inline"><i class="fa-solid fa-align-left"></i> Description Détaillée *</label>
                    <textarea name="description" rows="5" required placeholder="Décrire le problème..." style="width:100%; padding:14px; border-radius:18px; border:1px solid var(--border); font-family:inherit; background:var(--surface); resize:vertical; min-height:140px;"></textarea>
                </div>

                <div class="submit-bar">
                    <button type="submit" class="btn btn-full"><i class="fa-solid fa-paper-plane"></i> Créer ticket</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        const preselectedSiteId = "<?php echo $preselected_site; ?>";
        const allClientOptions = <?php
            echo json_encode(array_map(function($c) {
                $display = $c['nom'] . ' (' . ($c['code_client'] ?? '?') . ') - ' . ($c['ville'] ?? '');
                if (!empty($c['telephone1'])) {
                    $display .= ' [' . $c['telephone1'] . ']';
                }
                return [
                    'value' => (string)$c['id'],
                    'text' => $display,
                    'label' => $c['nom']
                ];
            }, $clientsList), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        ?>;

        $(document).ready(function() {
            const $clientInput = $('#client_search');
            const $siteInput = $('#site_search');
            const $contratInput = $('#contrat_search');
            const $clientId = $('#client_id');
            const $siteId = $('#site_id');
            const $contratId = $('#contrat_id');
            const $clientPanel = $('#client_panel');
            const $sitePanel = $('#site_panel');
            const $contratPanel = $('#contrat_panel');

            let currentSites = [];
            let allSiteOptions = [];
            let allContratOptions = [];

            function normalize(text) {
                return String(text || '').toLowerCase().trim();
            }

            function escapeHtml(text) {
                return String(text || '').replace(/[&<>"]+/g, function(ch) {
                    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' };
                    return map[ch] || ch;
                });
            }

            function renderPanel($panel, options, onPick) {
                $panel.empty();

                if (!options.length) {
                    $panel.append($('<div class="autocomplete-empty">').text('No results found'));
                    $panel.show();
                    return;
                }

                options.forEach(function(opt) {
                    const $item = $('<div class="autocomplete-option" tabindex="0"></div>');
                    $item.html(opt.html || escapeHtml(opt.text));
                    $item.on('click', function() {
                        onPick(opt);
                    });
                    $item.on('mouseenter', function() {
                        $panel.find('.autocomplete-option').removeClass('active');
                        $item.addClass('active');
                    });
                    $panel.append($item);
                });
                $panel.show();
            }

            function filterClientOptions(term) {
                term = normalize(term);
                return allClientOptions.filter(function(opt) {
                    return normalize(opt.text).includes(term);
                });
            }

            function filterSiteOptions(term) {
                term = normalize(term);
                return allSiteOptions.filter(function(opt) {
                    return normalize(opt.text).includes(term);
                });
            }

            function filterContratOptions(term) {
                term = normalize(term);
                return allContratOptions.filter(function(opt) {
                    return normalize(opt.text).includes(term);
                });
            }

            function closePanels() {
                $clientPanel.hide();
                $sitePanel.hide();
                $contratPanel.hide();
            }

            function setClient(client) {
                $clientId.val(client.value);
                $clientInput.val(client.label || client.text);
                closePanels();
                $clientInput.trigger('change');
            }

            function setSite(site) {
                $siteId.val(site.value);
                $siteInput.val(site.label || site.text);
                closePanels();
                $siteInput.trigger('change');
            }

            function setContrat(contrat) {
                $contratId.val(contrat.value);
                $contratInput.val(contrat.label || contrat.text);
                closePanels();
            }

            function rebuildClientPanel(term) {
                const filtered = filterClientOptions(term).map(function(opt) {
                    const parts = opt.text.split(' - ');
                    return {
                        value: opt.value,
                        text: opt.text,
                        label: opt.text,
                        html: '<strong>' + escapeHtml(parts[0] || opt.text) + '</strong>' + (parts[1] ? '<span class="option-meta">' + escapeHtml(parts.slice(1).join(' - ')) + '</span>' : '')
                    };
                });
                renderPanel($clientPanel, filtered, setClient);
            }

            function rebuildSitePanel(term) {
                const filtered = filterSiteOptions(term).map(function(opt) {
                    return {
                        value: opt.value,
                        text: opt.text,
                        label: opt.text,
                        html: escapeHtml(opt.text)
                    };
                });
                renderPanel($sitePanel, filtered, setSite);
            }

            function rebuildContratPanel(term) {
                const filtered = filterContratOptions(term).map(function(opt) {
                    return {
                        value: opt.value,
                        text: opt.text,
                        label: opt.text,
                        html: escapeHtml(opt.text)
                    };
                });
                renderPanel($contratPanel, filtered, setContrat);
            }

            $clientInput.on('input focus click', function() {
                rebuildClientPanel(this.value);
            });

            $siteInput.on('input focus click', function() {
                rebuildSitePanel(this.value);
            });

            $contratInput.on('input focus click', function() {
                rebuildContratPanel(this.value);
            });

            $clientInput.on('keydown', function(e) {
                if (e.key === 'Escape') closePanels();
            });

            $siteInput.on('keydown', function(e) {
                if (e.key === 'Escape') closePanels();
            });

            $contratInput.on('keydown', function(e) {
                if (e.key === 'Escape') closePanels();
            });

            $(document).on('click', function(e) {
                if (!$(e.target).closest('.autocomplete-field').length) {
                    closePanels();
                }
            });

            // Fill if a client was preselected from query string
            if ($clientId.val()) {
                const preselectedClient = allClientOptions.find(function(opt) {
                    return String(opt.value) === String($clientId.val());
                });
                const currentClientLabel = preselectedClient ? preselectedClient.label : '';
                $clientInput.val(currentClientLabel);
                $clientInput.trigger('change');
            }

            // On change listener
            $clientInput.on('change', function() {
                var clientId = $clientId.val();
                if(!clientId) {
                    resetFields();
                    return;
                }

                // Load Data
                $.ajax({
                    url: 'get_client_details_api.php',
                    data: { client_id: clientId },
                    dataType: 'json',
                    success: function(data) {
                        // 1. Sites
                        currentSites = Array.isArray(data.sites) ? data.sites : [];
                        allSiteOptions = currentSites.map(function(s) {
                            const label = (s.nom || 'Site') + ' (' + (s.ville || '-') + ')';
                            return {
                                value: String(s.id),
                                text: label,
                                label: label,
                                html: escapeHtml(label)
                            };
                        });

                        $siteId.val('');
                        $siteInput.val('');
                        rebuildSitePanel($siteInput.val());

                        if (preselectedSiteId) {
                            const presetSite = currentSites.find(function(s) {
                                return String(s.id) === String(preselectedSiteId);
                            });
                            if (presetSite) {
                                $siteId.val(String(presetSite.id));
                                $siteInput.val((presetSite.nom || 'Site') + ' (' + (presetSite.ville || '-') + ')');
                                $('#contact_nom').val(presetSite.contact_nom || '');
                                $('#contact_tel').val(presetSite.contact_tel || '');
                            }
                        }

                        // 2. Contrats
                        const contrats = Array.isArray(data.contrats) ? data.contrats : [];
                        allContratOptions = contrats.map(function(c) {
                            var label = c.numero_contrat || 'N/A';
                            if(c.categorie_contrat) label += ' (' + c.categorie_contrat + ')';
                            if(c.etat) label += ' [' + c.etat + ']';
                            label += ' - Fin: ' + (c.date_fin || 'Non définie');
                            return { value: String(c.id), text: label, label: label, html: escapeHtml(label) };
                        });

                        $contratId.val('');
                        $contratInput.val('');
                        rebuildContratPanel($contratInput.val());

                        // Trigger change on site_select if something was pre-selected to load contact info
                    },
                    error: function() {
                        alert("Erreur lors du chargement des détails client.");
                    }
                });
            });

            // If a client was pre-selected on page load, trigger the change event to fetch sites
            if ($clientId.val()) {
                $clientInput.trigger('change');
            }

            // On Site Change Listener
            $siteInput.on('change', function() {
                var siteId = $siteId.val();
                if(siteId && currentSites) {
                    var site = currentSites.find(s => String(s.id) === String(siteId));
                    if(site) {
                        $('#contact_nom').val(site.contact_nom || '');
                        $('#contact_tel').val(site.contact_tel || '');
                    }
                }
            });

            function resetFields() {
                currentSites = [];
                allSiteOptions = [];
                allContratOptions = [];
                $siteInput.val('');
                $contratInput.val('');
                $siteId.val('');
                $contratId.val('');
                $clientId.val('');
                $('#contact_nom').val('');
                $('#contact_tel').val('');
                $sitePanel.hide();
                $contratPanel.hide();
                $clientPanel.hide();
            }
        });
    </script>
</body>
</html>
