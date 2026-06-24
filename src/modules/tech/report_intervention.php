<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['tech', 'admin']);

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Récupérer l'intervention et vérifier l'assignation
$sql = "SELECT Interventions.*, TICKET.ID_TICKET as ticket_id, '-' as contact_sur_place, TICKET.COMMENT as ticket_desc, 'Demande SAV' as sujet,
               SAV_Clients.Nom as client_nom, SAV_Sites.Nom as site_nom, SAV_Sites.Ville as ville, TICKET.ID_CLIENT as client_id
        FROM Interventions 
        JOIN TICKET ON Interventions.ticket_id = TICKET.ID_TICKET
        JOIN SAV_Clients ON TICKET.ID_CLIENT = SAV_Clients.ID_Client
        LEFT JOIN SAV_Sites ON TICKET.ID_SITE = SAV_Sites.Id_Site
        WHERE Interventions.id = ? AND Interventions.tech_id = ?";
$inter = sqlsrv_fetch_array(query($sql, [$id, $user_id]), SQLSRV_FETCH_ASSOC);

if (!$inter) die("Intervention introuvable ou vous n'êtes pas le technicien assigné.");

// Récupérer les produits disponibles
$produits = query("SELECT * FROM Produits ORDER BY nom ASC");

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Horaires Matin
    $heure_arrivee_matin = $_POST['heure_arrivee_matin'];
    $heure_depart_matin = $_POST['heure_depart_matin'];
    $duree_trajet_matin = $_POST['duree_trajet_matin'];

    // Horaires Soir
    $heure_arrivee_soir = $_POST['heure_arrivee_soir'];
    $heure_depart_soir = $_POST['heure_depart_soir'];
    $duree_trajet_soir = $_POST['duree_trajet_soir'];
    
    // Contenu
    $travaux_demandes = $_POST['travaux_demandes'];
    $travaux_recommandes = $_POST['travaux_recommandes'];
    $rapport_effectue = $_POST['rapport']; // Travaux Effectués
    
    $commentaire_client = $_POST['commentaire_client'];
    $nom_signataire_client = $_POST['nom_signataire_client'];
    
    $statut_final = $_POST['statut_final'];

    $date_debut = date('Y-m-d') . ' ' . ($heure_arrivee_matin ?: '08:00'); 
    $date_fin_heure = $heure_depart_soir ?: ($heure_depart_matin ?: '18:00');
    $date_fin = date('Y-m-d') . ' ' . $date_fin_heure;

    // Gestion Fichier Joint (Photo)
    $fichier_path = null;
    if (isset($_FILES['piece_jointe']) && $_FILES['piece_jointe']['error'] == 0) {
        $uploadDir = __DIR__ . '/../../../public/uploads/interventions/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $maxFileSize = 10 * 1024 * 1024; // 10 MB
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        $allowedMime = [
            'image/jpeg',
            'image/png',
            'image/webp',
            'application/pdf'
        ];

        $originalName = (string)($_FILES['piece_jointe']['name'] ?? '');
        $ext = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
        $tmpPath = (string)($_FILES['piece_jointe']['tmp_name'] ?? '');
        $fileSize = (int)($_FILES['piece_jointe']['size'] ?? 0);
        $mimeType = '';
        if ($tmpPath !== '' && is_uploaded_file($tmpPath) && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mimeType = (string)finfo_file($finfo, $tmpPath);
                finfo_close($finfo);
            }
        }

        if ($fileSize > $maxFileSize) {
            $error = "Pièce jointe trop volumineuse (max 10 Mo).";
        } elseif (!in_array($ext, $allowedExt, true) || !in_array($mimeType, $allowedMime, true)) {
            $error = "Type de pièce jointe non autorisé.";
        } elseif (!is_uploaded_file($tmpPath)) {
            $error = "Upload pièce jointe invalide détecté.";
        } else {
            $fileName = 'INT_' . bin2hex(random_bytes(16)) . '.' . $ext;
            $targetPath = $uploadDir . $fileName;
            if (move_uploaded_file($tmpPath, $targetPath)) {
                $fichier_path = 'public/uploads/interventions/' . $fileName;
            } else {
                $error = "Echec de l'enregistrement de la pièce jointe.";
            }
        }
    }

    if (!$error && empty($rapport_effectue)) {
        $error = "Le champ 'Travaux Effectués' est obligatoire.";
    } elseif (!$error) {
        
        // MAJ Intervention
        $sqlUpd = "UPDATE Interventions SET 
                   statut = 'termine', 
                   rapport = ?, 
                   travaux_recommandes = ?,
                   commentaire_client = ?,
                   nom_signataire_client = ?,
                   heure_arrivee_matin = ?, heure_depart_matin = ?, duree_trajet_matin = ?,
                   heure_arrivee_soir = ?, heure_depart_soir = ?, duree_trajet_soir = ?,
                   date_intervention = ?, date_fin = ?, fichiers_path = ?
                   WHERE id = ?";
        
        $params = [
            $rapport_effectue, 
            $travaux_recommandes, 
            $commentaire_client, 
            $nom_signataire_client,
            $heure_arrivee_matin, $heure_depart_matin, $duree_trajet_matin,
            $heure_arrivee_soir, $heure_depart_soir, $duree_trajet_soir,
            $date_debut, $date_fin, $fichier_path, 
            $id
        ];

        if (sqlsrv_query($conn, $sqlUpd, $params)) {
            
            // Gestion des Produits
            $sqlDelProd = "DELETE FROM Intervention_Produits WHERE intervention_id = ?";
            sqlsrv_query($conn, $sqlDelProd, [$id]);

            if (isset($_POST['produits']) && is_array($_POST['produits'])) {
                foreach ($_POST['produits'] as $prod_id => $qty) {
                    if ($qty > 0) {
                        $sqlProd = "INSERT INTO Intervention_Produits (intervention_id, produit_id, quantite) VALUES (?, ?, ?)";
                        sqlsrv_query($conn, $sqlProd, [$id, $prod_id, $qty]);
                        
                        // Decrement Stock
                        $sqlStock = "UPDATE Produits SET stock = stock - ? WHERE id = ?";
                        sqlsrv_query($conn, $sqlStock, [$qty, $prod_id]);
                    }
                }
            }

            // MAJ Ticket
            $nouveau_statut = ($statut_final == 'resolu') ? 'traite' : 'attente_dispatch';
            $sqlTicket = "UPDATE TICKET SET ETAT = ? WHERE ID_TICKET = ?";
            sqlsrv_query($conn, $sqlTicket, [$nouveau_statut, $inter['ticket_id']]);
            
            require_once __DIR__ . '/../../utils/NotificationManager.php';
            $nm = new NotificationManager($conn);
            $ticket_id_notif = $inter['ticket_id'];
            
            if ($nouveau_statut === 'traite') {
                $nm->create("Ticket #$ticket_id_notif - Intervention terminée par le Tech (ticket traité).", 'dispatch', null, "/sav/src/modules/dispatch/interventions_list.php");
            } else {
                $nm->create("Ticket #$ticket_id_notif - Intervention terminée non résolue, à replanifier par le Dispatch.", 'dispatch', null, "/sav/src/modules/dispatch/assign_tech.php?ticket_id=$ticket_id_notif");
                $nm->create("Ticket #$ticket_id_notif - Ticket revenu du Tech non résolu, à reprendre par le TAC.", 'tac', null, "/sav/src/modules/tac/ticket_process.php?id=$ticket_id_notif");
            }
            
            header("Location: history.php?msg=report_saved");
            exit;
        } else {
            error_log('[TECH_REPORT_INTERVENTION] ' . db_last_error_message());
            $error = "Erreur interne lors de l'enregistrement du rapport.";
        }
    }
}

