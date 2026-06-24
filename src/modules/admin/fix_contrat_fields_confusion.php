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

function normalize_text_basic($value) {
    $text = trim(to_safe_string($value));
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

function contains_any_keyword($value, $keywords) {
    $text = normalize_text_basic($value);
    if ($text === '') {
        return false;
    }

    foreach ($keywords as $keyword) {
        if (strpos($text, $keyword) !== false) {
            return true;
        }
    }
    return false;
}

function looks_like_period_value($value) {
    $text = normalize_text_basic($value);
    if ($text === '') {
        return false;
    }

    if (preg_match('/\b\d+\s*(jour|jours|semaine|semaines|mois|an|ans)\b/', $text)) {
        return true;
    }

    $keywords = [
        'mensuel', 'mensuelle', 'trimestriel', 'trimestrielle', 'semestriel', 'semestrielle',
        'annuel', 'annuelle', 'hebdo', 'hebdomadaire', 'fin de mois', 'fin mois', 'bimensuel'
    ];

    return contains_any_keyword($text, $keywords);
}

function looks_like_service_value($value) {
    $keywords = [
        'support', 'maintenance', 'assistance', 'infogerance', 'helpdesk', 'monitoring', 'supervision',
        'reseau', 'securite', 'fortinet', 'vpn', 'soc', 'sav', 'n1', 'n2', 'n3', 'it',
        'replace', 'remplacement', 'spare', 'piece'
    ];
    return contains_any_keyword($value, $keywords);
}

function looks_like_city_value($value) {
    $text = trim(to_safe_string($value));
    if ($text === '') {
        return false;
    }

    $norm = normalize_text_basic($text);
    if (looks_like_service_value($norm) || looks_like_period_value($norm)) {
        return false;
    }

    if (strlen($text) > 60) {
        return false;
    }

    return preg_match('/^[\p{L}0-9\s\-\'\.,]{2,60}$/u', $text) === 1;
}

function looks_like_site_code($value) {
    $text = trim(to_safe_string($value));
    if ($text === '') {
        return false;
    }

    return preg_match('/^[A-Za-z]{2,}[A-Za-z0-9\-_]*\d+[A-Za-z0-9\-_]*$/', $text) === 1;
}

function looks_like_hour_coverage_value($value) {
    $text = normalize_text_basic($value);
    if ($text === '') {
        return false;
    }

    if (preg_match('/\b\d{1,2}\s*h\b/', $text)) {
        return true;
    }

    return preg_match('/\b\d{1,2}\s*[:h]\s*\d{0,2}\s*[-\\/a]\s*\d{1,2}\s*[:h]?\s*\d{0,2}\b/', $text) === 1;
}

function looks_like_day_coverage_value($value) {
    $text = normalize_text_basic($value);
    if ($text === '') {
        return false;
    }

    if (preg_match('/\b[1-7]\s*\/\s*7\b/', $text)) {
        return true;
    }

    $keywords = [
        'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche',
        'semaine', 'weekend', 'week-end'
    ];

    return contains_any_keyword($text, $keywords);
}

function looks_like_status_value($value) {
    $text = normalize_text_basic($value);
    if ($text === '') {
        return false;
    }

    $keywords = [
        'expire', 'expir', 'valide', 'invalide', 'actif', 'inactif', 'oui', 'non', 'true', 'false', 'ok', 'ko', 'resilie'
    ];

    return contains_any_keyword($text, $keywords);
}

function looks_like_boolean_value($value) {
    $text = normalize_text_basic($value);
    if ($text === '') {
        return false;
    }

    return in_array($text, ['0', '1', 'true', 'false', 'oui', 'non', 'yes', 'no', 'o', 'n'], true);
}

function looks_like_excel_serial_date($value) {
    $text = trim(to_safe_string($value));
    if ($text === '' || !is_numeric($text)) {
        return false;
    }

    $num = (float)$text;
    return $num > 30000 && $num < 100000;
}

function excel_serial_to_date_ymd($value) {
    if (!looks_like_excel_serial_date($value)) {
        return null;
    }

    $num = (float)$value;
    $unix = ((int)$num - 25569) * 86400;
    $result = date('Y-m-d', $unix);
    $parts = explode('-', $result);
    if (count($parts) === 3 && checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) {
        return $result;
    }
    return null;
}

function suggest_fixed_fields($row) {
    $original = [
        'code_site' => trim(to_safe_string($row['Code_Site'] ?? '')),
        'nom_site' => trim(to_safe_string($row['Nom_Site'] ?? '')),
        'periode_facturation' => trim(to_safe_string($row['Periode_Facturation'] ?? '')),
        'vpplanifier' => trim(to_safe_string($row['VPPLANIFIER'] ?? '')),
        'service' => trim(to_safe_string($row['SERVICE'] ?? '')),
        'couverture_heures' => trim(to_safe_string($row['Couverture_Heures'] ?? '')),
        'couverture_jours' => trim(to_safe_string($row['Couverture_Jours'] ?? '')),
        'ville' => trim(to_safe_string($row['Ville'] ?? '')),
        'etat_redouane' => trim(to_safe_string($row['ETAT_REDOUANE'] ?? '')),
        'dateresil' => trim(to_safe_string($row['DATERESIL'] ?? '')),
    ];

    $new = $original;
    $fixTypes = [];

    // 1) SERVICE / Periode / Ville
    if (looks_like_city_value($new['service']) && looks_like_service_value($new['periode_facturation']) && looks_like_period_value($new['ville'])) {
        $tmpService = $new['service'];
        $new['service'] = $new['periode_facturation'];
        $new['periode_facturation'] = $new['ville'];
        $new['ville'] = $tmpService;
        $fixTypes[] = 'rotation_3_champs';
    } elseif (looks_like_city_value($new['service']) && looks_like_service_value($new['ville'])) {
        $tmpService = $new['service'];
        $new['service'] = $new['ville'];
        $new['ville'] = $tmpService;
        $fixTypes[] = 'swap_service_ville';
    } elseif (looks_like_period_value($new['service']) && looks_like_service_value($new['periode_facturation'])) {
        $tmpService = $new['service'];
        $new['service'] = $new['periode_facturation'];
        $new['periode_facturation'] = $tmpService;
        $fixTypes[] = 'swap_service_periode';
    }

    // 2) Code_Site / Nom_Site
    if (!looks_like_site_code($new['code_site']) && looks_like_site_code($new['nom_site'])) {
        $tmp = $new['code_site'];
        $new['code_site'] = $new['nom_site'];
        $new['nom_site'] = $tmp;
        $fixTypes[] = 'swap_code_nom_site';
    }

    // 3) Chaine décalée VPPLANIFIER -> SERVICE -> Couvertures -> Ville -> ETAT_REDOUANE
    $shiftedChain = (
        !looks_like_boolean_value($new['vpplanifier'])
        && (looks_like_service_value($new['vpplanifier']) || looks_like_hour_coverage_value($new['service']))
        && looks_like_hour_coverage_value($new['service'])
        && looks_like_day_coverage_value($new['couverture_heures'])
        && looks_like_city_value($new['couverture_jours'])
        && (looks_like_status_value($new['ville']) || $new['ville'] !== '')
    );

    if ($shiftedChain) {
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
        $fixTypes[] = 'shift_vp_service_couvertures_ville_etat';
    }

    // 4) VPPLANIFIER default
    if ($new['vpplanifier'] === '') {
        $new['vpplanifier'] = 'false';
        $fixTypes[] = 'vpplanifier_default_false';
    }

    // 5) ETAT_REDOUANE serial excel -> DATERESIL si vide
    if ($new['dateresil'] === '' && looks_like_excel_serial_date($original['etat_redouane'])) {
        $date = excel_serial_to_date_ymd($original['etat_redouane']);
        if (!empty($date)) {
            $new['dateresil'] = $date;
            $fixTypes[] = 'dateresil_from_excel_serial';
        }
    }

    $hasChange = false;
    foreach ($new as $key => $value) {
        if ($value !== $original[$key]) {
            $hasChange = true;
            break;
        }
    }

    if (!$hasChange) {
        return null;
    }

    return [
        'fix_type' => implode(' + ', array_unique($fixTypes)),
        'new' => $new,
        'old' => $original
    ];
}

function safe_display($value) {
    $v = trim(to_safe_string($value));
    if ($v === '') {
        return '---';
    }
    return htmlspecialchars($v);
}

$pageTitle = 'Correction Champs Contrat';
$message = '';
$messageType = 'info';

$allRowsStmt = query("SELECT ID_CONTRAT, CODE_CONTRAT, Code_Site, Nom_Site, Periode_Facturation, VPPLANIFIER, SERVICE, Couverture_Heures, Couverture_Jours, Ville, ETAT_REDOUANE, DATERESIL FROM CONTRAT ORDER BY CODE_CONTRAT ASC");
$suggestions = [];

while ($row = sqlsrv_fetch_array($allRowsStmt, SQLSRV_FETCH_ASSOC)) {
    $suggestion = suggest_fixed_fields($row);
    if ($suggestion !== null) {
        $suggestions[] = [
            'id' => $row['ID_CONTRAT'],
            'code' => $row['CODE_CONTRAT'],
            'old' => $suggestion['old'],
            'new' => $suggestion['new'],
            'fix_type' => $suggestion['fix_type']
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply_fix') {
    if (empty($suggestions)) {
        $message = 'Aucune correction à appliquer.';
        $messageType = 'info';
    } else {
        $updated = 0;
        foreach ($suggestions as $item) {
            $stmt = sqlsrv_query(
                $conn,
                "UPDATE CONTRAT
                 SET Code_Site = ?,
                     Nom_Site = ?,
                     Periode_Facturation = ?,
                     VPPLANIFIER = ?,
                     SERVICE = ?,
                     Couverture_Heures = ?,
                     Couverture_Jours = ?,
                     Ville = ?,
                     ETAT_REDOUANE = ?,
                     DATERESIL = CASE WHEN ? = '' THEN DATERESIL ELSE ? END
                 WHERE ID_CONTRAT = ?",
                [
                    $item['new']['code_site'],
                    $item['new']['nom_site'],
                    $item['new']['periode_facturation'],
                    $item['new']['vpplanifier'],
                    $item['new']['service'],
                    $item['new']['couverture_heures'],
                    $item['new']['couverture_jours'],
                    $item['new']['ville'],
                    $item['new']['etat_redouane'],
                    $item['new']['dateresil'],
                    $item['new']['dateresil'],
                    $item['id']
                ]
            );

            if ($stmt) {
                $updated++;
            }
        }

        $message = "Correction terminée. Contrats corrigés: $updated.";
        $messageType = 'success';

        // Recharger suggestions après correction
        $allRowsStmt = query("SELECT ID_CONTRAT, CODE_CONTRAT, Code_Site, Nom_Site, Periode_Facturation, VPPLANIFIER, SERVICE, Couverture_Heures, Couverture_Jours, Ville, ETAT_REDOUANE, DATERESIL FROM CONTRAT ORDER BY CODE_CONTRAT ASC");
        $suggestions = [];
        while ($row = sqlsrv_fetch_array($allRowsStmt, SQLSRV_FETCH_ASSOC)) {
            $suggestion = suggest_fixed_fields($row);
            if ($suggestion !== null) {
                $suggestions[] = [
                    'id' => $row['ID_CONTRAT'],
                    'code' => $row['CODE_CONTRAT'],
                    'old' => $suggestion['old'],
                    'new' => $suggestion['new'],
                    'fix_type' => $suggestion['fix_type']
                ];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <style>
        .fix-wrap { max-width: 1200px; margin: 0 auto; }
        .hint { color: var(--text-muted); font-size: .95rem; }
        .mono { font-family: Consolas, Monaco, monospace; font-size: .88rem; }
        .table-wrap { overflow: auto; border: 1px solid var(--border); border-radius: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; border-bottom: 1px solid var(--border); text-align: left; vertical-align: top; }
        th { background: var(--surface-2); font-weight: 700; }
        .ok { color: var(--success); }
        .warn { color: var(--warning); }
    </style>
</head>
<body>
    <div class="main-content" style="margin-left:0; width:100%;">
        <div class="page-content fix-wrap">
            <header style="margin-bottom:16px;">
                <h1 style="margin:0;"><i class="fa-solid fa-wrench"></i> Correction automatique des champs contrats</h1>
                <p class="hint" style="margin-top:8px;">Cet outil détecte les décalages de colonnes importées (site/code, VPPLANIFIER/SERVICE/couvertures/ville/etat, date résiliation) et applique une correction sécurisée.</p>
            </header>

            <?php if ($message): ?>
                <div class="alert <?= $messageType === 'success' ? 'alert-success' : 'alert-info' ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <div class="card" style="margin-bottom:16px;">
                <p style="margin:0 0 8px 0;"><strong>Total contrats suspects:</strong> <?= count($suggestions) ?></p>
                <p class="hint" style="margin:0;">La correction n'affecte que les lignes détectées comme incohérentes par règles métier.</p>
            </div>

            <?php if (!empty($suggestions)): ?>
                <form method="POST" class="card" style="margin-bottom:16px;">
                    <input type="hidden" name="action" value="apply_fix">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Appliquer la correction sur tous les contrats suspects ?');">
                        <i class="fa-solid fa-bolt"></i> Appliquer les corrections
                    </button>
                </form>

                <div class="table-wrap card">
                    <table>
                        <thead>
                            <tr>
                                <th>Contrat</th>
                                <th>Type correction</th>
                                <th>Code Site</th>
                                <th>Nom Site</th>
                                <th>VPPLANIFIER</th>
                                <th>SERVICE</th>
                                <th>Couverture Heures</th>
                                <th>Couverture Jours</th>
                                <th>Ville</th>
                                <th>ETAT_REDOUANE</th>
                                <th>DATERESIL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($suggestions as $item): ?>
                                <tr>
                                    <td class="mono"><?= htmlspecialchars((string)$item['code']) ?></td>
                                    <td><span class="warn"><?= htmlspecialchars((string)$item['fix_type']) ?></span></td>
                                    <td class="mono"><?= safe_display($item['old']['code_site']) ?><br><span class="ok">→ <?= safe_display($item['new']['code_site']) ?></span></td>
                                    <td class="mono"><?= safe_display($item['old']['nom_site']) ?><br><span class="ok">→ <?= safe_display($item['new']['nom_site']) ?></span></td>
                                    <td class="mono"><?= safe_display($item['old']['vpplanifier']) ?><br><span class="ok">→ <?= safe_display($item['new']['vpplanifier']) ?></span></td>
                                    <td class="mono"><?= safe_display($item['old']['service']) ?><br><span class="ok">→ <?= safe_display($item['new']['service']) ?></span></td>
                                    <td class="mono"><?= safe_display($item['old']['couverture_heures']) ?><br><span class="ok">→ <?= safe_display($item['new']['couverture_heures']) ?></span></td>
                                    <td class="mono"><?= safe_display($item['old']['couverture_jours']) ?><br><span class="ok">→ <?= safe_display($item['new']['couverture_jours']) ?></span></td>
                                    <td class="mono"><?= safe_display($item['old']['ville']) ?><br><span class="ok">→ <?= safe_display($item['new']['ville']) ?></span></td>
                                    <td class="mono"><?= safe_display($item['old']['etat_redouane']) ?><br><span class="ok">→ <?= safe_display($item['new']['etat_redouane']) ?></span></td>
                                    <td class="mono"><?= safe_display($item['old']['dateresil']) ?><br><span class="ok">→ <?= safe_display($item['new']['dateresil']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-success">Aucune confusion détectée sur les champs contrats surveillés.</div>
            <?php endif; ?>

            <div style="margin-top:16px;">
                <a href="dashboard.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Retour Admin</a>
            </div>
        </div>
    </div>
</body>
</html>
