<?php

class CsvImporter {
    private $conn;
    private $delimiter = ';';

    public function __construct($conn) {
        $this->conn = $conn;
    }

    private function expectedHeadersFor($type) {
        switch ($type) {
            case 'clients':
                return [
                    'id_client', 'nom', 'adresse', 'ville', 'contact', 'tel', 'tel2', 'tel3',
                    'fax', 'email', 'site', 'blocage', 'activite', 'secteur_activite_sec',
                    'code_secteur_activite_princ', 'code_secteur_activite_sec', 'modalite_paiement', 'sysgm_client_bit'
                ];
            case 'contrats':
                return [
                    'code_contrat', 'code_client', 'nom_client', 'date_creation', 'date_debut', 'periode', 'date_fin', 'date_signature',
                    'avenant', 'contrat_originale', 'etat', 'type', 'vp', 'code_site', 'nom_site', 'montant_contrat', 'mode_facturation',
                    'periode_facturation', 'echeance_facturation', 'vpplanifier', 'service', 'couverture_heures', 'couverture_jours',
                    'ville', 'etat_redouane', 'dateresil'
                ];
            case 'sites':
                return [
                    'id_site', 'id_client', 'ville', 'nom_client', 'nom', 'adresse', 'tel', 'fax', 'siteweb', 'email',
                    'comment', 'modem', 'blocage', 'datebl', 'datedbl', 'tel2', 'tel3', 'remote_login1', 'mdp1',
                    'remote_login2', 'mdp2', 'zone_geo', 'tel_siege', 'code_agence', 'latitude', 'longitude', 'contact_nom'
                ];
            case 'commandes':
                return ['numero_commande', 'code_client', 'montant_ht', 'date_commande'];
            case 'tickets':
                return ['sujet', 'description', 'code_client', 'priorite'];
            default:
                return [];
        }
    }

    private function formatExpectedContractHeaders() {
        return [
            'CODE_CONTRAT', 'Code_Client', 'Nom_Client', 'Date_Creation', 'Date_Debut', 'PERIODE', 'Date_Fin', 'Date_Signature',
            'AVENANT', 'Contrat_Originale', 'ETAT', 'TYPE', 'VP', 'Code_Site', 'Nom_Site', 'Montant_Contrat', 'Mode_Facturation',
            'Periode_Facturation', 'Echeance_Facturation', 'VPPLANIFIER', 'SERVICE', 'Couverture_Heures', 'Couverture_Jours',
            'Ville', 'ETAT_REDOUANE', 'DATERESIL'
        ];
    }

    private function normalizeHeaderKey($key) {
        $key = trim((string)$key);
        $key = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $key); // BOM / bytes bizarres
        // mbstring n'est pas toujours activé sur IIS : fallback sûr
        if (function_exists('mb_strtolower')) {
            $key = mb_strtolower($key, 'UTF-8');
        } else {
            $key = strtolower($key);
        }

