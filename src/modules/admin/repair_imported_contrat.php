<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['admin', 'directeur']);

function to_safe_string($value) {
    if ($value === null) {
        return '';
    }
    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d H:i:s');
    }
    if (is_scalar($value)) {
        return (string)$value;
    }
    return '';
}

function clean_input($value) {
    return trim(to_safe_string($value));
}

function normalize_contract_code($value) {
    $text = clean_input($value);
    if ($text === '') {
        return '';
    }
    if (is_numeric($text) && (strpos($text, '.') !== false || stripos($text, 'e') !== false)) {
        $text = sprintf('%.0f', (float)$text);
    }
    return trim($text);
}

function basic_norm($value) {
    $text = clean_input($value);
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_strtolower')) {
        $text = mb_strtolower($text, 'UTF-8');
    } else {
        $text = strtolower($text);
    }
    if (function_exists('iconv')) {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($ascii !== false) {
            $text = $ascii;
        }
    }
    return $text;
}

function contains_any($value, $keywords) {
    $text = basic_norm($value);
    foreach ($keywords as $keyword) {
        if ($text !== '' && strpos($text, $keyword) !== false) {
            return true;
        }
    }
    return false;
}

function looks_like_site_code($value) {
    $text = clean_input($value);
    return $text !== '' && preg_match('/^[A-Za-z]{2,}[A-Za-z0-9\-_]*\d+[A-Za-z0-9\-_]*$/', $text) === 1;
}

function looks_like_period($value) {
    $text = basic_norm($value);
    if ($text === '') {
        return false;
    }
    if (preg_match('/\b\d+\s*(jour|jours|semaine|semaines|mois|an|ans)\b/', $text)) {
        return true;
    }
    return contains_any($text, ['mensuel', 'mensuelle', 'trimestriel', 'trimestrielle', 'semestriel', 'semestrielle', 'annuel', 'annuelle', 'hebdo', 'hebdomadaire', 'fin de mois', 'fin mois', 'bimensuel']);
}

function looks_like_service($value) {
    return contains_any($value, ['support', 'maintenance', 'assistance', 'infogerance', 'helpdesk', 'monitoring', 'supervision', 'reseau', 'securite', 'fortinet', 'vpn', 'soc', 'sav', 'replace', 'remplacement', 'spare', 'piece', 'n1', 'n2', 'n3', 'it']);
}

function looks_like_city($value) {
    $text = clean_input($value);
    if ($text === '' || strlen($text) > 60) {
        return false;
    }
    if (looks_like_service($text) || looks_like_period($text)) {
        return false;
    }
    return preg_match('/^[\p{L}0-9\s\-\'\.,]{2,60}$/u', $text) === 1;
}

function looks_like_boolean($value) {
    return in_array(basic_norm($value), ['0', '1', 'true', 'false', 'oui', 'non', 'yes', 'no', 'o', 'n'], true);
}

function looks_like_hour_coverage($value) {
    $text = basic_norm($value);
    if ($text === '') {
        return false;
    }
    if (preg_match('/\b\d{1,2}\s*h\b/', $text)) {
        return true;
    }
    return preg_match('/\b\d{1,2}\s*[:h]\s*\d{0,2}\s*[-\/a]\s*\d{1,2}\s*[:h]?\s*\d{0,2}\b/', $text) === 1;
}

