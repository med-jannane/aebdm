<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';
require_once __DIR__ . '/../../utils/SmtpSender.php';
$smtpConfig = require __DIR__ . '/../../../config/smtp_config.php';

check_role(['dispatch', 'admin']);

if (!isset($_GET['ticket_id'])) {
    header('Location: dashboard.php');
    exit;
}

$ticketId = $_GET['ticket_id'];
$error = '';
$success = '';

// Inclure la logique de la modale de clôture
require_once __DIR__ . '/../../includes/cloture_modal_logic.php';

$ticket = sqlsrv_fetch_array(
    query(
        "SELECT TICKET.ID_TICKET as id, TICKET.DATE as cree_le, TICKET.ETAT as statut, TICKET.PRIORITE as priorite, TICKET.COMMENT as description, TICKET.MESSAGE_DISPATCH as message_relais_dispatch,
                SAV_Clients.Nom as client_nom, SAV_Clients.Email as client_email, SAV_Clients.ID_Client as client_id,
                SAV_Sites.Nom as site_nom, SAV_Sites.Ville as ville, '' as region, SAV_Sites.Id_Site as site_id, '-' as contact_sur_place
         FROM TICKET
         JOIN SAV_Clients ON SAV_Clients.ID_Client = TICKET.ID_CLIENT
         LEFT JOIN SAV_Sites ON SAV_Sites.Id_Site = TICKET.ID_SITE
         WHERE TICKET.ID_TICKET = ?",
        [$ticketId]
    ),
    SQLSRV_FETCH_ASSOC
);

if (!$ticket) {
    header('Location: dashboard.php');
    exit;
}

$techs = query("SELECT id, nom_complet FROM Users WHERE role = 'tech' ORDER BY nom_complet ASC");

$existingIntervention = sqlsrv_fetch_array(
    query("SELECT TOP 1 * FROM Interventions WHERE ticket_id = ? ORDER BY date_planifiee DESC", [$ticketId]),
    SQLSRV_FETCH_ASSOC
);

// Fetch Site History (Past Interventions for this Site)
$siteHistoryQuery = "
    SELECT 
        i.date_intervention,
        i.statut as intervention_statut,
        i.rapport,
        u.nom_complet as technicien,
        'Demande SAV' as sujet,
        t.ID_TICKET as old_ticket_id
    FROM Interventions i
    JOIN TICKET t ON i.ticket_id = t.ID_TICKET
    LEFT JOIN Users u ON i.tech_id = u.id
    WHERE t.ID_SITE = ? AND t.ID_TICKET != ? AND i.statut IN ('terminee', 'cloture', 'validee')
    ORDER BY i.date_intervention DESC
