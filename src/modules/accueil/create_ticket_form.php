<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['accueil', 'admin']);

if (!isset($_GET['client_id'])) {
    header("Location: create_ticket_search.php");
    exit;
}

$client_id = $_GET['client_id'];

// Infos Client
$client = sqlsrv_fetch_array(query("SELECT ID_Client as id, Nom as nom FROM SAV_Clients WHERE ID_Client = ?", [$client_id]), SQLSRV_FETCH_ASSOC);

// Sites du Client
$sites = query("SELECT Id_Site as id, Nom as nom FROM SAV_Sites WHERE Id_Client = ?", [$client_id]);

// Contrats du Client (tolère import via ID_CLIENT ou Code_Client)
$contrats = query("SELECT ID_CONTRAT as id, CODE_CONTRAT as numero_contrat
                FROM CONTRAT
                WHERE (LTRIM(RTRIM(ISNULL(ID_CLIENT, ''))) = LTRIM(RTRIM(?))
                     OR LTRIM(RTRIM(ISNULL(Code_Client, ''))) = LTRIM(RTRIM(?)))
                 AND (Date_Fin IS NULL
                     OR CAST(Date_Fin AS DATE) >= CAST(GETDATE() AS DATE)
                     OR UPPER(LTRIM(RTRIM(ISNULL(ETAT, '')))) IN ('ACTIF', 'EN_ATTENTE_SIGNATURE', 'RENOUVELLEMENT'))
                ORDER BY Date_Fin DESC", [$client_id, $client_id]);

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $isDirectDispatchMode = get_app_setting('route_accueil_direct_dispatch', '0') === '1';
    $initialStatus = $isDirectDispatchMode ? 'attente_dispatch' : 'ouvert';

    $site_id = $_POST['site_id'];
    $contrat_id = !empty($_POST['contrat_id']) ? $_POST['contrat_id'] : null;
    $contact_source = $_POST['contact_source'];
    $priorite = $_POST['priorite'];
    $type_probleme = $_POST['type_probleme'];
    $description = $_POST['description'];

    $sujet = $_POST['sujet'];
    $contact_sur_place = $_POST['contact_sur_place'];

    // Validation basique
    if(empty($site_id) || empty($description) || empty($sujet)) {
        $error = "Le site, le sujet et la description sont obligatoires.";
    } else {
        $description_complete = "Source: $contact_source\nContact sur place: $contact_sur_place\nType de problème: $type_probleme\nDescription:\n$description";
        $new_id = uniqid('TIC-');
        $code = 'TIC-' . date('Ymd-Hi');
        $sql = "INSERT INTO TICKET (ID_TICKET, ID_CLIENT, ID_SITE, CODE, PRIORITE, COMMENT, ETAT, DATE, OBJET)
                VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE(), ?)";

        $params = [$new_id, $client_id, $site_id, $code, $priorite, $description_complete, $initialStatus, $sujet];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            require_once __DIR__ . '/../../utils/NotificationManager.php';
            $nm = new NotificationManager($conn);
            if ($isDirectDispatchMode) {
                $nm->create("Ticket #$new_id - Nouveau ticket à planifier (routage direct Accueil).", 'dispatch', null, "/sav/src/modules/dispatch/assign_tech.php?ticket_id=$new_id");
            } else {
                $nm->create("Ticket #$new_id - Nouveau ticket créé par l'Accueil, à traiter par le TAC.", 'tac', null, "/sav/src/modules/tac/ticket_process.php?id=$new_id");
            }
            header("Location: dashboard.php?msg=ticket_created");
            exit;
        } else {
            error_log('[ACCUEIL_CREATE_TICKET_FORM] ' . db_last_error_message());
            $error = "Erreur lors de la creation du ticket.";
        }
    }
}

$pageTitle = "Nouveau Ticket : " . $client['nom'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header>
            <h1>Ouvrir un Ticket</h1>
            <p>Client : <strong><?php echo htmlspecialchars($client['nom']); ?></strong></p>
        </header>

        <?php if($error): ?><div class="card" style="color:red;"><?php echo $error; ?></div><?php endif; ?>

        <div class="card">
            <form method="POST">
                <div class="form-group">
                    <label>Site concerné *</label>
                    <select name="site_id" required>
                        <option value="">-- Choisir un site --</option>
                        <?php while($s = sqlsrv_fetch_array($sites, SQLSRV_FETCH_ASSOC)): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['nom']) . ' (' . htmlspecialchars($s['ville']) . ')'; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Contrat (Optionnel)</label>
                    <select name="contrat_id">
                        <option value="">-- Aucun / Hors contrat --</option>
                        <?php while($c = sqlsrv_fetch_array($contrats, SQLSRV_FETCH_ASSOC)): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['numero_contrat']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Source du contact *</label>
                    <select name="contact_source" required>
                        <option value="telephone">Téléphone</option>
                        <option value="email">Email</option>
                        <option value="fax">Fax</option>
                    </select>
                <div class="form-group">
                    <label>Source du contact *</label>
                    <select name="contact_source" required>
                        <option value="telephone">Téléphone</option>
                        <option value="email">Email</option>
                        <option value="fax">Fax</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Personne contact sur place (Nom/Tél)</label>
                    <input type="text" name="contact_sur_place" placeholder="Ex: M. Alaoui 06...">
                </div>

                <div class="form-group">
                    <label>Priorité *</label>
                    <select name="priorite" required>
                        <option value="normale" selected>Normale</option>
                        <option value="basse">Basse</option>
                        <option value="haute" style="color:orange; font-weight:bold;">Haute</option>
                        <option value="urgente" style="color:red; font-weight:bold;">Urgente</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Sujet / Titre *</label>
                    <input type="text" name="sujet" required placeholder="Résumé court du problème (ex: Panne Serveur)">
                </div>

                <div class="form-group">
                    <label>Type de problème</label>
                    <input type="text" name="type_probleme" placeholder="Ex: Panne réseau, Imprimante bloquée...">
                </div>

                <div class="form-group">
                    <label>Description Détaillée *</label>
                    <textarea name="description" rows="5" required placeholder="Détails du problème..."></textarea>
                </div>

                <button type="submit" class="btn btn-full">Créer le Ticket</button>
            </form>
        </div>
    </div>
</body>
</html>