        // Convertir accents -> ascii quand possible
        if (function_exists('iconv')) {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $key);
            if ($ascii !== false) $key = $ascii;
        }

        // Unifier: espaces/ponctuation -> underscore
        $key = preg_replace('/[^a-z0-9]+/', '_', $key);
        $key = trim($key, '_');
        return $key;
    }

    private function normalizeBusinessCode($value) {
        $text = trim((string)$value);
        if ($text === '') {
            return '';
        }

        // Excel can export numeric IDs as 3421012520.0 or 3.42101252E+9.
        if (is_numeric($text) && (strpos($text, '.') !== false || stripos($text, 'e') !== false)) {
            $text = sprintf('%.0f', (float)$text);
        }

        return trim($text);
    }

    private function normalizeTextBasic($value) {
        $text = trim((string)$value);
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

    private function containsAnyKeyword($value, $keywords) {
        $text = $this->normalizeTextBasic($value);
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

    private function looksLikePeriodValue($value) {
        $text = $this->normalizeTextBasic($value);
        if ($text === '') {
            return false;
        }

        if (preg_match('/\b\d+\s*(jour|jours|semaine|semaines|mois|an|ans)\b/', $text)) {
            return true;
        }

        $periodKeywords = [
            'mensuel', 'mensuelle', 'trimestriel', 'trimestrielle', 'semestriel', 'semestrielle',
            'annuel', 'annuelle', 'hebdo', 'hebdomadaire', 'fin de mois', 'fin mois', 'bimensuel'
        ];

        return $this->containsAnyKeyword($text, $periodKeywords);
    }

    private function looksLikeServiceValue($value) {
        $serviceKeywords = [
            'support', 'maintenance', 'assistance', 'infogerance', 'helpdesk', 'monitoring', 'supervision',
            'reseau', 'securite', 'fortinet', 'vpn', 'soc', 'sav', 'n1', 'n2', 'n3', 'it',
            'replace', 'remplacement', 'spare', 'piece'
        ];
        return $this->containsAnyKeyword($value, $serviceKeywords);
    }

    private function looksLikeExcelSerialDate($value) {
        $text = trim((string)$value);
        if ($text === '' || !is_numeric($text)) {
            return false;
        }

        $num = (float)$text;
        return $num > 30000 && $num < 100000;
    }

    private function excelSerialToDateYmd($value) {
        if (!$this->looksLikeExcelSerialDate($value)) {
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

    private function looksLikeCityValue($value) {
        $text = trim((string)$value);
        if ($text === '') {
            return false;
        }

        $norm = $this->normalizeTextBasic($text);
        if ($this->looksLikeServiceValue($norm) || $this->looksLikePeriodValue($norm)) {
            return false;
        }

        if (strlen($text) > 60) {
            return false;
        }

        return preg_match('/^[\p{L}0-9\s\-\'\.,]{2,60}$/u', $text) === 1;
    }

    private function looksLikeSiteCode($value) {
        $text = trim((string)$value);
        if ($text === '') {
            return false;
        }

        // Exemples: SIT-001, SITE001, C001, AB-12
        return preg_match('/^[A-Za-z]{2,}[A-Za-z0-9\-_]*\d+[A-Za-z0-9\-_]*$/', $text) === 1;
    }

    private function looksLikeHourCoverageValue($value) {
        $text = $this->normalizeTextBasic($value);
        if ($text === '') {
            return false;
        }

        if (preg_match('/\b\d{1,2}\s*h\b/', $text)) {
            return true;
        }

        return preg_match('/\b\d{1,2}\s*[:h]\s*\d{0,2}\s*[-\/a]\s*\d{1,2}\s*[:h]?\s*\d{0,2}\b/', $text) === 1;
    }

    private function looksLikeDayCoverageValue($value) {
        $text = $this->normalizeTextBasic($value);
        if ($text === '') {
            return false;
        }

        if (preg_match('/\b[1-7]\s*\/\s*7\b/', $text)) {
            return true;
        }

        $dayKeywords = [
            'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche',
            'semaine', 'weekend', 'week-end'
        ];

        return $this->containsAnyKeyword($text, $dayKeywords);
    }

    private function looksLikeStatusValue($value) {
        $text = $this->normalizeTextBasic($value);
        if ($text === '') {
            return false;
        }

        $statusKeywords = [
            'expire', 'expir', 'valide', 'invalide', 'actif', 'inactif', 'oui', 'non', 'true', 'false', 'ok', 'ko'
        ];

        return $this->containsAnyKeyword($text, $statusKeywords);
    }

    private function looksLikeBooleanValue($value) {
        $text = $this->normalizeTextBasic($value);
        if ($text === '') {
            return false;
        }

        return in_array($text, ['0', '1', 'true', 'false', 'oui', 'non', 'yes', 'no', 'o', 'n'], true);
    }

    private function normalizeContratOperationalFields($codeSite, $nomSite, $vpplanifier, $service, $couvertureHeures, $couvertureJours, $ville, $etatRedouane) {
        $codeSite = trim((string)$codeSite);
        $nomSite = trim((string)$nomSite);
        $vpplanifier = trim((string)$vpplanifier);
        $service = trim((string)$service);
        $couvertureHeures = trim((string)$couvertureHeures);
        $couvertureJours = trim((string)$couvertureJours);
        $ville = trim((string)$ville);
        $etatRedouane = trim((string)$etatRedouane);

        // Cas: Code_Site <-> Nom_Site inversés
        if (!$this->looksLikeSiteCode($codeSite) && $this->looksLikeSiteCode($nomSite)) {
            $tmp = $codeSite;
            $codeSite = $nomSite;
            $nomSite = $tmp;
        }

        // Cas observé: VPPLANIFIER absent, puis décalage à gauche de toute la chaîne suivante.
        // vpplanifier <- service, service <- couverture_heures, couverture_heures <- couverture_jours,
        // couverture_jours <- ville, ville <- etat_redouane.
        $isShiftedChain = (
            !$this->looksLikeBooleanValue($vpplanifier)
            && ($this->looksLikeServiceValue($vpplanifier) || $this->looksLikeHourCoverageValue($service))
            && $this->looksLikeHourCoverageValue($service)
            && $this->looksLikeDayCoverageValue($couvertureHeures)
            && $this->looksLikeCityValue($couvertureJours)
            && ($this->looksLikeStatusValue($ville) || $ville !== '')
        );

        if ($isShiftedChain) {
            $oldVpplanifier = $vpplanifier;
            $oldService = $service;
            $oldCouvertureHeures = $couvertureHeures;
            $oldCouvertureJours = $couvertureJours;
            $oldVille = $ville;

            $vpplanifier = 'false';
            $service = $oldVpplanifier;
            $couvertureHeures = $oldService;
            $couvertureJours = $oldCouvertureHeures;
            $ville = $oldCouvertureJours;
            $etatRedouane = $oldVille;
        }

        if ($vpplanifier === '') {
            $vpplanifier = 'false';
        }

        return [
            'code_site' => $codeSite,
            'nom_site' => $nomSite,
            'vpplanifier' => $vpplanifier,
            'service' => $service,
            'couverture_heures' => $couvertureHeures,
            'couverture_jours' => $couvertureJours,
            'ville' => $ville,
            'etat_redouane' => $etatRedouane
        ];
    }

    private function normalizeContratFieldOrder($service, $periodeFacturation, $ville) {
        $s = trim((string)$service);
        $p = trim((string)$periodeFacturation);
        $v = trim((string)$ville);

        // Cas observé: SERVICE <- Lieu, Periode_Facturation <- Service, Ville <- Periode
        if ($this->looksLikeCityValue($s) && $this->looksLikeServiceValue($p) && $this->looksLikePeriodValue($v)) {
            return [
                'service' => $p,
                'periode_facturation' => $v,
                'ville' => $s
            ];
        }

        // Cas simple: SERVICE <-> Ville inversés
        if ($this->looksLikeCityValue($s) && $this->looksLikeServiceValue($v)) {
            return [
                'service' => $v,
                'periode_facturation' => $p,
                'ville' => $s
            ];
        }

        // Cas simple: SERVICE <-> Periode_Facturation inversés
        if ($this->looksLikePeriodValue($s) && $this->looksLikeServiceValue($p)) {
            return [
                'service' => $p,
                'periode_facturation' => $s,
                'ville' => $v
            ];
        }

        return [
            'service' => $s,
            'periode_facturation' => $p,
            'ville' => $v
        ];
    }

    public function import($filePath, $type) {
        if (!file_exists($filePath)) {
            return ['status' => 'error', 'message' => 'Fichier introuvable.'];
        }

        // Detect Extension
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        // --- XLSX HANDLING ---
        if ($ext === 'xlsx' || (isset($_FILES['csv_file']) && $_FILES['csv_file']['type'] == 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')) {
            require_once __DIR__ . '/../libs/SimpleXLSX.php';
            if ($xlsx = SimpleXLSX::parse($filePath)) {
                $rows = $xlsx->rows();
                if (empty($rows)) {
                    return ['status' => 'error', 'message' => 'Fichier XLSX vide.'];
                }
                
                // First row is header
                $maybeHeaderRow = array_shift($rows);
                $header = array_map([$this, 'normalizeHeaderKey'], $maybeHeaderRow);
                $expected = $this->expectedHeadersFor($type);

                // Pour CONTRATS: forcer le format du modèle (comme modele_contrats.csv)
                if ($type === 'contrats' && !empty($expected)) {
                    if ($header !== $expected) {
                        $missing = array_values(array_diff($expected, $header));
                        $orderedHeader = implode(', ', $this->formatExpectedContractHeaders());
                        $message = "En-tête invalide pour l'import des contrats. L'ordre des colonnes doit être strictement respecté.";
                        if (!empty($missing)) {
                            $message .= " Colonnes manquantes: " . implode(', ', $missing) . ".";
                        }
                        $message .= " Ordre attendu: " . $orderedHeader . ".";
                        return [
                            'status' => 'error',
                            'message' => $message
                        ];
                    }
                } else {
                    // Autres imports: si l'en-tête n'est pas présent (ou pas le bon), on utilise le modèle attendu
                    if (!empty($expected) && !in_array($expected[0], $header, true)) {
                        // La première ligne lue est en fait une ligne de données
                        array_unshift($rows, $maybeHeaderRow);
                        $header = $expected;
                    }
                }
                
                $successCount = 0;
                $errorCount = 0;
                $errors = [];
                $rowNum = 1; 
                
                foreach ($rows as $row) {
                    $rowNum++;
                    // Tolérer les colonnes manquantes/en trop
                    if (count($row) < count($header)) $row = array_pad($row, count($header), '');
                    if (count($row) > count($header)) $row = array_slice($row, 0, count($header));
                    
                    $data = array_combine($header, $row);
                    if ($data === false) {
                        $errors[] = "Ligne $rowNum: Erreur de mappage.";
                        $errorCount++;
                        continue;
                    }
                    
                    $res = $this->processRow($type, $data);
                    if ($res === true) {
                        $successCount++;
                    } else {
                        $errorCount++;
                        $errors[] = "Ligne $rowNum: " . $res;
                    }
                }
                
                return [
                    'status' => 'success',
                    'imported' => $successCount,
                    'failed' => $errorCount,
                    'errors' => $errors
                ];
            } else {
                return ['status' => 'error', 'message' => 'Erreur lecture XLSX.'];
            }
        }

        // --- CSV HANDLING ---
        // Attempt to detect delimiter if not semicolon
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return ['status' => 'error', 'message' => 'Impossible d\'ouvrir le fichier.'];
        }

        // Read first line to check delimiter
        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = ';';
        if (substr_count($firstLine, ',') > substr_count($firstLine, ';')) {
            $delimiter = ',';
        }

        // Read Header (ou 1ère ligne de données si fichier sans en-tête)
        $maybeHeaderRow = fgetcsv($handle, 1000, $delimiter);
        if (!$maybeHeaderRow) {
            fclose($handle);
            return ['status' => 'error', 'message' => 'Fichier vide ou format incorrect.'];
        }

        // Normalize headers (BOM, accents, espaces, etc.)
        $header = array_map([$this, 'normalizeHeaderKey'], $maybeHeaderRow);
        $expected = $this->expectedHeadersFor($type);
        $pendingFirstDataRow = null;

        // Pour CONTRATS: forcer le format du modèle (comme modele_contrats.csv)
        if ($type === 'contrats' && !empty($expected)) {
            if ($header !== $expected) {
                $missing = array_values(array_diff($expected, $header));
                $orderedHeader = implode(', ', $this->formatExpectedContractHeaders());
                $message = "En-tête invalide pour l'import des contrats. L'ordre des colonnes doit être strictement respecté.";
                if (!empty($missing)) {
                    $message .= " Colonnes manquantes: " . implode(', ', $missing) . ".";
                }
                $message .= " Ordre attendu: " . $orderedHeader . ".";
                fclose($handle);
                return [
                    'status' => 'error',
                    'message' => $message
                ];
            }
        } else {
            // Autres types: si l'en-tête n'est pas présent (ou pas le bon), on utilise le modèle attendu
            if (!empty($expected) && !in_array($expected[0], $header, true)) {
                $pendingFirstDataRow = $maybeHeaderRow; // traiter cette ligne comme données
                $header = $expected;
            }
        }

        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        $rowNum = 1;

        // Si la 1ère ligne était une ligne de données, on la traite d'abord
        if (is_array($pendingFirstDataRow)) {
            $rowNum++;
            $row = $pendingFirstDataRow;
            if (count($row) < count($header)) $row = array_pad($row, count($header), '');
            if (count($row) > count($header)) $row = array_slice($row, 0, count($header));
            set_time_limit(0);
            $data = array_combine($header, $row);
            if ($data === false) {
                $errors[] = "Ligne $rowNum: Erreur de formatage.";
                $errorCount++;
            } else {
                $res = $this->processRow($type, $data);
                if ($res === true) $successCount++; else { $errorCount++; $errors[] = "Ligne $rowNum: " . $res; }
            }
        }

        while (($row = fgetcsv($handle, 2000, $delimiter)) !== FALSE) {
            $rowNum++;
            // Skip empty rows
            if (empty($row) || (count($row) === 1 && empty($row[0]))) continue;

            // Tolérer les colonnes manquantes/en trop (Excel met parfois des ; en fin de ligne)
            if (count($row) < count($header)) $row = array_pad($row, count($header), '');
            if (count($row) > count($header)) $row = array_slice($row, 0, count($header));
            // Reset le temps d'exécution PHP pour chaque ligne (indispensable pour les gros fichiers)
            set_time_limit(0);

            $data = array_combine($header, $row);
            if ($data === false) {
                 $errors[] = "Ligne $rowNum: Erreur de formatage.";
                 $errorCount++;
                 continue;
            }

            $res = $this->processRow($type, $data);
            if ($res === true) {
                $successCount++;
            } else {
                $errorCount++;
                $errors[] = "Ligne $rowNum: " . $res;
            }
        }

        fclose($handle);

        return [
            'status' => 'success',
            'imported' => $successCount,
            'failed' => $errorCount,
            'errors' => $errors
        ];
    }
    private function processRow($type, $data) {
        switch ($type) {
            case 'clients':
                return $this->importClient($data);
            case 'contrats':
                return $this->importContrat($data);
            case 'sites':
                return $this->importSite($data);
            case 'tickets':
                return $this->importTicket($data);
            case 'commandes':
                return $this->importCommande($data);
            default:
                return "Type d'import inconnu : $type";
        }
    }

    private function importClient($data) {
        // Expected keys (lowercase from the exact user list): 
        // id_client, nom, adresse, ville, contact, tel, tel2, tel3, fax, email, site, blocage, 
        // activite, secteur_activite_sec, code_secteur_activite_princ, code_secteur_activite_sec, modalite_paiement, sysgm_client_bit
        
        $code = $this->normalizeBusinessCode($data['id_client'] ?? '');
        $nom = $data['nom'] ?? '';
        $adresse = $data['adresse'] ?? '';
        $ville = $data['ville'] ?? '';
        $contact = $data['contact'] ?? '';
        $tel = $data['tel'] ?? '';
        $tel2 = $data['tel2'] ?? '';
        $tel3 = $data['tel3'] ?? '';
        $fax = $data['fax'] ?? '';
        $email = $data['email'] ?? '';
        $site = $data['site'] ?? '';
        $blocage = $data['blocage'] ?? '';
        $activite = $data['activite'] ?? '';
        $sect_sec = $data['secteur_activite_sec'] ?? '';
        $code_sect_princ = $data['code_secteur_activite_princ'] ?? '';
        $code_sect_sec = $data['code_secteur_activite_sec'] ?? '';
        $mod_paiement = $data['modalite_paiement'] ?? '';
        $sysgm_bit = !empty($data['sysgm_client_bit']) ? 1 : 0;

        if (empty($nom)) return "Nom du client manquant";

        // Generate Code if missing (although user provided it as first column)
        if (empty($code)) {
            $code = strtoupper(substr($nom, 0, 3) . rand(100,999));
        }

        // Check availability by Code Client
        $check = sqlsrv_query($this->conn, "SELECT ID_Client as id FROM SAV_Clients WHERE ID_Client = ?", [$code]);
        if ($check === false) {
            error_log('[CSV_IMPORT_CLIENT_CHECK_CODE] ' . db_last_error_message());
            return "Erreur SQL check code.";
        }

        if (sqlsrv_has_rows($check)) {
            // Update existing client
            $sql = "UPDATE SAV_Clients SET 
                        Nom=COALESCE(NULLIF(?, ''), Nom),
                        Adresse=COALESCE(NULLIF(?, ''), Adresse),
                        Ville=COALESCE(NULLIF(?, ''), Ville),
                        Contact=COALESCE(NULLIF(?, ''), Contact),
                        TEL=COALESCE(NULLIF(?, ''), TEL),
                        TEL2=COALESCE(NULLIF(?, ''), TEL2),
                        TEL3=COALESCE(NULLIF(?, ''), TEL3),
                        Fax=COALESCE(NULLIF(?, ''), Fax),
                        Email=COALESCE(NULLIF(?, ''), Email),
                        Site=COALESCE(NULLIF(?, ''), Site),
                        Blocage=COALESCE(NULLIF(?, ''), Blocage),
                        Activite=COALESCE(NULLIF(?, ''), Activite),
                        Secteur_Activite_Sec=COALESCE(NULLIF(?, ''), Secteur_Activite_Sec),
                        Code_Secteur_Activite_Princ=COALESCE(NULLIF(?, ''), Code_Secteur_Activite_Princ),
                        Code_Secteur_Activite_Sec=COALESCE(NULLIF(?, ''), Code_Secteur_Activite_Sec),
                        Modalite_Paiement=COALESCE(NULLIF(?, ''), Modalite_Paiement),
                        SysGM_Client_Bit=COALESCE(?, SysGM_Client_Bit)
                    WHERE ID_Client=?";
            $params = [
                $nom, $adresse, $ville, $contact, $tel, $tel2, $tel3, $fax, $email, $site,
                $blocage, $activite, $sect_sec, $code_sect_princ, $code_sect_sec,
                $mod_paiement, $sysgm_bit,
                $code
            ];
            if (sqlsrv_query($this->conn, $sql, $params)) {
                return true; 
            }
            error_log('[CSV_IMPORT_CLIENT_UPDATE] ' . db_last_error_message());
            return "Erreur Update.";
        }

        // Check by Name if Code was newly generated
        $checkName = sqlsrv_query($this->conn, "SELECT ID_Client as id FROM SAV_Clients WHERE Nom = ?", [$nom]);
        if ($checkName && sqlsrv_has_rows($checkName)) {
             return "Client '$nom' existe déjà (Nom duplicate).";
        }

        $sql = "INSERT INTO SAV_Clients (
                    ID_Client, Nom, Adresse, Ville, Contact, TEL, TEL2, TEL3, Fax, Email, Site, 
                    Blocage, Activite, Secteur_Activite_Sec, Code_Secteur_Activite_Princ, Code_Secteur_Activite_Sec, 
                    Modalite_Paiement, SysGM_Client_Bit
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [
            $code, $nom, $adresse, $ville, $contact, $tel, $tel2, $tel3, $fax, $email, $site,
            $blocage, $activite, $sect_sec, $code_sect_princ, $code_sect_sec,
            $mod_paiement, $sysgm_bit
        ];

        if (sqlsrv_query($this->conn, $sql, $params)) {
             return true;
        }
           error_log('[CSV_IMPORT_CLIENT_INSERT] ' . db_last_error_message());
           return "Erreur Insert.";
    }

    private function importContrat($data) {
        // Expected keys (exact user list): 
        // code_contrat, code_client, nom_client, date_creation, date_debut, periode, date_fin, date_signature, 
        // avenant, contrat_originale, etat, type, vp, code_site, nom_site, montant_contrat, mode_facturation, 
        // periode_facturation, echeance_facturation, vpplanifier, service, couverture_heures, couverture_jours, 
        // ville, etat_redouane, dateresil

        $pick = function(array $keys) use ($data) {
            foreach ($keys as $k) {
                if (array_key_exists($k, $data)) {
                    $v = trim((string)$data[$k]);
                    if ($v !== '') return $v;
                }
            }
            return '';
        };

        // Tolérer plusieurs noms de colonnes (exports différents)
        $code_contrat = $pick(['code_contrat', 'codecontrat', 'numero_contrat', 'reference', 'ref', 'code']);
        
        // Fonction locale pour parser les dates Excel / CSV (robuste)
        $parseDate = function($val) {
            if (empty($val) || trim($val) === '' || trim($val) === '0') return null;
            $val = trim($val);

            // 1. Nombre pur = date série Excel (ex: 45292 = 2024-01-01)
            if (is_numeric($val) && (int)$val > 30000 && (int)$val < 100000) {
                $unix = ((int)$val - 25569) * 86400; // Excel epoch -> Unix
                $result = date('Y-m-d', $unix);
                $parts = explode('-', $result);
                if (count($parts) === 3 && checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) {
                    return $result;
                }
                return null;
            }

            // 2. Format yyyy-mm-dd (ou yyyy/mm/dd) éventuellement avec heure
            if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/', $val, $m)) {
                $y = (int)$m[1]; $mo = (int)$m[2]; $d = (int)$m[3];
                if (checkdate($mo, $d, $y)) {
                    return sprintf('%04d-%02d-%02d', $y, $mo, $d);
                }
                return null;
            }

            // 3. Format dd/mm/yyyy ou dd-mm-yyyy (éventuellement avec heure)
            if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/', $val, $m)) {
                $d = (int)$m[1]; $mo = (int)$m[2]; $y = (int)$m[3];
                // Vérifier dd/mm/yyyy d'abord
                if (checkdate($mo, $d, $y)) {
                    return sprintf('%04d-%02d-%02d', $y, $mo, $d);
                }
                // Tenter mm/dd/yyyy si dd/mm échoue
                if (checkdate($d, $mo, $y)) {
                    return sprintf('%04d-%02d-%02d', $y, $d, $mo);
                }
                return null;
            }

            // 4. Fallback strtotime
            $time = @strtotime(str_replace('/', '-', $val));
            if ($time && $time > 0) {
                $result = date('Y-m-d', $time);
                $parts = explode('-', $result);
                if (count($parts) === 3 && checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) {
                    return $result;
                }
            }
            return null;
        };

        // Fonction locale pour tronquer les chaines
        $truncate = function($val, $length) {
            return !empty($val) ? substr(trim($val), 0, $length) : $val;
        };

        // Collect all standard fields with Safe Truncation matching SQL constraints
        $code_client_raw = $this->normalizeBusinessCode($pick(['code_client', 'id_client', 'client_id', 'codeclient']));
        $nom_client_raw = $pick(['nom_client', 'client_nom', 'nom', 'client']);
        $code_client = $truncate($code_client_raw, 99);
        $nom_client = $truncate($nom_client_raw, 149);
        $date_creation = $parseDate($data['date_creation'] ?? '');
        $date_debut = $parseDate($data['date_debut'] ?? '');
        $periode = $truncate($data['periode'] ?? '', 49);
        $date_fin = $parseDate($data['date_fin'] ?? '');
        $date_signature = $parseDate($data['date_signature'] ?? '');
        $avenant = $truncate($data['avenant'] ?? '', 49);
        $contrat_originale = $truncate($data['contrat_originale'] ?? '', 99);
        $etat = $truncate($data['etat'] ?? 'ACTIF', 49);
        $type = $truncate($data['type'] ?? '', 49);
        $vp = $truncate($data['vp'] ?? '', 49);
        $code_site = $truncate($data['code_site'] ?? '', 99);
        $nom_site = $truncate($data['nom_site'] ?? '', 149);
        $montant_contrat = !empty($data['montant_contrat']) ? floatval(str_replace(',', '.', $data['montant_contrat'])) : null;
        $mode_facturation = $truncate($data['mode_facturation'] ?? '', 49);
        $periode_facturation = $truncate($data['periode_facturation'] ?? ($data['periode_facture'] ?? ''), 49);
        $echeance_facturation = $truncate($data['echeance_facturation'] ?? '', 49);
        $vpplanifier = $truncate($data['vpplanifier'] ?? '', 49);
        $service = $truncate($data['service'] ?? ($data['service_principal'] ?? ''), 99);
        $couverture_heures = $truncate($data['couverture_heures'] ?? '', 99);
        $couverture_jours = $truncate($data['couverture_jours'] ?? '', 99);
        $ville = $truncate($data['ville'] ?? ($data['lieu'] ?? ''), 99);
        $etat_redouane = $truncate($data['etat_redouane'] ?? '', 49);
        $raw_etat_redouane_input = $etat_redouane;
        $dateresil = $parseDate($data['dateresil'] ?? '');

        $normalizedFields = $this->normalizeContratFieldOrder($service, $periode_facturation, $ville);
        $service = $truncate($normalizedFields['service'], 99);
        $periode_facturation = $truncate($normalizedFields['periode_facturation'], 49);
        $ville = $truncate($normalizedFields['ville'], 99);

        $normalizedOperationalFields = $this->normalizeContratOperationalFields(
            $code_site,
            $nom_site,
            $vpplanifier,
            $service,
            $couverture_heures,
            $couverture_jours,
            $ville,
            $etat_redouane
        );
        $code_site = $truncate($normalizedOperationalFields['code_site'], 99);
        $nom_site = $truncate($normalizedOperationalFields['nom_site'], 149);
        $vpplanifier = $truncate($normalizedOperationalFields['vpplanifier'], 49);
        $service = $truncate($normalizedOperationalFields['service'], 99);
        $couverture_heures = $truncate($normalizedOperationalFields['couverture_heures'], 99);
        $couverture_jours = $truncate($normalizedOperationalFields['couverture_jours'], 99);
        $ville = $truncate($normalizedOperationalFields['ville'], 99);
        $etat_redouane = $truncate($normalizedOperationalFields['etat_redouane'], 49);

        if ($dateresil === null && $this->looksLikeExcelSerialDate($raw_etat_redouane_input)) {
            $serialDate = $this->excelSerialToDateYmd($raw_etat_redouane_input);
            if (!empty($serialDate)) {
                $dateresil = $serialDate;
            }
        }

        // En import CONTRATS, on veut respecter strictement le modèle:
        if (empty($code_contrat)) return "code_contrat manquant (colonne 'code_contrat')";
        if (empty($code_client)) return "code_client manquant (colonne 'code_client')";

        // Verify if client exists (optional link, but good for ID_CLIENT relation if needed later)
        $id_client_internal = null;
        if (!empty($code_client)) {
            $stmtClient = sqlsrv_query($this->conn, "SELECT ID_Client as id FROM SAV_Clients WHERE ID_Client = ?", [$code_client]);
            if ($stmtClient && $row = sqlsrv_fetch_array($stmtClient)) {
                $id_client_internal = $row['id'];
            } else {
                // Créer le client minimal si absent (certaines BDD n'ont pas les colonnes "Statut_Import")
                $sqlCreateClient = "INSERT INTO SAV_Clients (ID_Client, Nom) VALUES (?, ?)";
                $ok = sqlsrv_query($this->conn, $sqlCreateClient, [$code_client, !empty($nom_client) ? $nom_client : $code_client]);
                if ($ok === false) {
                    error_log('[CSV_IMPORT_CONTRAT_CREATE_CLIENT_' . $code_client . '] ' . db_last_error_message());
                    return "Impossible de creer le client '$code_client'.";
                }
                $id_client_internal = $code_client;
            }
        }
        
        // Sécurité en cas de code_client vide (très rare selon l'export exact)
        if (empty($id_client_internal)) {
            $id_client_internal = 'INCONNU';
            // Créer le client INCONNU si non existant
            $stmtClientInc = sqlsrv_query($this->conn, "SELECT ID_Client as id FROM SAV_Clients WHERE ID_Client = 'INCONNU'");
            if (!$stmtClientInc || !sqlsrv_has_rows($stmtClientInc)) {
                $ok = sqlsrv_query($this->conn, "INSERT INTO SAV_Clients (ID_Client, Nom) VALUES ('INCONNU', 'Client Inconnu')");
                if ($ok === false) {
                    error_log('[CSV_IMPORT_CONTRAT_CREATE_UNKNOWN_CLIENT] ' . db_last_error_message());
                    return "Impossible de creer le client 'INCONNU'.";
                }
            }
        }

        // Check if contract exists by CODE_CONTRAT
        $check = sqlsrv_query($this->conn, "SELECT ID_CONTRAT FROM CONTRAT WHERE CODE_CONTRAT = ?", [$code_contrat]);
        if ($check === false) {
            error_log('[CSV_IMPORT_CONTRAT_CHECK] ' . db_last_error_message());
            return "Erreur SQL check contrat.";
        }

        if (sqlsrv_has_rows($check)) {
            // Update
            $sql = "UPDATE CONTRAT SET 
                        Code_Client = COALESCE(NULLIF(?, ''), Code_Client),
                        Nom_Client = COALESCE(NULLIF(?, ''), Nom_Client),
                        Date_Creation = COALESCE(?, Date_Creation),
                        Date_Debut = COALESCE(?, Date_Debut),
                        PERIODE = COALESCE(NULLIF(?, ''), PERIODE),
                        Date_Fin = COALESCE(?, Date_Fin),
                        Date_Signature = COALESCE(?, Date_Signature),
                        AVENANT = COALESCE(NULLIF(?, ''), AVENANT),
                        Contrat_Originale = COALESCE(NULLIF(?, ''), Contrat_Originale),
                        ETAT = COALESCE(NULLIF(?, ''), ETAT),
                        TYPE = COALESCE(NULLIF(?, ''), TYPE),
                        VP = COALESCE(NULLIF(?, ''), VP),
                        Code_Site = COALESCE(NULLIF(?, ''), Code_Site),
                        Nom_Site = COALESCE(NULLIF(?, ''), Nom_Site),
                        Montant_Contrat = COALESCE(?, Montant_Contrat),
                        Mode_Facturation = COALESCE(NULLIF(?, ''), Mode_Facturation),
                        Periode_Facturation = COALESCE(NULLIF(?, ''), Periode_Facturation),
                        Echeance_Facturation = COALESCE(NULLIF(?, ''), Echeance_Facturation),
                        VPPLANIFIER = COALESCE(NULLIF(?, ''), VPPLANIFIER),
                        SERVICE = COALESCE(NULLIF(?, ''), SERVICE),
                        Couverture_Heures = COALESCE(NULLIF(?, ''), Couverture_Heures),
                        Couverture_Jours = COALESCE(NULLIF(?, ''), Couverture_Jours),
                        Ville = COALESCE(NULLIF(?, ''), Ville),
                        ETAT_REDOUANE = COALESCE(NULLIF(?, ''), ETAT_REDOUANE),
                        DATERESIL = COALESCE(?, DATERESIL),
                        ID_CLIENT = COALESCE(NULLIF(?, ''), ID_CLIENT)
                    WHERE CODE_CONTRAT=?";
            
            $params = [
                $code_client, $nom_client, $date_creation, $date_debut, $periode, $date_fin,
                $date_signature, $avenant, $contrat_originale, $etat, $type, $vp,
                $code_site, $nom_site, $montant_contrat, $mode_facturation, $periode_facturation,
                $echeance_facturation, $vpplanifier, $service, $couverture_heures, $couverture_jours,
                $ville, $etat_redouane, $dateresil, $id_client_internal, $code_contrat
            ];

            if (sqlsrv_query($this->conn, $sql, $params)) {
                return true; 
            }
            error_log('[CSV_IMPORT_CONTRAT_UPDATE] ' . db_last_error_message());
            return "Erreur Update Contrat.";
        }

        // Insert
        // Need to guarantee an internal primary key ID_CONTRAT is supplied if it's not Auto-Increment
        // Usually it's an INT IDENTITY, but earlier code used uniqid('CTR-'). We'll keep uniqid for safety if needed, 
        // or just insert the new columns. Let's provide ID_CONTRAT as uniqid like before to be safe.
        $new_id = uniqid('CTR-');
        
        $sql = "INSERT INTO CONTRAT (
                    ID_CONTRAT, CODE_CONTRAT, Code_Client, Nom_Client, Date_Creation, Date_Debut, PERIODE, Date_Fin, 
                    Date_Signature, AVENANT, Contrat_Originale, ETAT, TYPE, VP, 
                    Code_Site, Nom_Site, Montant_Contrat, Mode_Facturation, Periode_Facturation, 
                    Echeance_Facturation, VPPLANIFIER, SERVICE, Couverture_Heures, Couverture_Jours, 
                    Ville, ETAT_REDOUANE, DATERESIL, ID_CLIENT
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )";
                
        $params = [
            $new_id, $code_contrat, $code_client, $nom_client, $date_creation, $date_debut, $periode, $date_fin,
            $date_signature, $avenant, $contrat_originale, $etat, $type, $vp,
            $code_site, $nom_site, $montant_contrat, $mode_facturation, $periode_facturation,
            $echeance_facturation, $vpplanifier, $service, $couverture_heures, $couverture_jours,
            $ville, $etat_redouane, $dateresil, $id_client_internal
        ];

        if (sqlsrv_query($this->conn, $sql, $params)) {
             return true;
        }
           error_log('[CSV_IMPORT_CONTRAT_INSERT] ' . db_last_error_message());
           return "Erreur Insert Contrat.";
    }

    private function importSite($data) {
        $pick = function(array $keys, $default = '') use ($data) {
            foreach ($keys as $k) {
                if (array_key_exists($k, $data)) {
                    $v = trim((string)$data[$k]);
                    if ($v !== '') return $v;
                }
            }
            return $default;
        };

        $parseDate = function($val) {
            if ($val === null) return null;
            $val = trim((string)$val);
            if ($val === '' || $val === '0') return null;

            if (is_numeric($val) && (int)$val > 30000 && (int)$val < 100000) {
                $unix = ((int)$val - 25569) * 86400;
                return date('Y-m-d', $unix);
            }

            if (preg_match('/^(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})/', $val, $m)) {
                $y = (int)$m[1]; $mo = (int)$m[2]; $d = (int)$m[3];
                if (checkdate($mo, $d, $y)) return sprintf('%04d-%02d-%02d', $y, $mo, $d);
            }

            if (preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})/', $val, $m)) {
                $d = (int)$m[1]; $mo = (int)$m[2]; $y = (int)$m[3];
                if (checkdate($mo, $d, $y)) return sprintf('%04d-%02d-%02d', $y, $mo, $d);
                if (checkdate($d, $mo, $y)) return sprintf('%04d-%02d-%02d', $y, $d, $mo);
            }

            $t = @strtotime(str_replace('/', '-', $val));
            return $t ? date('Y-m-d', $t) : null;
        };

        $truncate = function($val, $len) {
            $v = trim((string)$val);
            if ($v === '') return '';
            return substr($v, 0, $len);
        };

        $idSite = $pick(['id_site', 'code_site', 'site_id', 'idsite', 'code_site_client']);
        $idClient = $pick(['id_client', 'code_client', 'client_id', 'codeclient']);
        $nomClient = $pick(['nom_client', 'client_nom', 'nomclient', 'client', 'raison_sociale']);

        $nomSite = $pick(['nom', 'nom_site', 'site_nom', 'libelle_site']);
        $ville = $pick(['ville', 'city']);
        $adresse = $pick(['adresse', 'address']);
        $tel = $pick(['tel', 'telephone', 'contact_tel']);
        $fax = $pick(['fax']);
        $siteWeb = $pick(['siteweb', 'site_web', 'website', 'site']);
        $email = $pick(['email', 'mail']);
        $comment = $pick(['comment', 'commentaire', 'notes']);
        $modem = $pick(['modem']);
        $blocageRaw = $pick(['blocage', 'blocked', 'is_blocked'], '0');
        $datebl = $parseDate($pick(['datebl', 'date_blocage']));
        $datedbl = $parseDate($pick(['datedbl', 'date_deblocage']));
        $tel2 = $pick(['tel2', 'telephone2']);
        $tel3 = $pick(['tel3', 'telephone3']);
        $remoteLogin1 = $pick(['remote_login1', 'remote1', 'login_remote1']);
        $mdp1 = $pick(['mdp1', 'password1', 'motdepasse1']);
        $remoteLogin2 = $pick(['remote_login2', 'remote2', 'login_remote2']);
        $mdp2 = $pick(['mdp2', 'password2', 'motdepasse2']);
        $zoneGeo = $pick(['zone_geo', 'zone']);
        $telSiege = $pick(['tel_siege', 'telephone_siege']);
        $codeAgence = $pick(['code_agence', 'agence']);
        $latitude = $pick(['latitude', 'lat']);
        $longitude = $pick(['longitude', 'lng', 'long']);
        $contactNom = $pick(['contact_nom', 'contact', 'nom_contact']);

        $blocage = 0;
        $blocageNorm = strtolower(trim((string)$blocageRaw));
        if (in_array($blocageNorm, ['1', 'true', 'oui', 'yes', 'bloque', 'bloquee', 'bloqué', 'bloquée'], true)) {
            $blocage = 1;
        }

        if ($nomSite === '') return 'Nom du site manquant';

        if ($idClient === '' && $nomClient !== '') {
            $stmtClientByName = sqlsrv_query($this->conn, "SELECT TOP 1 ID_Client FROM SAV_Clients WHERE Nom = ?", [$nomClient]);
            if ($stmtClientByName && ($row = sqlsrv_fetch_array($stmtClientByName, SQLSRV_FETCH_ASSOC))) {
                $idClient = (string)$row['ID_Client'];
            }
        }

        if ($idClient === '') return "Client introuvable pour le site '$nomSite' (id_client/nom_client manquant)";

        $stmtClient = sqlsrv_query($this->conn, "SELECT TOP 1 ID_Client, Nom FROM SAV_Clients WHERE ID_Client = ?", [$idClient]);
        if (!$stmtClient || !($clientRow = sqlsrv_fetch_array($stmtClient, SQLSRV_FETCH_ASSOC))) {
            return "Client '$idClient' introuvable pour le site '$nomSite'";
        }

        $nomClientFinal = $nomClient !== '' ? $nomClient : (string)($clientRow['Nom'] ?? '');

        if ($idSite === '') {
            $seed = strtoupper(preg_replace('/[^A-Z0-9]/', '', substr($idClient, 0, 8) . substr($nomSite, 0, 8)));
            if ($seed === '') $seed = 'SITE';
            $idSite = 'SIT-' . substr($seed . md5($idClient . '|' . $nomSite), 0, 10);
        }

        $idSite = $truncate($idSite, 50);
        $idClient = $truncate($idClient, 50);
        $ville = $truncate($ville, 100);
        $nomClientFinal = $truncate($nomClientFinal, 255);
        $nomSite = $truncate($nomSite, 255);
        $tel = $truncate($tel, 50);
        $fax = $truncate($fax, 50);
        $siteWeb = $truncate($siteWeb, 255);
        $email = $truncate($email, 255);
        $modem = $truncate($modem, 100);
        $tel2 = $truncate($tel2, 50);
        $tel3 = $truncate($tel3, 50);
        $remoteLogin1 = $truncate($remoteLogin1, 100);
        $mdp1 = $truncate($mdp1, 100);
        $remoteLogin2 = $truncate($remoteLogin2, 100);
        $mdp2 = $truncate($mdp2, 100);
        $zoneGeo = $truncate($zoneGeo, 100);
        $telSiege = $truncate($telSiege, 50);
        $codeAgence = $truncate($codeAgence, 50);
        $latitude = $truncate($latitude, 50);
        $longitude = $truncate($longitude, 50);
        $contactNom = $truncate($contactNom, 100);

        $check = sqlsrv_query($this->conn, "SELECT Id_Site FROM SAV_Sites WHERE Id_Site = ?", [$idSite]);
        if ($check === false) {
            error_log('[CSV_IMPORT_SITE_CHECK] ' . db_last_error_message());
            return 'Erreur SQL check site.';
        }

        if (sqlsrv_has_rows($check)) {
            $sql = "UPDATE SAV_Sites SET
                        Id_Client = ?,
                        Ville = COALESCE(NULLIF(?, ''), Ville),
                        Nom_Client = COALESCE(NULLIF(?, ''), Nom_Client),
                        Nom = COALESCE(NULLIF(?, ''), Nom),
                        Adresse = COALESCE(NULLIF(?, ''), Adresse),
                        Tel = COALESCE(NULLIF(?, ''), Tel),
                        Fax = COALESCE(NULLIF(?, ''), Fax),
                        SiteWeb = COALESCE(NULLIF(?, ''), SiteWeb),
                        Email = COALESCE(NULLIF(?, ''), Email),
                        Comment = COALESCE(NULLIF(?, ''), Comment),
                        Modem = COALESCE(NULLIF(?, ''), Modem),
                        Blocage = ?,
                        DATEBL = COALESCE(?, DATEBL),
                        DATEDBL = COALESCE(?, DATEDBL),
                        TEL2 = COALESCE(NULLIF(?, ''), TEL2),
                        TEL3 = COALESCE(NULLIF(?, ''), TEL3),
                        Remote_Login1 = COALESCE(NULLIF(?, ''), Remote_Login1),
                        MDP1 = COALESCE(NULLIF(?, ''), MDP1),
                        Remote_Login2 = COALESCE(NULLIF(?, ''), Remote_Login2),
                        MDP2 = COALESCE(NULLIF(?, ''), MDP2),
                        Zone_Geo = COALESCE(NULLIF(?, ''), Zone_Geo),
                        Tel_Siege = COALESCE(NULLIF(?, ''), Tel_Siege),
                        Code_Agence = COALESCE(NULLIF(?, ''), Code_Agence),
                        latitude = COALESCE(NULLIF(?, ''), latitude),
                        longitude = COALESCE(NULLIF(?, ''), longitude),
                        contact_nom = COALESCE(NULLIF(?, ''), contact_nom)
                    WHERE Id_Site = ?";

            $params = [
                $idClient, $ville, $nomClientFinal, $nomSite, $adresse, $tel, $fax, $siteWeb, $email,
                $comment, $modem, $blocage, $datebl, $datedbl, $tel2, $tel3, $remoteLogin1, $mdp1,
                $remoteLogin2, $mdp2, $zoneGeo, $telSiege, $codeAgence, $latitude, $longitude, $contactNom,
                $idSite
            ];

            if (sqlsrv_query($this->conn, $sql, $params)) return true;
            error_log('[CSV_IMPORT_SITE_UPDATE] ' . db_last_error_message());
            return 'Erreur Update Site.';
        }

        $sql = "INSERT INTO SAV_Sites (
                    Id_Site, Id_Client, Ville, Nom_Client, Nom, Adresse, Tel, Fax, SiteWeb, Email,
                    Comment, Modem, Blocage, DATEBL, DATEDBL, TEL2, TEL3, Remote_Login1, MDP1,
                    Remote_Login2, MDP2, Zone_Geo, Tel_Siege, Code_Agence, latitude, longitude, contact_nom
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )";

        $params = [
            $idSite, $idClient, $ville, $nomClientFinal, $nomSite, $adresse, $tel, $fax, $siteWeb, $email,
            $comment, $modem, $blocage, $datebl, $datedbl, $tel2, $tel3, $remoteLogin1, $mdp1,
            $remoteLogin2, $mdp2, $zoneGeo, $telSiege, $codeAgence, $latitude, $longitude, $contactNom
        ];

        if (sqlsrv_query($this->conn, $sql, $params)) return true;
        error_log('[CSV_IMPORT_SITE_INSERT] ' . db_last_error_message());
        return 'Erreur Insert Site.';
    }

    private function importTicket($data) {
        // Expected: sujet, description, code_site (or code_client?), priorite
        // Finding site is tricky if only client is given. Prefer 'code_site' or 'nom_site'.
        
        // ... Implementation for tickets
        return "Import Tickets non implémenté pour l'instant."; // Placeholder
    }

    private function importCommande($data) {
        // Expected: numero_commande, code_client, montant_ht, date_commande
        $num = $data['numero_commande'] ?? '';
        if (empty($num)) return "Numéro commande manquant";

        // Find Client
         $clientId = null;
        if (!empty($data['code_client'])) {
            $stmt = sqlsrv_query($this->conn, "SELECT id FROM Clients WHERE code_client = ?", [$data['code_client']]);
            if ($row = sqlsrv_fetch_array($stmt)) $clientId = $row['id'];
        }

        if (!$clientId) return "Client introuvable";

        $sql = "INSERT INTO Commandes (numero_commande, client_id, montant_ht, date_commande, statut, cree_le) VALUES (?, ?, ?, ?, 'Brouillon', GETDATE())";
        $date = !empty($data['date_commande']) ? $data['date_commande'] : date('Y-m-d');

        if (sqlsrv_query($this->conn, $sql, [$num, $clientId, $data['montant_ht'] ?? 0, $date])) return true;
        return "Erreur SQL";
    }
}
?>