";
$siteHistoryStmt = query($siteHistoryQuery, [$ticket['site_id'], $ticketId]);
$siteHistory = [];
while ($row = sqlsrv_fetch_array($siteHistoryStmt, SQLSRV_FETCH_ASSOC)) {
    $siteHistory[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'planifier';

    if ($action == 'annuler_affectation') {
        // Annuler toutes les planifications encore actives pour ce ticket.
        query(
            "UPDATE Interventions
                         SET statut = 'annule'
             WHERE ticket_id = ?
                             AND ISNULL(statut, '') NOT IN ('termine', 'annule')",
            [$ticketId]
        );

        if (query("UPDATE TICKET SET ETAT = 'attente_dispatch' WHERE ID_TICKET = ?", [$ticketId])) {
            require_once __DIR__ . '/../../utils/NotificationManager.php';
            $nm = new NotificationManager($conn);

            if (!empty($existingIntervention['tech_id'])) {
                $nm->create("Ticket #$ticketId - Affectation annulée, retiré de votre planning.", null, $existingIntervention['tech_id'], "/sav/src/modules/tech/dashboard.php");
            }

            $success = "Affectation annulée. Le ticket est remis en attente Dispatch.";
            $existingIntervention = sqlsrv_fetch_array(
                query("SELECT TOP 1 * FROM Interventions WHERE ticket_id = ? ORDER BY date_planifiee DESC", [$ticketId]),
                SQLSRV_FETCH_ASSOC
            );
        } else {
            $error = "Erreur lors de l'annulation de l'affectation.";
        }
    } elseif ($action == 'retour_tac') {
        if ($existingIntervention) {
            query("UPDATE Interventions SET statut = 'annule' WHERE id = ?", [$existingIntervention['id']]);
        }

        if (query("UPDATE TICKET SET ETAT = 'en_cours_tac' WHERE ID_TICKET = ?", [$ticketId])) {
            require_once __DIR__ . '/../../utils/NotificationManager.php';
            $nm = new NotificationManager($conn);
            $nm->create("Ticket #$ticketId - Ticket renvoyé par le Dispatch vers le TAC.", 'tac', null, "/sav/src/modules/tac/ticket_process.php?id=$ticketId");

            $success = "Ticket renvoyé au TAC avec succès.";
        } else {
            $error = "Erreur lors du retour du ticket vers le TAC.";
        }
    } elseif ($action == 'retour_accueil') {
        // Retourner le ticket à l'accueil pour devis/commande
        $sql = "UPDATE TICKET SET ETAT = 'attente_devis' WHERE ID_TICKET = ?";
        // On pourrait ajouter un log ou une note interne ici si nécessaire
        if (query($sql, [$ticketId])) {
            require_once __DIR__ . '/../../utils/NotificationManager.php';
            $nm = new NotificationManager($conn);
            $nm->create("Ticket #$ticketId - Hors contrat validé par le Dispatch (retour Accueil).", 'accueil', null, "/sav/src/modules/accueil/tickets.php"); // Link to list
            $nm->create("Ticket #$ticketId - Hors contrat validé par le Dispatch (information TAC).", 'tac', null, "/sav/src/modules/tac/ticket_process.php?id=$ticketId");
            
            $success = "Ticket renvoyé à l'Accueil (Hors Contrat).";
            // Redirection optionnelle ou affichage succès
        } else {
            $error = "Erreur lors du renvoi.";
        }
    } elseif ($action == 'cloturer') {
        $notesCloture = trim($_POST['notes_cloture'] ?? '');
        
        if (empty($notesCloture)) {
            $notesCloture = "Clôturé suite à l'intervention.";
        }
        
        // Concaténer la note au rapport final ou description du ticket
        $rapportText = "Clôturé par Dispatch. Motif : " . $notesCloture;
        $sql = "UPDATE TICKET SET ETAT = 'cloture', COMMENT = CONCAT(COMMENT, CHAR(13), CHAR(10), '[', CONVERT(varchar, GETDATE(), 120), '] ', ?) WHERE ID_TICKET = ?";
        
        if (query($sql, [$rapportText, $ticketId])) {
            $success = "Ticket clôturé avec succès.";
            
            require_once __DIR__ . '/../../utils/NotificationManager.php';
            $nm = new NotificationManager($conn);
            $nm->create("Ticket #$ticketId - Ticket clôturé par le Dispatch.", 'admin', null, "/sav/src/modules/accueil/dashboard.php");
            
            // --- ENVOI DE L'EMAIL AU CLIENT LORS DE LA CLOTURE ---
            if (!empty($ticket['client_email'])) {
                // Recuperer le dernier rapport tech
                $lastInter = sqlsrv_fetch_array(query("SELECT TOP 1 rapport_tech FROM Interventions WHERE ticket_id = ? ORDER BY date_intervention DESC", [$ticketId]), SQLSRV_FETCH_ASSOC);
                $rapportTech = $lastInter ? $lastInter['rapport_tech'] : "Aucun rapport d'intervention disponible.";
                
                $subject = "Clôture de votre ticket SAV #" . $ticketId;
                $message = "Bonjour cher client (" . $ticket['client_nom'] . "),\n\n";
                $message .= "Nous tenons à vous informer que votre ticket de support (#$ticketId) a été clôturé.\n\n";
                $message .= "Compte-rendu de notre technicien :\n";
                $message .= "--------------------------------------------------\n";
                $message .= $rapportTech . "\n";
                $message .= "--------------------------------------------------\n\n";
                $message .= "Notes complémentaires : " . $notesCloture . "\n\n";
                $message .= "Merci de votre confiance.\nL'équipe SAV.";
                
                try {
                    $mailer = new SmtpSender($smtpConfig);
                    $headers = "From: " . $smtpConfig['from_name'] . " <" . $smtpConfig['from_email'] . ">";
                    if ($mailer->send($ticket['client_email'], $subject, $message, $headers)) {
                        $success .= " (Email envoyé au client : " . $ticket['client_email'] . ")";
                    } else {
                        $error = "Ticket clôturé mais échec de l'envoi de l'email au client.";
                    }
                } catch (Exception $e) {
                    $error = "Ticket clôturé mais erreur SMTP: " . $e->getMessage();
                }
            } else {
                $error = "Ticket clôturé, mais le client n'a pas d'adresse email renseignée.";
            }

        } else {
            $error = "Erreur lors de la clôture du ticket.";
        }
    } elseif ($action == 'planifier') {
        $techId = $_POST['tech_id'] ?? '';
        $datePlanifieeRaw = $_POST['date_planifiee'] ?? '';


    if ($techId === '' || $datePlanifieeRaw === '') {
        $error = "Le technicien et la date planifiee sont obligatoires.";
    } else {
        $datePlanifiee = DateTime::createFromFormat('Y-m-d\TH:i', $datePlanifieeRaw);
        if (!$datePlanifiee) {
            $error = "Format de date invalide.";
        } else {
            $dateSql = $datePlanifiee->format('Y-m-d H:i:s');

            // Instructions = Message du TAC (automatique)
            $instructions = $ticket['message_relais_dispatch'] ?? '';

            if ($existingIntervention) {
                $interventionIdForNotif = $existingIntervention['id'];
                $sql = "UPDATE Interventions SET tech_id = ?, date_planifiee = ?, instructions = ?, statut = 'planifie' WHERE id = ?";
                $params = [$techId, $dateSql, $instructions, $existingIntervention['id']];
            } else {
                $new_id = uniqid('INT-');
                $interventionIdForNotif = $new_id;
                $sql = "INSERT INTO Interventions (id, ticket_id, tech_id, date_planifiee, instructions, statut)
                        VALUES (?, ?, ?, ?, ?, 'planifie')";
                $params = [$new_id, $ticketId, $techId, $dateSql, $instructions];
            }

            $stmt = sqlsrv_query($conn, $sql, $params);
            if ($stmt) {
                query("UPDATE TICKET SET ETAT = 'assigne' WHERE ID_TICKET = ?", [$ticketId]);
                
                // NOTIFICATION POUR LE TECHNICIEN
                require_once __DIR__ . '/../../utils/NotificationManager.php';
                $nm = new NotificationManager($conn);
                $nm->create(
                    "Ticket #$ticketId - Nouvelle intervention planifiée à " . $ticket['ville'],
                    null,
                    $techId,
                    "/sav/src/modules/tech/report_intervention.php?id=" . urlencode((string)$interventionIdForNotif)
                );

                // Récupérer email du technicien
                $techRow = sqlsrv_fetch_array(query("SELECT email, nom_complet FROM Users WHERE id = ?", [$techId]), SQLSRV_FETCH_ASSOC);
                $techEmail = $techRow['email'];
                
                // Envoi de l'email
                if ($techEmail) {
                    $subject = "Nouvelle intervention assignee - Ticket #" . $ticketId;
                    $message = "Bonjour " . $techRow['nom_complet'] . ",\n\n";
                    $message .= "Une nouvelle intervention vous a ete assignee.\n\n";
                    $message .= "Client : " . $ticket['client_nom'] . "\n";
                    $message .= "Site : " . $ticket['site_nom'] . " (" . $ticket['ville'] . ")\n";
                    $message .= "Date planifiee : " . $datePlanifiee->format('d/m/Y H:i') . "\n\n";
                    $message .= "MESSAGE DU TAC :\n";
                    $message .= "--------------------------------------------------\n";
                    $message .= ($instructions ?: "Aucune instruction specifique.") . "\n";
                    $message .= "--------------------------------------------------\n\n";
                    $message .= "Cordialement,\nDispatch SAV";
                    
                    // Envoi via SmtpSender
                    try {
                        $mailer = new SmtpSender($smtpConfig);
                        $headers = "From: " . $smtpConfig['from_name'] . " <" . $smtpConfig['from_email'] . ">";
                        
                        if ($mailer->send($techEmail, $subject, $message, $headers)) {
                            $emailStatus = " (Email envoye a $techEmail)";
                        } else {
                            $emailStatus = " (Echec de l'envoi de l'email via SMTP)";
                        }
                    } catch (Exception $e) {
                         $emailStatus = " (Erreur SMTP: " . $e->getMessage() . ")";
                    }
                } else {
                    $emailStatus = " (Aucun email defini pour ce technicien)";
                }

                $success = "Intervention planifiee avec succes." . $emailStatus;

                $existingIntervention = sqlsrv_fetch_array(
                    query("SELECT TOP 1 * FROM Interventions WHERE ticket_id = ? ORDER BY date_planifiee DESC", [$ticketId]),
                    SQLSRV_FETCH_ASSOC
                );
            } else {
                error_log('[DISPATCH_ASSIGN_TECH_PLAN] ' . db_last_error_message());
                $error = "Erreur lors de la planification de l'intervention.";
            }
        }
    }
}
}

$pageTitle = "Planifier intervention";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <style>
        .assign-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
        }

        .message-bubble {
            max-width: 80%;
            padding: 10px 15px;
            border-radius: var(--radius-md);
            position: relative;
            font-size: 0.9em;
        }

        .message-mine {
            align-self: flex-end;
            background-color: var(--primary);
            color: white;
            border-bottom-right-radius: 0;
        }

        .message-other {
            align-self: flex-start;
            background-color: #e2e8f0;
            color: var(--text-dark);
            border-bottom-left-radius: 0;
        }

        .message-meta {
            font-size: 0.75em;
            opacity: 0.8;
            margin-top: 5px;
            display: block;
        }

        .contract-modal-content {
            margin: 4% auto;
            width: 92%;
            max-width: 760px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            border-radius: 14px;
            border: 1px solid rgba(58,1,92,.12);
            box-shadow: 0 22px 48px rgba(15, 23, 42, .22);
            overflow: hidden;
            background: linear-gradient(180deg, #ffffff 0%, #fbf9ff 100%);
        }

        .contract-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 16px 18px;
            border-bottom: 1px solid var(--border);
            background: rgba(58, 1, 92, .05);
        }

        .contract-modal-title {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--dark-amethyst-3);
            font-size: 1.08rem;
        }

        .contract-modal-close {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            border: 1px solid rgba(58,1,92,.2);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text-muted);
            transition: all .2s ease;
            font-size: 1.3rem;
            line-height: 1;
        }

        .contract-modal-close:hover {
            color: var(--primary);
            border-color: var(--primary);
            background: rgba(58,1,92,.08);
        }

        .contract-modal-loading {
            text-align: center;
            padding: 24px;
            color: var(--text-muted);
        }

        .contract-modal-error {
            display: none;
            color: var(--danger);
            margin: 16px 18px 0;
            padding: 12px;
            background: #fef2f2;
            border-left: 4px solid var(--danger);
            border-radius: 8px;
        }

        .contract-modal-body {
            display: none;
            overflow-y: auto;
            flex: 1;
            margin: 16px 18px;
            padding-right: 4px;
        }

        .contract-details-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
        }

        .contract-details-table td {
            padding: 11px 12px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
            font-size: .95rem;
        }

        .contract-details-table tr:last-child td {
            border-bottom: none;
        }

        .contract-details-table td:first-child {
            width: 40%;
            color: var(--text-muted);
            background: rgba(58,1,92,.03);
            font-weight: 600;
        }

        .contract-details-table td:last-child {
            text-align: right;
            color: var(--text-dark);
            font-weight: 700;
            word-break: break-word;
        }

        .contract-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: .78rem;
            letter-spacing: .03em;
        }

        .contract-modal-footer {
            margin-top: auto;
            padding: 14px 18px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            background: rgba(58, 1, 92, .03);
        }

        @media (max-width: 980px) {
            .assign-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .contract-modal-content {
                width: 96%;
                margin: 3% auto;
                max-height: 94vh;
            }

            .contract-modal-title {
                font-size: 1rem;
            }

            .contract-details-table td {
                font-size: .9rem;
                padding: 9px 10px;
            }

            .contract-details-table td:first-child {
                width: 46%;
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
                <div>
                    <h1 class="page-title">Planifier intervention</h1>
                    <p class="subtle-text">Ticket #<?php echo $ticket['id']; ?> - <?php echo htmlspecialchars($ticket['client_nom']); ?></p>
                </div>
            </div>
            <a href="dashboard.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Retour</a>
        </header>

        <?php if ($error): ?>
            <div class="card" style="background-color: #fef2f2; color: var(--error-color); border-left: 4px solid var(--error-color);">
                <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="card" style="background-color: #ecfdf3; color: var(--success-color); border-left: 4px solid var(--success-color);">
                <i class="fa-solid fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <div class="assign-grid">
            <div class="card">
                <h2 class="page-title"><i class="fa-solid fa-circle-info"></i> Informations Client & Site</h2>
                
                <style>
                    .details-table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-bottom: 20px;
                    }
                    .details-table td {
                        padding: 8px;
                        border-bottom: 1px solid var(--border);
                    }
                    .details-table td:first-child {
                        font-weight: 500;
                        color: var(--text-muted);
                        width: 40%;
                    }
                    .details-table td:last-child {
                        font-weight: 600;
                        text-align: right;
                    }
                </style>

                <table class="details-table">
                    <tr><td>Code Client</td><td><?php echo htmlspecialchars($ticket['client_id']); ?></td></tr>
                    <tr>
                        <td>Nom Client</td>
                        <td>
                            <?php echo htmlspecialchars($ticket['client_nom']); ?>
                            <button type="button" class="btn btn-sm btn-secondary" style="margin-left:10px; font-size: 0.8em; padding: 2px 8px;" onclick="openContractModal('<?php echo htmlspecialchars($ticket['client_id'] ?? ''); ?>')">
                                <i class="fa-solid fa-file-contract"></i> Voir le Contrat
                            </button>
                        </td>
                    </tr>
                    <tr><td>Site</td><td><?php echo htmlspecialchars($ticket['site_nom']); ?></td></tr>
                    <tr>
                        <td>Adresse</td>
                        <td>
                            <?php 
                            $siteAddress = htmlspecialchars($ticket['ville']); 
                            echo $siteAddress;
                            $mapQuery = urlencode($ticket['ville']);
                            ?>
                            <div style="margin-top: 5px; display:flex; justify-content: flex-end; gap:5px;">
                                <a href="https://maps.google.com/?q=<?php echo $mapQuery; ?>" target="_blank" class="btn btn-sm" style="background-color: #4285F4; color: white; padding: 2px 8px; font-size: 0.8em; border-radius: 4px;">
                                    <i class="fa-solid fa-map-location-dot"></i> Maps
                                </a>
                                <a href="https://waze.com/ul?q=<?php echo $mapQuery; ?>" target="_blank" class="btn btn-sm" style="background-color: #33ccff; color: white; padding: 2px 8px; font-size: 0.8em; border-radius: 4px;">
                                    <i class="fa-brands fa-waze"></i> Waze
                                </a>
                            </div>
                        </td>
                    </tr>
                    <tr><td>Contact Sur Place</td><td><?php echo htmlspecialchars($ticket['contact_sur_place'] ?? 'Non spécifié'); ?></td></tr>
                    <tr><td>Priorité</td><td><span class="badge badge-<?php echo $ticket['priorite']; ?>"><?php echo htmlspecialchars(ucfirst($ticket['priorite'])); ?></span></td></tr>
                </table>

                <div class="card" style="background: var(--background); margin-top: 20px; border: 1px dashed var(--border);">
                    <strong style="color:var(--primary);"><i class="fa-solid fa-envelope-open-text"></i> Message du TAC (Interne)</strong>
                    <p class="subtle-text" style="font-style: italic; margin-top:10px;">
                        <?php 
                        if(!empty($ticket['message_relais_dispatch'])) {
                            echo nl2br(htmlspecialchars($ticket['message_relais_dispatch'])); 
                        } else {
                            echo "<span style='color:var(--text-muted);'>Aucun message du TAC.</span>";
                        }
                        ?>
                    </p>
                    
                    <hr style="margin: 15px 0;">
                    
                    <strong class="subtle-text"><i class="fa-regular fa-file-lines"></i> Description Problème</strong>
                    <p class="subtle-text" style="font-size: 0.9em; color: var(--text);"><?php echo nl2br(htmlspecialchars($ticket['description'])); ?></p>
                </div>
                
                <?php if ($existingIntervention): ?>
                <div class="card" style="margin-top: 20px; border-top: 4px solid var(--primary);">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <h3 style="margin:0;"><i class="fa-solid fa-comments"></i> Discussion Intervention</h3>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="openChatModal(<?php echo $existingIntervention['id']; ?>)">
                            Ouvrir le Chat
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <!-- HISTORIQUE DU SITE -->
                <div class="card" style="margin-top: 20px;">
                    <h3 style="margin-top:0;"><i class="fa-solid fa-clock-rotate-left"></i> Historique du Site</h3>
                    <?php if (empty($siteHistory)): ?>
                        <p class="subtle-text" style="font-style: italic;">Aucune intervention passée enregistrée pour ce site.</p>
                    <?php else: ?>
                        <div style="max-height: 300px; overflow-y: auto; border: 1px solid var(--border); border-radius: var(--radius-md);">
                            <table class="table table-striped" style="margin:0; font-size: 0.9em;">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Technicien</th>
                                        <th>Sujet Ticket</th>
                                        <th>Rapport</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($siteHistory as $hist): ?>
                                    <tr>
                                        <td style="white-space: nowrap;">
                                            <?php echo $hist['date_intervention'] ? $hist['date_intervention']->format('d/m/Y') : '-'; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($hist['technicien'] ?? 'Inconnu'); ?></td>
                                        <td><a href="../accueil/ticket_details.php?id=<?php echo $hist['old_ticket_id']; ?>" target="_blank">#<?php echo $hist['old_ticket_id']; ?> - <?php echo htmlspecialchars($hist['sujet']); ?></a></td>
                                        <td>
                                            <?php 
                                            $rap = $hist['rapport'] ?? '';
                                            if (strlen($rap) > 50) {
                                                echo htmlspecialchars(substr($rap, 0, 50)) . '...';
                                            } else {
                                                echo htmlspecialchars($rap ?: '-');
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <h2 class="page-title"><i class="fa-solid fa-user-clock"></i> Affectation</h2>
                <form method="POST">
                    <div class="form-group">
                        <label for="tech_id"><i class="fa-solid fa-user-gear"></i> Technicien</label>
                        <select name="tech_id" id="tech_id" required class="form-control">
                            <option value="">-- Choisir un technicien --</option>
                            <?php while ($tech = sqlsrv_fetch_array($techs, SQLSRV_FETCH_ASSOC)): ?>
                                <?php
                                $selected = $existingIntervention && $existingIntervention['tech_id'] === $tech['id'];
                                ?>
                                <option value="<?php echo $tech['id']; ?>" <?php echo $selected ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tech['nom_complet']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="date_planifiee"><i class="fa-regular fa-calendar-days"></i> Date planifiée</label>
                        <input type="datetime-local" name="date_planifiee" id="date_planifiee" required class="form-control"
                               style="font-family: inherit;"
                               value="<?php
                                    if ($existingIntervention && $existingIntervention['date_planifiee']) {
                                        echo $existingIntervention['date_planifiee']->format('Y-m-d\TH:i');
                                    } else {
                                        echo date('Y-m-d\TH:i'); // Par défaut à maintenant
                                    }
                               ?>">
                    </div>

                    <div class="form-group" style="margin-top:20px; border-top: 1px solid var(--border); padding-top: 15px;">
                        <label for="notes_cloture"><i class="fa-solid fa-pen-to-square"></i> Notes de Clôture (Obligatoire si clôture directe)</label>
                        <textarea name="notes_cloture" id="notes_cloture" class="form-control" rows="3" placeholder="Renseignez ici la raison de la clôture (ex: Problème résolu par téléphone, annulé par le client...)"></textarea>
                    </div>

                    <div class="form-group" style="margin-top:20px;">
                        <p class="subtle-text"><i class="fa-solid fa-info-circle"></i> <em>Le technicien recevra automatiquement le message du TAC par email lors de la planification.</em></p>
                    </div>

                    <div class="form-actions" style="display: flex; gap: 10px; flex-wrap: wrap; margin-top:20px;">
                        <button type="submit" name="action" value="planifier" class="btn btn-full" style="flex: 2; min-width: 200px;">
                            <i class="fa-solid fa-calendar-plus"></i> Valider la Planification
                        </button>
                        
                        <!-- Bouton Clôture Directe / Rapport -->
                        <?php if ($existingIntervention && in_array(strtolower((string)$existingIntervention['statut']), ['termine', 'terminee', 'cloture', 'validee'], true)): ?>
                            <button type="button" class="btn btn-secondary" 
                               style="background-color: var(--success); color: white; border-color: var(--success); flex: 1; min-width: 150px; text-align:center;"
                               onclick="openClotureModal('<?php echo htmlspecialchars($existingIntervention['id']); ?>'); return false;">
                                <i class="fa-solid fa-file-signature"></i> Rédiger Rapport & Clôturer
                            </button>
                        <?php else: ?>
                            <button type="submit" name="action" value="cloturer" class="btn btn-secondary" 
                                    style="background-color: var(--success); color: white; border-color: var(--success); flex: 1; min-width: 150px;"
                                    formnovalidate
                                    onclick="return confirmerCloture();">
                                <i class="fa-solid fa-check-double"></i> Clôturer Directement
                            </button>
                        <?php endif; ?>

                        <button type="submit" name="action" value="annuler_affectation" class="btn btn-secondary"
                                style="background-color: #6b7280; color: white; border-color: #6b7280; flex: 1; min-width: 150px;"
                                formnovalidate
                                onclick="return confirm('Confirmez-vous l\'annulation de l\'affectation de ce ticket ?');">
                            <i class="fa-solid fa-user-xmark"></i> Annuler Affectation
                        </button>

                        <button type="submit" name="action" value="retour_tac" class="btn btn-warning"
                                style="flex: 1; min-width: 150px;"
                                formnovalidate
                                onclick="return confirm('Confirmez-vous le retour de ce ticket vers le TAC ?');">
                            <i class="fa-solid fa-rotate-left"></i> Retour au TAC
                        </button>
                        
                        <!-- Bouton Retour Accueil pour cas Hors Contrat -->
                        <button type="submit" name="action" value="retour_accueil" class="btn btn-danger" 
                                style="background-color: var(--danger); flex: 1; min-width: 150px;"
                                formnovalidate
                                onclick="return confirm('Confirmez-vous que ce ticket est HORS CONTRAT et doit être renvoyé à l\'Accueil pour devis ?');">
                            <i class="fa-solid fa-ban"></i> TIR / Hors Contrat
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Chat Modal -->
    <div id="chatModal" class="modal" style="display:none;">
        <div class="modal-content" style="margin:5% auto; padding:20px; width:80%; max-width:600px; display:flex; flex-direction:column; height:70vh;">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); padding-bottom:10px; margin-bottom:15px;">
                <h3 style="margin:0;"><i class="fa-solid fa-comments"></i> Discussion Intervention #<span id="chatInterventionId"></span></h3>
                <span class="close" onclick="closeChatModal()">&times;</span>
            </div>
            
            <div id="chatMessages" style="flex:1; overflow-y:auto; padding:10px; display:flex; flex-direction:column; gap:10px; background:var(--background); border-radius:var(--radius-md); margin-bottom:15px;">
                <!-- Messages will be loaded here -->
            </div>
            
            <div style="display:flex; gap:10px;">
                <input type="text" id="chatInput" class="form-control" placeholder="Taper votre message..." style="flex:1;" onkeypress="if(event.key === 'Enter') sendChatMessage()">
                <button type="button" class="btn" onclick="sendChatMessage()"><i class="fa-solid fa-paper-plane"></i> Envoyer</button>
            </div>
        </div>
    </div>

    <script>
        const assignMenuBtn = document.querySelector('.mobile-toggle');
        const assignSidebar = document.getElementById('sidebar');
        const assignOverlay = document.getElementById('sidebarOverlay');

        if (assignMenuBtn && assignSidebar && assignOverlay) {
            assignMenuBtn.addEventListener('click', () => {
                assignSidebar.classList.toggle('active');
                assignOverlay.classList.toggle('active');
            });

            assignOverlay.addEventListener('click', () => {
                assignSidebar.classList.remove('active');
                assignOverlay.classList.remove('active');
            });
        }

        let currentInterventionId = null;
        let chatPollInterval = null;

        function openChatModal(interventionId) {
            currentInterventionId = interventionId;
            document.getElementById('chatInterventionId').textContent = interventionId;
            document.getElementById('chatModal').style.display = 'block';
            loadChatMessages();
            // Start polling for new messages every 5 seconds
            chatPollInterval = setInterval(loadChatMessages, 5000);
            
            // Focus on input
            setTimeout(() => document.getElementById('chatInput').focus(), 100);
        }

        function closeChatModal() {
            document.getElementById('chatModal').style.display = 'none';
            currentInterventionId = null;
            if (chatPollInterval) {
                clearInterval(chatPollInterval);
                chatPollInterval = null;
            }
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
                        messagesContainer.innerHTML = '<div style="text-align:center; color:var(--text-muted); margin-top:20px;">Aucun message pour cette intervention.</div>';
                        return;
                    }

                    data.messages.forEach(msg => {
                        const bubbleClass = msg.is_mine ? 'message-mine' : 'message-other';
                        const authorInfo = msg.is_mine ? '' : `<strong>${msg.auteur} (${msg.role})</strong><br>`;
                        
                        const msgHtml = `
                            <div class="message-bubble ${bubbleClass}">
                                ${authorInfo}
                                ${msg.message.replace(/\n/g, '<br>')}
                                <span class="message-meta">${msg.date}</span>
                            </div>
                        `;
                        messagesContainer.insertAdjacentHTML('beforeend', msgHtml);
                    });
                    
                    // Scroll to bottom only if user hasn't scrolled up (simplified for now: always scroll to bottom)
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                }
            } catch (error) {
                console.error('Erreur lors du chargement du chat:', error);
            }
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
                    loadChatMessages(); // Refresh immediately
                } else {
                    alert(data.error || 'Erreur lors de l\'envoi');
                }
            } catch (error) {
                console.error('Erreur:', error);
                alert('Erreur réseau');
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const chatModal = document.getElementById('chatModal');
            const contractModal = document.getElementById('contractModal');
            if (event.target == chatModal) closeChatModal();
            if (event.target == contractModal) closeContractModal();
        }

        function confirmerCloture() {
            const notes = document.getElementById('notes_cloture').value.trim();
            if(!notes) {
                return confirm("Aucune note de clôture saisie. Continuer avec une note automatique ?");
            }
            return confirm("Confirmez-vous la clôture définitive de ce ticket ?");
        }

        async function openContractModal(clientId) {
            document.getElementById('contractModal').style.display = 'block';
            document.getElementById('contractLoading').style.display = 'block';
            document.getElementById('contractDetails').style.display = 'none';
            document.getElementById('contractError').style.display = 'none';

            try {
                const response = await fetch(`../api/get_contrat.php?client_id=${clientId}`);
                const data = await response.json();
                
                document.getElementById('contractLoading').style.display = 'none';

                if(data.success) {
                    document.getElementById('contractDetails').style.display = 'block';
                    
                    const tbody = document.getElementById('contractTableBody');
                    tbody.innerHTML = ''; // Clear previous

                    // Mapping of nice labels for known fields
                    const fieldLabels = {
                        'numero_contrat': 'Numéro Contrat',
                        'date_debut': 'Date de Début',
                        'date_fin': 'Date de Fin',
                        'type': 'Type de contrat',
                        'categorie': 'Catégorie',
                        'materiel': 'Matériel couvert',
                        'details': 'Détails Additionnels',
                        'created_at': 'Créé le'
                    };

                    // First add the special Status row
                    let statusRow = `<tr><td>Statut</td><td><span class="badge badge-${data.contrat.statut_badge} contract-status-badge">${data.contrat.status.toUpperCase()}</span></td></tr>`;
                    tbody.insertAdjacentHTML('beforeend', statusRow);

                    // Then iterate through all other fields
                    for (const [key, value] of Object.entries(data.contrat)) {
                         // Skip these special keys, internal IDs
                        if (['status', 'statut_badge', 'id', 'client_id'].includes(key.toLowerCase())) continue;
                        
                        // Ignore empty values so the popup doesn't look empty
                        if (value === null || value === '') continue;

                        const label = fieldLabels[key] || key.charAt(0).toUpperCase() + key.slice(1).replace('_', ' ');
                        let displayValue = value;
                        
                        // Handle long text like 'details' or 'materiel'
                        if (typeof value === 'string' && value.length > 50) {
                            displayValue = value.replace(/\n/g, '<br>');
                        }

                        let row = `<tr><td>${label}</td><td>${displayValue}</td></tr>`;
                        tbody.insertAdjacentHTML('beforeend', row);
                    }
                } else {
                    document.getElementById('contractError').style.display = 'block';
                    document.getElementById('contractErrorText').textContent = data.error || "Contrat introuvable.";
                }
            } catch(e) {
                document.getElementById('contractLoading').style.display = 'none';
                document.getElementById('contractError').style.display = 'block';
                document.getElementById('contractErrorText').textContent = "Erreur de connexion au serveur.";
            }
        }

        function closeContractModal() {
            document.getElementById('contractModal').style.display = 'none';
        }
    </script>

    <!-- Contract Modal -->
    <div id="contractModal" class="modal" style="display:none;">
        <div class="modal-content contract-modal-content">
            <div class="contract-modal-header">
                <h3 class="contract-modal-title"><i class="fa-solid fa-file-signature"></i> Détails du Contrat Actif</h3>
                <span class="close contract-modal-close" onclick="closeContractModal()">&times;</span>
            </div>
            
            <div id="contractLoading" class="contract-modal-loading">
                <i class="fa-solid fa-spinner fa-spin fa-2x"></i><br>Chargement...
            </div>

            <div id="contractError" class="contract-modal-error">
                <i class="fa-solid fa-circle-exclamation"></i> <span id="contractErrorText"></span>
            </div>

            <div id="contractDetails" class="contract-modal-body">
                <table class="details-table contract-details-table">
                    <tbody id="contractTableBody">
                        <!-- Content populated dynamically via JS -->
                    </tbody>
                </table>
            </div>

            <div class="contract-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeContractModal()">Fermer</button>
            </div>
        </div>
    </div>

    <!-- UI Modale de Clôture -->
    <?php require_once __DIR__ . '/../../includes/cloture_modal_ui.php'; ?>
</body>
</html>