$pageTitle = "Rapport Intervention #" . htmlspecialchars($id);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <style>
        .form-section { background: var(--surface); border-radius: var(--r-md); padding: 24px; box-shadow: 0 4px 15px rgba(24,8,44,.05); border: 1px solid rgba(58,1,92,.08); margin-bottom: 24px; }
        .form-section-title { font-size: 1.15rem; color: var(--dark-amethyst-3); margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid rgba(58,1,92,.08); display:flex; align-items:center; gap:8px; font-weight:700;}
        .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; }
        .grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; }
        
        textarea.form-control { width: 100%; min-height: 100px; padding: 16px; border: 1px solid var(--border); border-radius: var(--r-md); font-family: inherit; font-size: 1rem; transition: border-color .2s; }
        textarea.form-control:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(155,93,229,.1); }
        
        .disabled-input { background-color: var(--surface-2); color: var(--text-muted); cursor: not-allowed; border-color: var(--border) !important;}
        
        .product-card { background: var(--surface-2); padding: 16px; border-radius: 12px; border: 1px solid rgba(58,1,92,.08); transition: transform 0.2s, box-shadow 0.2s; }
        .product-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(24,8,44,.08); }

        @media (max-width: 768px) {
            .grid-2, .grid-3 { grid-template-columns: 1fr; }
            .form-section { padding: 16px; }
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header>
             <div style="display:flex; align-items:center; gap:20px;">
                <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                <div style="display:flex; flex-direction:column;">
                    <h1 style="margin:0;"><i class="fa-solid fa-clipboard-check text-accent" style="margin-right:8px;"></i>Rapport d'Intervention</h1>
                    <span style="font-size:.9rem; color:var(--text-muted); font-weight:600;"><span class="badge badge-normal" style="font-family:monospace; margin-right:8px;">#<?= htmlspecialchars($id) ?></span> Clôture de mission</span>
                </div>
            </div>
            <a href="dashboard.php" class="btn btn-secondary" style="border-radius:30px;"><i class="fa-solid fa-arrow-left"></i> Retour</a>
        </header>

        <div class="page-content">

            <?php if($error): ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

            <!-- Info Ticket (Rappel) -->
            <div style="background: linear-gradient(135deg, rgba(33, 150, 243, 0.05), rgba(33, 150, 243, 0.1)); padding: 24px; margin-bottom: 24px; border-radius: var(--r-md); border-left: 4px solid #2196F3; position: relative; overflow: hidden;">
                <i class="fa-solid fa-circle-info" style="font-size:8rem; position:absolute; right:-20px; bottom:-20px; opacity:0.05; color:#2196F3;"></i>
                <h3 style="margin:0 0 16px; color:#1565C0; font-size:1.2rem; display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-bullhorn"></i>  Rappel de la demande initiale</h3>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:16px;">
                    <div>
                        <p style="margin:0 0 8px;"><strong style="color:var(--text); font-size:.9rem; text-transform:uppercase;">Client</strong><br>
                            <span style="font-size:1.1rem; font-weight:600; color:var(--primary);"><?= htmlspecialchars($inter['client_nom']) ?></span>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="openClientDetailsModal('<?= htmlspecialchars($inter['client_id']) ?>')" style="padding: 2px 8px; font-size: 0.8rem; margin-left: 8px;" title="Fiche Client"><i class="fa-solid fa-address-card"></i> Fiche Complète</button>
                        </p>
                        <p style="margin:0 0 8px;"><strong style="color:var(--text); font-size:.9rem; text-transform:uppercase;">Site</strong><br>
                            <span style="font-weight:600;"><i class="fa-solid fa-location-dot text-muted"></i> <?= htmlspecialchars($inter['site_nom'] . ' - ' . $inter['ville']) ?></span>
                        </p>
                        <p style="margin:0;"><strong style="color:var(--text); font-size:.9rem; text-transform:uppercase;">Contact Sur Place</strong><br>
                            <?= htmlspecialchars($inter['contact_sur_place']) ?>
                        </p>
                    </div>
                    <div>
                        <p style="margin:0;"><strong style="color:var(--text); font-size:.9rem; text-transform:uppercase;">Défaut Constaté</strong><br>
                            <div style="background:rgba(255,255,255,0.6); padding:12px; border-radius:8px; border:1px dashed #90CAF9; margin-top:4px; font-style:italic;">
                                <?= nl2br(htmlspecialchars($inter['ticket_desc'])) ?>
                            </div>
                        </p>
                    </div>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data">
                
                <!-- HORAIRES -->
                <div class="form-section">
                    <div class="form-section-title"><i class="fa-regular fa-clock text-accent"></i> Relevé d'heures (Pointage)</div>
                    <div class="grid-2">
                        <div style="background:var(--surface-2); padding:20px; border-radius:12px; border:1px solid rgba(58,1,92,.04);">
                            <h4 style="margin:0 0 16px; color:var(--dark-amethyst-3); text-align:center;"><i class="fa-regular fa-sun text-warning"></i> MATIN</h4>
                            <div class="grid-3">
                                <div class="form-group"><label>Heure Arrivée</label><input type="time" name="heure_arrivee_matin" class="form-control" style="background:var(--surface);"></div>
                                <div class="form-group"><label>Heure Départ</label><input type="time" name="heure_depart_matin" class="form-control" style="background:var(--surface);"></div>
                                <div class="form-group"><label>Trajet (Min)</label><input type="text" name="duree_trajet_matin" placeholder="ex: 30" class="form-control" style="background:var(--surface);"></div>
                            </div>
                        </div>
                        <div style="background:var(--surface-2); padding:20px; border-radius:12px; border:1px solid rgba(58,1,92,.04);">
                            <h4 style="margin:0 0 16px; color:var(--dark-amethyst-3); text-align:center;"><i class="fa-solid fa-moon" style="color:#64748b;"></i> APRÈS-MIDI / SOIR</h4>
                            <div class="grid-3">
                                <div class="form-group"><label>Heure Arrivée</label><input type="time" name="heure_arrivee_soir" class="form-control" style="background:var(--surface);"></div>
                                <div class="form-group"><label>Heure Départ</label><input type="time" name="heure_depart_soir" class="form-control" style="background:var(--surface);"></div>
                                <div class="form-group"><label>Trajet (Min)</label><input type="text" name="duree_trajet_soir" placeholder="ex: 30" class="form-control" style="background:var(--surface);"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DIAGNOSTIC -->
                <div class="form-section">
                    <div class="form-section-title"><i class="fa-solid fa-magnifying-glass-chart text-info"></i> Diagnostic Technique</div>
                    <div class="form-group">
                        <label>Travaux Demandés (Rappel Ticket ou Précision)</label>
                        <textarea name="travaux_demandes" rows="2" class="form-control disabled-input" readonly><?= htmlspecialchars($inter['ticket_desc']) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Travaux Recommandés (Préventif)</label>
                        <textarea name="travaux_recommandes" rows="3" class="form-control" placeholder="Indiquez ici vos recommandations techniques pour le client (ex: Prévoir remplacement batterie, Nettoyage des filtres)..."></textarea>
                    </div>
                </div>

                <!-- INTERVENTION -->
                <div class="form-section" style="border-left: 4px solid var(--success);">
                    <div class="form-section-title"><i class="fa-solid fa-screwdriver-wrench text-success"></i> Rapport d'Intervention</div>
                    <div class="form-group">
                        <label style="font-weight:700; color:var(--dark-amethyst-3); font-size:1.1rem;">Détail des Travaux Effectués <span class="text-danger">*</span></label>
                        <textarea name="rapport" rows="6" required class="form-control" placeholder="Décrivez en détail les manipulations, réparations et tests effectués lors de votre visite..."></textarea>
                    </div>
                </div>

                <!-- MATERIEL -->
                <div class="form-section">
                    <div class="form-section-title" style="justify-content:space-between;">
                        <span><i class="fa-solid fa-box-open text-warning"></i> Consommables & Matériel Remplacé</span>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="toggleProduits()" style="border-radius:20px;"><i class="fa-solid fa-bars-staggered"></i> Gérer Pièces</button>
                    </div>
                        
                    <div id="produits-section" style="display:none; margin-top:16px;">
                        <input type="text" id="searchPiece" placeholder="Rechercher une pièce..." class="form-control" style="margin-bottom:16px; max-width:400px; border-radius:30px; padding-left:20px;" onkeyup="filterPieces()">
                        <div class="grid-3" id="piecesContainer">
                            <?php 
                            if ($produits && sqlsrv_has_rows($produits)) {
                                while($p = sqlsrv_fetch_array($produits, SQLSRV_FETCH_ASSOC)) {
                                    echo '<div class="product-card piece-item">';
                                    echo '<label style="font-size:0.9rem; font-weight:600; display:block; margin-bottom:8px; line-height:1.3; color:var(--dark-amethyst-3);" class="piece-name">' . htmlspecialchars($p['nom']) . '</label>';
                                    echo '<div style="display:flex; justify-content:space-between; align-items:center;">';
                                    echo '<span class="badge badge-normal" style="font-size:0.75rem;">Stock: '.$p['stock'].'</span>';
                                    echo '<input type="number" name="produits['.$p['id'].']" min="0" max="'.$p['stock'].'" placeholder="Qté" class="form-control" style="width:70px; padding:6px; text-align:center;">';
                                    echo '</div></div>';
                                }
                            } else {
                                echo '<p class="text-muted">Aucun produit configuré dans la base.</p>';
                            }
                            ?>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-top:24px; padding-top:20px; border-top:1px dashed rgba(58,1,92,.1);">
                        <label><i class="fa-solid fa-camera text-muted"></i> Joindre une Photo / Document PDF (Optionnel)</label>
                        <input type="file" name="piece_jointe" class="form-control" style="padding:10px;">
                    </div>
                </div>

                <!-- CLOTURE -->
                <div class="form-section">
                    <div class="form-section-title"><i class="fa-solid fa-file-signature text-primary"></i> Validation Client & Clôture</div>
                    <div class="form-group">
                        <label>Commentaire du Client (Optionnel)</label>
                        <textarea name="commentaire_client" rows="2" class="form-control" placeholder="Observations formulées par le client sur place..."></textarea>
                    </div>
                    
                    <div class="grid-2">
                        <div class="form-group">
                            <label><i class="fa-solid fa-signature"></i> Nom du Signataire Client</label>
                            <input type="text" name="nom_signataire_client" class="form-control" placeholder="Nom de la personne qui valide l'intervention">
                        </div>
                        <div class="form-group">
                            <label><i class="fa-solid fa-flag-checkered"></i> Résultat Final de la mission <span class="text-danger">*</span></label>
                            <div style="position:relative;">
                                <select name="statut_final" required class="form-control" style="appearance:none; padding-right:40px; font-weight:600;">
                                    <option value="resolu">✅ Mission Accomplie (Tickets à clôturer)</option>
                                    <option value="non_resolu">❌ Mission Non-Achevée (Replanifier)</option>
                                </select>
                                <i class="fa-solid fa-chevron-down" style="position:absolute; right:15px; top:50%; transform:translateY(-50%); color:var(--text-muted); pointer-events:none;"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="position:sticky; bottom:20px; z-index:100;">
                    <button type="submit" class="btn btn-full" style="background:linear-gradient(135deg, var(--success), #059669); font-size: 1.15rem; padding: 18px; box-shadow:0 10px 25px rgba(16,185,129,.4); font-weight:700;">
                        <i class="fa-solid fa-check-double" style="margin-right:10px;"></i> Valider le rapport et Générer la Clôture
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Client Details -->
    <div id="clientDetailsModal" class="modal">
        <div class="modal-content" style="max-width:600px; padding:0; overflow:hidden;">
            <div class="modal-header" style="background:var(--surface-2); padding:24px; border-bottom:1px solid rgba(58,1,92,.08);">
                <h3 style="margin:0; color:var(--primary);"><i class="fa-solid fa-address-card text-accent"></i> Fiche Client Rapide</h3>
                <div class="close" onclick="closeClientDetailsModal()"><i class="fa-solid fa-xmark"></i></div>
            </div>
            
            <div id="clientDetailsContent" style="padding:24px; font-size:1.05rem; line-height:1.7;">
                <div style="text-align:center; color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin fa-2x"></i></div>
            </div>
            
            <div class="modal-footer" style="padding:16px 24px; background:var(--surface-2);">
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

        function toggleProduits() {
            var div = document.getElementById('produits-section');
            if (div.style.display === 'none' || div.style.display === '') {
                div.style.display = 'block';
                div.style.animation = "fadeIn 0.3s ease-out";
            } else {
                div.style.display = 'none';
            }
        }

        function filterPieces() {
            let input = document.getElementById('searchPiece').value.toLowerCase();
            let items = document.getElementsByClassName('piece-item');
            
            for (let i = 0; i < items.length; i++) {
                let name = items[i].getElementsByClassName('piece-name')[0].innerText.toLowerCase();
                if (name.includes(input)) {
                    items[i].style.display = "";
                } else {
                    items[i].style.display = "none";
                }
            }
        }

        async function openClientDetailsModal(clientId) {
            document.getElementById('clientDetailsModal').style.display = 'flex';
            document.getElementById('clientDetailsContent').innerHTML = '<div style="text-align:center; padding: 40px; color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin fa-2x"></i><br><br>Chargement des infos...</div>';
            
            try {
                const response = await fetch(`../api/get_client.php?id=${clientId}`);
                const data = await response.json();
                
                if (data.success) {
                    let html = `<div style="display:grid; grid-template-columns:1fr; gap:12px;">`;
                    for (const [key, value] of Object.entries(data.client)) {
                        if (value === null || value === '' || key.toLowerCase() === 'id') continue;
                        let displayValue = value;
                        if (key.toLowerCase() === 'email') {
                            displayValue = `<a href="mailto:${value}" style="color:var(--accent); font-weight:600;">${value}</a>`;
                        } else if (typeof value === 'string' && value.length > 50) {
                            displayValue = value.replace(/\n/g, '<br>');
                        }
                        const label = key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ');
                        html += `
                            <div style="background:var(--surface-2); padding:12px 16px; border-radius:8px; display:flex; flex-direction:column; gap:4px;">
                                <span style="font-size:.85rem; color:var(--text-muted); text-transform:uppercase; font-weight:700;">${label}</span>
                                <span style="word-break: break-word; font-weight:600; color:var(--text);">${displayValue}</span>
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
    </script>
</body>
</html>