function looks_like_day_coverage($value) {
    $text = basic_norm($value);
    if ($text === '') {
        return false;
    }
    if (preg_match('/\b[1-7]\s*\/\s*7\b/', $text)) {
        return true;
    }
    return contains_any($text, ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche', 'semaine', 'weekend', 'week-end']);
}

function looks_like_status($value) {
    return contains_any($value, ['expire', 'expir', 'valide', 'invalide', 'actif', 'inactif', 'oui', 'non', 'true', 'false', 'ok', 'ko', 'resilie']);
}

function looks_like_excel_serial_date($value) {
    $text = clean_input($value);
    return $text !== '' && is_numeric($text) && (float)$text > 30000 && (float)$text < 100000;
}

function excel_serial_to_date($value) {
    if (!looks_like_excel_serial_date($value)) {
        return '';
    }
    $unix = (((int)(float)$value) - 25569) * 86400;
    return date('Y-m-d', $unix);
}

function auto_fix_contract(array $row) {
    $new = [
        'code_site' => clean_input($row['Code_Site'] ?? ''),
        'nom_site' => clean_input($row['Nom_Site'] ?? ''),
        'periode_facturation' => clean_input($row['Periode_Facturation'] ?? ''),
        'vpplanifier' => clean_input($row['VPPLANIFIER'] ?? ''),
        'service' => clean_input($row['SERVICE'] ?? ''),
        'couverture_heures' => clean_input($row['Couverture_Heures'] ?? ''),
        'couverture_jours' => clean_input($row['Couverture_Jours'] ?? ''),
        'ville' => clean_input($row['Ville'] ?? ''),
        'etat_redouane' => clean_input($row['ETAT_REDOUANE'] ?? ''),
        'dateresil' => clean_input($row['DATERESIL'] ?? ''),
    ];

    if (!looks_like_site_code($new['code_site']) && looks_like_site_code($new['nom_site'])) {
        $tmp = $new['code_site'];
        $new['code_site'] = $new['nom_site'];
        $new['nom_site'] = $tmp;
    }

    $shifted = (
        !looks_like_boolean($new['vpplanifier'])
        && (looks_like_service($new['vpplanifier']) || looks_like_hour_coverage($new['service']))
        && looks_like_hour_coverage($new['service'])
        && looks_like_day_coverage($new['couverture_heures'])
        && looks_like_city($new['couverture_jours'])
        && ($new['ville'] !== '' || looks_like_status($new['ville']))
    );

    if ($shifted) {
        $oldVp = $new['vpplanifier'];
        $oldService = $new['service'];
        $oldHeures = $new['couverture_heures'];
        $oldJours = $new['couverture_jours'];
        $oldVille = $new['ville'];

        $new['vpplanifier'] = 'false';
        $new['service'] = $oldVp;
        $new['couverture_heures'] = $oldService;
        $new['couverture_jours'] = $oldHeures;
        $new['ville'] = $oldJours;
        $new['etat_redouane'] = $oldVille;
    }

    if ($new['vpplanifier'] === '') {
        $new['vpplanifier'] = 'false';
    }

    if ($new['dateresil'] === '' && looks_like_excel_serial_date($new['etat_redouane'])) {
        $new['dateresil'] = excel_serial_to_date($new['etat_redouane']);
    }

    return $new;
}

$pageTitle = 'Réparer contrats importés';
$message = '';
$messageType = 'info';
$searchedCode = '';
$contract = null;
$suggested = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $code = normalize_contract_code($_POST['code_contrat'] ?? '');
    $searchedCode = $code;

    if ($action === 'load') {
        if ($code === '') {
            $message = 'Saisis un code contrat.';
            $messageType = 'error';
        } else {
            $stmt = query("SELECT TOP 1 * FROM CONTRAT WHERE LTRIM(RTRIM(ISNULL(CODE_CONTRAT, ''))) = LTRIM(RTRIM(?))", [$code]);
            $contract = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
            if (!$contract) {
                $message = 'Contrat introuvable.';
                $messageType = 'error';
            } else {
                $suggested = auto_fix_contract($contract);
                $message = 'Contrat chargé.';
                $messageType = 'success';
            }
        }
    }

    if ($action === 'save') {
        $id = $_POST['id_contrat'] ?? '';
        $fields = [
            'Code_Client' => clean_input($_POST['code_client'] ?? ''),
            'Nom_Client' => clean_input($_POST['nom_client'] ?? ''),
            'Date_Creation' => clean_input($_POST['date_creation'] ?? ''),
            'Date_Debut' => clean_input($_POST['date_debut'] ?? ''),
            'PERIODE' => clean_input($_POST['periode'] ?? ''),
            'Date_Fin' => clean_input($_POST['date_fin'] ?? ''),
            'Date_Signature' => clean_input($_POST['date_signature'] ?? ''),
            'AVENANT' => clean_input($_POST['avenant'] ?? ''),
            'Contrat_Originale' => clean_input($_POST['contrat_originale'] ?? ''),
            'ETAT' => clean_input($_POST['etat'] ?? ''),
            'TYPE' => clean_input($_POST['type'] ?? ''),
            'VP' => clean_input($_POST['vp'] ?? ''),
            'Code_Site' => clean_input($_POST['code_site'] ?? ''),
            'Nom_Site' => clean_input($_POST['nom_site'] ?? ''),
            'Montant_Contrat' => clean_input($_POST['montant_contrat'] ?? ''),
            'Mode_Facturation' => clean_input($_POST['mode_facturation'] ?? ''),
            'Periode_Facturation' => clean_input($_POST['periode_facturation'] ?? ''),
            'Echeance_Facturation' => clean_input($_POST['echeance_facturation'] ?? ''),
            'VPPLANIFIER' => clean_input($_POST['vpplanifier'] ?? ''),
            'SERVICE' => clean_input($_POST['service'] ?? ''),
            'Couverture_Heures' => clean_input($_POST['couverture_heures'] ?? ''),
            'Couverture_Jours' => clean_input($_POST['couverture_jours'] ?? ''),
            'Ville' => clean_input($_POST['ville'] ?? ''),
            'ETAT_REDOUANE' => clean_input($_POST['etat_redouane'] ?? ''),
            'DATERESIL' => clean_input($_POST['dateresil'] ?? ''),
            'ID_CLIENT' => clean_input($_POST['id_client'] ?? ''),
            'NBRFACTURE' => clean_input($_POST['nbrfacture'] ?? ''),
            'RESERVES' => clean_input($_POST['reserves'] ?? ''),
        ];

        $sql = "UPDATE CONTRAT SET
                    Code_Client = ?, Nom_Client = ?, Date_Creation = NULLIF(?, ''), Date_Debut = NULLIF(?, ''), PERIODE = ?, Date_Fin = NULLIF(?, ''),
                    Date_Signature = NULLIF(?, ''), AVENANT = ?, Contrat_Originale = ?, ETAT = ?, TYPE = ?, VP = ?, Code_Site = ?, Nom_Site = ?,
                    Montant_Contrat = NULLIF(?, ''), Mode_Facturation = ?, Periode_Facturation = ?, Echeance_Facturation = ?, VPPLANIFIER = ?, SERVICE = ?,
                    Couverture_Heures = ?, Couverture_Jours = ?, Ville = ?, ETAT_REDOUANE = ?, DATERESIL = NULLIF(?, ''), ID_CLIENT = ?, NBRFACTURE = NULLIF(?, ''), RESERVES = ?
                WHERE ID_CONTRAT = ?";
        $params = array_values($fields);
        $params[] = $id;

        $ok = sqlsrv_query($conn, $sql, $params);
        if ($ok) {
            $message = 'Contrat enregistré.';
            $messageType = 'success';
        } else {
            error_log('[ADMIN_REPAIR_IMPORTED_CONTRAT_SAVE] ' . db_last_error_message());
            $message = 'Erreur lors de la sauvegarde du contrat.';
            $messageType = 'error';
        }

        $stmt = query("SELECT TOP 1 * FROM CONTRAT WHERE ID_CONTRAT = ?", [$id]);
        $contract = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
        $suggested = $contract ? auto_fix_contract($contract) : null;
    }

    if ($action === 'auto_fix' && !empty($_POST['id_contrat'])) {
        $id = $_POST['id_contrat'];
        $stmt = query("SELECT TOP 1 * FROM CONTRAT WHERE ID_CONTRAT = ?", [$id]);
        $contract = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
        if ($contract) {
            $suggested = auto_fix_contract($contract);
            if ($suggested) {
                $sql = "UPDATE CONTRAT SET
                            Code_Client = ?, Nom_Client = ?, Code_Site = ?, Nom_Site = ?, PERIODE = ?, VPPLANIFIER = ?, SERVICE = ?, Couverture_Heures = ?,
                            Couverture_Jours = ?, Ville = ?, ETAT_REDOUANE = ?, DATERESIL = CASE WHEN ? = '' THEN DATERESIL ELSE ? END
                        WHERE ID_CONTRAT = ?";
                $params = [
                    clean_input($contract['Code_Client'] ?? ''),
                    clean_input($contract['Nom_Client'] ?? ''),
                    clean_input($suggested['code_site'] ?? $contract['Code_Site'] ?? ''),
                    clean_input($suggested['nom_site'] ?? $contract['Nom_Site'] ?? ''),
                    clean_input($contract['PERIODE'] ?? ''),
                    clean_input($suggested['vpplanifier'] ?? $contract['VPPLANIFIER'] ?? ''),
                    clean_input($suggested['service'] ?? $contract['SERVICE'] ?? ''),
                    clean_input($suggested['couverture_heures'] ?? $contract['Couverture_Heures'] ?? ''),
                    clean_input($suggested['couverture_jours'] ?? $contract['Couverture_Jours'] ?? ''),
                    clean_input($suggested['ville'] ?? $contract['Ville'] ?? ''),
                    clean_input($suggested['etat_redouane'] ?? $contract['ETAT_REDOUANE'] ?? ''),
                    clean_input($suggested['dateresil'] ?? $contract['DATERESIL'] ?? ''),
                    clean_input($suggested['dateresil'] ?? $contract['DATERESIL'] ?? ''),
                    $id
                ];
                $ok = sqlsrv_query($conn, $sql, $params);
                if ($ok) {
                    $message = 'Auto-correction appliquée.';
                    $messageType = 'success';
                } else {
                    error_log('[ADMIN_REPAIR_IMPORTED_CONTRAT_AUTOFIX] ' . db_last_error_message());
                    $message = 'Erreur lors de l\'auto-correction.';
                    $messageType = 'error';
                }
                $stmt = query("SELECT TOP 1 * FROM CONTRAT WHERE ID_CONTRAT = ?", [$id]);
                $contract = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
                $suggested = $contract ? auto_fix_contract($contract) : null;
            } else {
                $message = 'Aucune incohérence détectée pour ce contrat.';
                $messageType = 'info';
            }
        }
    }
}

