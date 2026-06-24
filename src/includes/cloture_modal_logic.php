<?php
// GESTION DU POST POUR LA CLÔTURE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cloturer_modal') {
    $intervention_id = $_POST['intervention_id'] ?? '';
    $rapport_dispatch = trim($_POST['rapport_dispatch'] ?? '');
    $message_email = trim($_POST['message_email'] ?? '');

    if (!empty($intervention_id) && !empty($rapport_dispatch)) {
        require_once __DIR__ . '/../../config/db.php';
        
        $sqlCheck = "IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[Interventions]') AND name = 'rapport_dispatch')
                     BEGIN ALTER TABLE Interventions ADD rapport_dispatch NVARCHAR(MAX) NULL; END";
        sqlsrv_query($conn, $sqlCheck);

        $sqlDetails = "SELECT I.*, T.ID_TICKET, T.COMMENT as ticket_comment, C.Nom as client_nom, C.Email as client_email, S.Nom as site_nom, U.nom_complet as tech_nom 
                FROM Interventions I 
                JOIN TICKET T ON I.ticket_id = T.ID_TICKET
            LEFT JOIN SAV_Sites S ON T.ID_SITE = S.Id_Site
            LEFT JOIN SAV_Clients C ON T.ID_CLIENT = C.ID_Client
                LEFT JOIN Users U ON I.tech_id = U.id
                WHERE I.id = ?";
        $inter = sqlsrv_fetch_array(query($sqlDetails, [$intervention_id]), SQLSRV_FETCH_ASSOC);

        if ($inter) {
            $sqlUpdInt = "UPDATE Interventions SET rapport_dispatch = ? WHERE id = ?";
            query($sqlUpdInt, [$rapport_dispatch, $intervention_id]);

            $rapportText = "Clôturé par Dispatch. Rapport :\n" . $rapport_dispatch;
            $sqlUpdTicket = "UPDATE TICKET SET ETAT = 'cloture', COMMENT = CONCAT(COMMENT, CHAR(13), CHAR(10), '[', CONVERT(varchar, GETDATE(), 120), '] ', ?) WHERE ID_TICKET = ?";
            
            if (query($sqlUpdTicket, [$rapportText, $inter['ID_TICKET']])) {
                $success = "Ticket #{$inter['ID_TICKET']} clôturé avec succès et rapport mis à jour.";
                
                require_once __DIR__ . '/../utils/NotificationManager.php';
                $nm = new NotificationManager($conn);
                $nm->create("Ticket #{$inter['ID_TICKET']} clôturé.", 'admin', null, "/sav/src/modules/accueil/dashboard.php");
                
                if (!empty($inter['client_email'])) {
                    require_once __DIR__ . '/../utils/SmtpSender.php';
                    $smtpConfig = require __DIR__ . '/../../config/smtp_config.php';
                    
                    $subject = "Rapport d'Intervention - Ticket #" . $inter['ID_TICKET'];
                    $message = "Bonjour " . $inter['client_nom'] . ",\n\n";
                    if (!empty($message_email)) {
                        $message .= $message_email . "\n\n";
                    } else {
                        $message .= "Suite à l'intervention du " . ($inter['date_intervention'] ? $inter['date_intervention']->format('d/m/Y') : 'N/A') . " sur le site " . ($inter['site_nom'] ?? 'non spécifié') . ", veuillez trouver ci-dessous le rapport final :\n\n";
                    }
                    $message .= "--------------------------------------------------\n";
                    $message .= $rapport_dispatch . "\n";
                    $message .= "--------------------------------------------------\n\n";
                    $message .= "Votre ticket est désormais clôturé. Nous restons à votre disposition pour toute information complémentaire.\n\nCordialement,\nL'équipe Support.";
                    
                    try {
                        $mailer = new SmtpSender($smtpConfig);
                        $headers = "From: " . $smtpConfig['from_name'] . " <" . $smtpConfig['from_email'] . ">";
                        if ($mailer->send($inter['client_email'], $subject, $message, $headers)) {
                            $success .= " L'email a bien été envoyé au client.";
                        } else {
                            $error = "Ticket clôturé, mais l'envoi de l'email a échoué.";
                        }
                    } catch (Exception $e) {
                        $error = "Ticket clôturé, erreur SMTP: " . $e->getMessage();
                    }
                }
            } else {
                $error = "Erreur SQL lors de la mise à jour du ticket.";
            }
        } else {
            $error = "Intervention introuvable.";
        }
    } else {
        $error = "Le rapport de clôture est obligatoire.";
    }
}