if (isset($_GET['code_contrat']) && $contract === null) {
    $searchedCode = normalize_contract_code($_GET['code_contrat']);
    if ($searchedCode !== '') {
        $stmt = query("SELECT TOP 1 * FROM CONTRAT WHERE LTRIM(RTRIM(ISNULL(CODE_CONTRAT, ''))) = LTRIM(RTRIM(?))", [$searchedCode]);
        $contract = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
        $suggested = $contract ? auto_fix_contract($contract) : null;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <style>
        .repair-wrap { max-width: 1280px; margin: 0 auto; }
        .top-hero { background: linear-gradient(135deg, rgba(58,1,92,.08), rgba(155,93,229,.12)); border: 1px solid rgba(155,93,229,.2); border-radius: 18px; padding: 22px; margin-bottom: 18px; }
        .hero-title { margin: 0 0 6px; color: var(--dark-amethyst-3); }
        .hero-text { margin: 0; color: var(--text-muted); }
        .grid-two { display: grid; grid-template-columns: 1.1fr .9fr; gap: 18px; }
        .panel { background: var(--surface); border: 1px solid rgba(58,1,92,.08); border-radius: 16px; box-shadow: 0 4px 15px rgba(24,8,44,.05); padding: 18px; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 12px; }
        .form-grid .full { grid-column: 1 / -1; }
        .field { display: flex; flex-direction: column; gap: 6px; }
        .field label { font-weight: 700; color: var(--dark-amethyst-3); font-size: .9rem; }
        .field input, .field textarea { width: 100%; border: 1px solid var(--border); border-radius: 12px; padding: 10px 12px; font-family: inherit; background: #fff; }
        .field input:focus, .field textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(155,93,229,.12); }
        .toolbar { display:flex; gap:10px; flex-wrap: wrap; margin-top: 12px; }
        .badge-soft { display:inline-flex; align-items:center; gap:6px; padding:5px 10px; border-radius:999px; background: rgba(58,1,92,.08); color: var(--dark-amethyst-3); font-size:.8rem; font-weight:700; }
        .diff-box { border: 1px solid rgba(58,1,92,.08); border-radius: 12px; overflow: hidden; }
        .diff-box table { width: 100%; border-collapse: collapse; }
        .diff-box td { padding: 9px 10px; border-bottom: 1px solid rgba(58,1,92,.06); vertical-align: top; }
        .diff-box tr:last-child td { border-bottom: none; }
        .diff-box td:first-child { width: 32%; color: var(--text-muted); background: rgba(58,1,92,.03); font-weight:700; }
        .old-val { color: #374151; }
        .new-val { color: var(--success); font-weight: 700; }
        .muted { color: var(--text-muted); }
        .sticky-actions { position: sticky; bottom: 0; background: linear-gradient(180deg, transparent 0%, rgba(255,255,255,.92) 20%, #fff 100%); padding-top: 12px; margin-top: 14px; display:flex; gap:10px; flex-wrap: wrap; }
        @media (max-width: 980px) { .grid-two { grid-template-columns: 1fr; } }
        @media (max-width: 640px) { .form-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header>
            <div style="display:flex; align-items:center; gap:20px;">
                <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                <div style="display:flex; flex-direction:column;">
                    <h1 style="margin:0;"><i class="fa-solid fa-file-circle-check text-accent"></i> Réparer un contrat importé</h1>
                    <span style="font-size:.9rem; color:var(--text-muted); font-weight:600;">Recherche, correction manuelle et auto-correction</span>
                </div>
            </div>
            <span class="badge badge-normal"><i class="fa-solid fa-shield-halved"></i> Admin</span>
        </header>

        <div class="page-content repair-wrap">
            <div class="top-hero">
                <h2 class="hero-title">Page de réparation des contrats importés</h2>
                <p class="hero-text">Utilise cette page si l’import a décalé les champs. Tu peux charger un contrat par son code, voir les valeurs actuelles, corriger manuellement, ou lancer l’auto-correction.</p>
            </div>

            <?php if ($message): ?>
                <div class="alert <?= $messageType === 'success' ? 'alert-success' : ($messageType === 'error' ? 'alert-error' : 'alert-info') ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <div class="panel" style="margin-bottom:18px;">
                <form method="POST" class="form-grid">
                    <div class="field full">
                        <label for="code_contrat">Code contrat</label>
                        <input type="text" id="code_contrat" name="code_contrat" value="<?= htmlspecialchars($searchedCode) ?>" placeholder="Ex: 01/EIT/2006/DOI">
                    </div>
                    <div class="toolbar full">
                        <button type="submit" name="action" value="load" class="btn"><i class="fa-solid fa-magnifying-glass"></i> Charger</button>
                        <a href="repair_imported_contrat.php" class="btn btn-secondary"><i class="fa-solid fa-rotate-left"></i> Réinitialiser</a>
                    </div>
                </form>
            </div>

            <?php if ($contract): ?>
                <div class="grid-two">
                    <div class="panel">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px; margin-bottom:14px; flex-wrap:wrap;">
                            <div>
                                <h3 style="margin:0 0 6px; color:var(--dark-amethyst-3);">Contrat <?= htmlspecialchars(to_safe_string($contract['CODE_CONTRAT'] ?? '')) ?></h3>
                                <span class="badge-soft"><i class="fa-solid fa-user"></i> Client <?= htmlspecialchars(to_safe_string($contract['Code_Client'] ?? $contract['ID_CLIENT'] ?? '—')) ?></span>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="action" value="auto_fix">
                                <input type="hidden" name="id_contrat" value="<?= htmlspecialchars(to_safe_string($contract['ID_CONTRAT'] ?? '')) ?>">
                                <button type="submit" class="btn btn-secondary" onclick="return confirm('Appliquer l\'auto-correction sur ce contrat ?');"><i class="fa-solid fa-wand-magic-sparkles"></i> Auto-corriger</button>
                            </form>
                        </div>

                        <div class="diff-box">
                            <table>
                                <tr><td>Code contrat</td><td class="old-val"><?= htmlspecialchars(to_safe_string($contract['CODE_CONTRAT'] ?? '')) ?></td></tr>
                                <tr><td>Client code</td><td class="old-val"><?= htmlspecialchars(to_safe_string($contract['Code_Client'] ?? '')) ?></td></tr>
                                <tr><td>Client nom</td><td class="old-val"><?= htmlspecialchars(to_safe_string($contract['Nom_Client'] ?? '')) ?></td></tr>
                                <tr><td>Code site</td><td class="old-val"><?= htmlspecialchars(to_safe_string($contract['Code_Site'] ?? '')) ?></td></tr>
                                <tr><td>Nom site</td><td class="old-val"><?= htmlspecialchars(to_safe_string($contract['Nom_Site'] ?? '')) ?></td></tr>
                                <tr><td>VPPLANIFIER</td><td class="old-val"><?= htmlspecialchars(to_safe_string($contract['VPPLANIFIER'] ?? '')) ?></td></tr>
                                <tr><td>SERVICE</td><td class="old-val"><?= htmlspecialchars(to_safe_string($contract['SERVICE'] ?? '')) ?></td></tr>
                                <tr><td>Couverture heures</td><td class="old-val"><?= htmlspecialchars(to_safe_string($contract['Couverture_Heures'] ?? '')) ?></td></tr>
                                <tr><td>Couverture jours</td><td class="old-val"><?= htmlspecialchars(to_safe_string($contract['Couverture_Jours'] ?? '')) ?></td></tr>
                                <tr><td>Ville</td><td class="old-val"><?= htmlspecialchars(to_safe_string($contract['Ville'] ?? '')) ?></td></tr>
                                <tr><td>Etat redouane</td><td class="old-val"><?= htmlspecialchars(to_safe_string($contract['ETAT_REDOUANE'] ?? '')) ?></td></tr>
                                <tr><td>Date résiliation</td><td class="old-val"><?= htmlspecialchars(to_safe_string($contract['DATERESIL'] ?? '')) ?></td></tr>
                            </table>
                        </div>
                    </div>

                    <div class="panel">
                        <h3 style="margin-top:0; color:var(--dark-amethyst-3);">Edition manuelle</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="save">
                            <input type="hidden" name="id_contrat" value="<?= htmlspecialchars(to_safe_string($contract['ID_CONTRAT'] ?? '')) ?>">
                            <div class="form-grid">
                                <div class="field">
                                    <label>Code Client</label>
                                    <input type="text" name="code_client" value="<?= htmlspecialchars(to_safe_string($contract['Code_Client'] ?? '')) ?>">
                                </div>
                                <div class="field">
                                    <label>Nom Client</label>
                                    <input type="text" name="nom_client" value="<?= htmlspecialchars(to_safe_string($contract['Nom_Client'] ?? '')) ?>">
                                </div>
                                <div class="field">
                                    <label>Date Création</label>
                                    <input type="text" name="date_creation" value="<?= htmlspecialchars(to_safe_string($contract['Date_Creation'] ?? '')) ?>" placeholder="YYYY-MM-DD ou YYYY-MM-DD HH:MM:SS">
                                </div>
                                <div class="field">
                                    <label>Date Début</label>
                                    <input type="text" name="date_debut" value="<?= htmlspecialchars(to_safe_string($contract['Date_Debut'] ?? '')) ?>" placeholder="YYYY-MM-DD ou YYYY-MM-DD HH:MM:SS">
                                </div>
                                <div class="field">
                                    <label>PERIODE</label>
                                    <input type="text" name="periode" value="<?= htmlspecialchars(to_safe_string($contract['PERIODE'] ?? '')) ?>">
                                </div>
                                <div class="field">
                                    <label>Date Fin</label>
                                    <input type="text" name="date_fin" value="<?= htmlspecialchars(to_safe_string($contract['Date_Fin'] ?? '')) ?>" placeholder="YYYY-MM-DD ou YYYY-MM-DD HH:MM:SS">
                                </div>
                                <div class="field">
                                    <label>Date Signature</label>
                                    <input type="text" name="date_signature" value="<?= htmlspecialchars(to_safe_string($contract['Date_Signature'] ?? '')) ?>" placeholder="YYYY-MM-DD ou YYYY-MM-DD HH:MM:SS">
                                </div>
                                <div class="field">
                                    <label>AVENANT</label>
                                    <input type="text" name="avenant" value="<?= htmlspecialchars(to_safe_string($contract['AVENANT'] ?? '')) ?>">
                                </div>
                                <div class="field full">
                                    <label>Contrat Originale</label>
                                    <input type="text" name="contrat_originale" value="<?= htmlspecialchars(to_safe_string($contract['Contrat_Originale'] ?? '')) ?>">
                                </div>
                                <div class="field">
                                    <label>ETAT</label>
                                    <input type="text" name="etat" value="<?= htmlspecialchars(to_safe_string($contract['ETAT'] ?? '')) ?>">
                                </div>
                                <div class="field">
                                    <label>TYPE</label>
                                    <input type="text" name="type" value="<?= htmlspecialchars(to_safe_string($contract['TYPE'] ?? '')) ?>">
                                </div>
                                <div class="field">
                                    <label>VP</label>
                                    <input type="text" name="vp" value="<?= htmlspecialchars(to_safe_string($contract['VP'] ?? '')) ?>">
                                </div>
                                <div class="field">
                                    <label>Montant Contrat</label>
                                    <input type="text" name="montant_contrat" value="<?= htmlspecialchars(to_safe_string($contract['Montant_Contrat'] ?? '')) ?>">
                                </div>
                                <div class="field">
                                    <label>Code Site</label>
                                    <input type="text" name="code_site" value="<?= htmlspecialchars(to_safe_string($contract['Code_Site'] ?? '')) ?>">
                                </div>
                                <div class="field">
                                    <label>Nom Site</label>
                                    <input type="text" name="nom_site" value="<?= htmlspecialchars(to_safe_string($contract['Nom_Site'] ?? '')) ?>">
                                </div>
                                <div class="field">
                                    <label>Mode Facturation</label>
                                    <input type="text" name="mode_facturation" value="<?= htmlspecialchars(to_safe_string($contract['Mode_Facturation'] ?? '')) ?>">
                                </div>
                                <div class="field">
                                    <label>Période Facturation</label>
                                    <input type="text" name="periode_facturation" value="<?= htmlspecialchars(to_safe_string($contract['Periode_Facturation'] ?? '')) ?>">
                                </div>
                                <div class="field">
                                    <label>Echéance Facturation</label>
                                    <input type="text" name="echeance_facturation" value="<?= htmlspecialchars(to_safe_string($contract['Echeance_Facturation'] ?? '')) ?>">
                                </div>
                                <div class="field">
                                    <label>VPPLANIFIER</label>
                                    <input type="text" name="vpplanifier" value="<?= htmlspecialchars(to_safe_string($contract['VPPLANIFIER'] ?? '')) ?>">
                                </div>
                                <div class="field full">
                                    <label>SERVICE</label>
                                    <input type="text" name="service" value="<?= htmlspecialchars(to_safe_string($contract['SERVICE'] ?? '')) ?>">
                                </div>
                                <div class="field">
                                    <label>Couverture Heures</label>
                                    <input type="text" name="couverture_heures" value="<?= htmlspecialchars(to_safe_string($contract['Couverture_Heures'] ?? '')) ?>">
                                </div>
                                <div class="field">
                                    <label>Couverture Jours</label>
                                    <input type="text" name="couverture_jours" value="<?= htmlspecialchars(to_safe_string($contract['Couverture_Jours'] ?? '')) ?>">
                                </div>
                                <div class="field">
                                    <label>Ville</label>
                                    <input type="text" name="ville" value="<?= htmlspecialchars(to_safe_string($contract['Ville'] ?? '')) ?>">
                                </div>
                                <div class="field">
                                    <label>ETAT_REDOUANE</label>
                                    <input type="text" name="etat_redouane" value="<?= htmlspecialchars(to_safe_string($contract['ETAT_REDOUANE'] ?? '')) ?>">
                                </div>
                                <div class="field">
                                    <label>DATERESIL</label>
                                    <input type="text" name="dateresil" value="<?= htmlspecialchars(to_safe_string($contract['DATERESIL'] ?? '')) ?>" placeholder="YYYY-MM-DD">
                                </div>
                                <div class="field">
                                    <label>ID_CLIENT</label>
                                    <input type="text" name="id_client" value="<?= htmlspecialchars(to_safe_string($contract['ID_CLIENT'] ?? '')) ?>">
                                </div>
                                <div class="field">
                                    <label>NBRFACTURE</label>
                                    <input type="text" name="nbrfacture" value="<?= htmlspecialchars(to_safe_string($contract['NBRFACTURE'] ?? '')) ?>">
                                </div>
                                <div class="field full">
                                    <label>RESERVES</label>
                                    <textarea name="reserves" rows="4"><?= htmlspecialchars(to_safe_string($contract['RESERVES'] ?? '')) ?></textarea>
                                </div>
                            </div>

                            <div class="sticky-actions">
                                <button type="submit" class="btn"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
                                <button type="submit" name="action" value="auto_fix" class="btn btn-secondary" onclick="return confirm('Appliquer l\'auto-correction sur les champs détectés ?');"><i class="fa-solid fa-wand-magic-sparkles"></i> Auto-corriger puis enregistrer</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php elseif ($searchedCode !== ''): ?>
                <div class="alert alert-info">Aucun contrat chargé. Essaie avec un autre code contrat.</div>
            <?php endif; ?>
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