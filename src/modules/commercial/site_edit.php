<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['commercial', 'admin', 'accueil', 'tech', 'directeur', 'charge_de_compte']);

$role = $_SESSION['role'];
if (!isset($_GET['id'])) {
    header("Location: clients.php");
    exit;
}

$id = $_GET['id'];
$error = "";
$success = "";

// Récupérer le site via Id_Site (clé métier)
$site = sqlsrv_fetch_array(query("SELECT * FROM SAV_Sites WHERE Id_Site = ?", [$id]), SQLSRV_FETCH_ASSOC);

if (!$site) die("Site introuvable.");

$client_id = $site['Id_Client']; // Majuscule

// Traitement Update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nom = $_POST['nom'];
    $adresse = $_POST['adresse'];
    $ville = $_POST['ville'];
    $code_site = $_POST['code_site'];
    $contact_nom = $_POST['contact_nom'];
    $contact_tel = $_POST['contact_tel'];
    $latitude = !empty($_POST['latitude']) ? $_POST['latitude'] : null;
    $longitude = !empty($_POST['longitude']) ? $_POST['longitude'] : null;

    if (empty($nom)) {
        $error = "Le nom de l'établissement est obligatoire.";
    } else {
        $sql = "UPDATE SAV_Sites SET 
                    Nom = ?, Adresse = ?, Ville = ?, Id_Site = ?, 
                    contact_nom = ?, TEL = ?, 
                    latitude = ?, longitude = ? 
                WHERE Id_Site = ?";
        $params = [
            $nom, $adresse, $ville, $code_site, 
            $contact_nom, $contact_tel, 
            $latitude, $longitude, 
            $id
        ];
        
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt) {
            $success = "Les informations du site ont été mises à jour avec succès.";
            // Rafraichir
            $id = $code_site;
            $site = sqlsrv_fetch_array(query("SELECT * FROM SAV_Sites WHERE Id_Site = ?", [$id]), SQLSRV_FETCH_ASSOC);
        } else {
            error_log('[COMMERCIAL_SITE_EDIT] ' . db_last_error_message());
            $error = "Erreur lors de la mise a jour du site.";
        }
    }
}

$pageTitle = "Modification Site : " . $site['Nom'];
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
            <div style="display:flex;align-items:center;gap:16px;">
                <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                <div style="display:flex; flex-direction:column;">
                    <h1 style="margin:0;"><i class="fa-solid fa-map-location-dot text-accent" style="margin-right:8px;"></i>Éditer le Site</h1>
                    <span style="font-size:.9rem; color:var(--text-muted); font-weight:600;">Mise à jour des coordonnées</span>
                </div>
            </div>
            <div style="display:flex;gap:10px;">
                <a href="client_details.php?id=<?= $client_id ?>" class="btn btn-secondary" style="border-radius:30px;"><i class="fa-solid fa-arrow-left"></i> Retour au client</a>
            </div>
        </header>

        <div class="page-content" style="max-width:900px; margin:0 auto;">

            <?php if($error): ?><div class="alert alert-error alert-auto-dismiss"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if($success): ?><div class="alert alert-success alert-auto-dismiss"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>

            <form method="POST" class="card" style="padding:0; overflow:hidden;">
                
                <div style="background:var(--surface-2); padding:24px 32px; border-bottom:1px solid rgba(58,1,92,.08); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
                    <h3 style="margin:0; font-size:1.2rem; color:var(--dark-amethyst-3);"><i class="fa-solid fa-building text-primary" style="margin-right:8px;"></i>Informations du Site Local</h3>
                    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <button type="button" class="btn btn-sm" onclick="getLocation()" style="background:linear-gradient(135deg, var(--info), #03A9F4); border:none; box-shadow:0 4px 10px rgba(3,169,244,.3);"><i class="fa-solid fa-location-crosshairs"></i> Me géolocaliser</button>
                        <a href="https://maps.google.com" target="_blank" class="btn btn-sm btn-secondary" title="Chercher sur Maps"><i class="fa-solid fa-map-location-dot"></i></a>
                    </div>
                </div>
                
                <div style="padding:32px;">
                    <span id="geoStatus" style="font-size:0.9em; font-weight:600; color:var(--info); display:block; margin-bottom:16px; text-align:right;"></span>

                    <div class="form-section">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Nom de l'établissement <span class="text-danger">*</span></label>
                                <div style="position:relative;">
                                    <i class="fa-regular fa-building input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                                    <input type="text" name="nom" value="<?= htmlspecialchars($site['Nom']) ?>" required class="form-control" style="padding-left:36px; width:100%;">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Code Site (Interne)</label>
                                <div style="position:relative;">
                                    <i class="fa-solid fa-hashtag input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                                    <input type="text" name="code_site" value="<?= htmlspecialchars($site['Id_Site'] ?? '') ?>" class="form-control" style="padding-left:36px; width:100%;">
                                </div>
                            </div>
                        </div>

                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Adresse Postale <span class="text-danger">*</span></label>
                            <div style="position:relative;">
                                <i class="fa-solid fa-map-pin input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                                <input type="text" name="adresse" id="edit_adresse" value="<?= htmlspecialchars($site['Adresse']) ?>" required class="form-control" style="padding-left:36px; width:100%;">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Ville</label>
                            <input type="text" name="ville" id="edit_ville" value="<?= htmlspecialchars($site['Ville'] ?? '') ?>" class="form-control">
                        </div>
                    </div>

                    <div style="border-top:1px dashed rgba(58,1,92,.1); margin:32px 0;"></div>

                    <h4 style="color:var(--dark-amethyst-3); margin-top:0; margin-bottom:20px;"><i class="fa-solid fa-address-card text-accent" style="margin-right:8px;"></i>Contact & Géolocalisation</h4>
                    
                    <div class="form-section">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Nom du responsable local</label>
                                <div style="position:relative;">
                                    <i class="fa-solid fa-user-tie input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                                    <input type="text" name="contact_nom" value="<?= htmlspecialchars($site['contact_nom'] ?? '') ?>" class="form-control" style="padding-left:36px; width:100%;">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Téléphone direct</label>
                                <div style="position:relative;">
                                    <i class="fa-solid fa-phone input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                                    <input type="text" name="contact_tel" value="<?= htmlspecialchars($site['TEL'] ?? ($site['contact_tel'] ?? '')) ?>" class="form-control" style="padding-left:36px; width:100%;">
                                </div>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Latitude <i class="fa-brands fa-google text-muted"></i></label>
                                <input type="text" name="latitude" id="edit_lat" value="<?= htmlspecialchars($site['latitude'] ?? '') ?>" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Longitude <i class="fa-brands fa-google text-muted"></i></label>
                                <input type="text" name="longitude" id="edit_lng" value="<?= htmlspecialchars($site['longitude'] ?? '') ?>" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div style="background:var(--surface-2); padding:24px 32px; border-top:1px solid rgba(58,1,92,.08); text-align:right;">
                    <button type="submit" class="btn" style="padding:14px 32px; font-size:1.05rem;"><i class="fa-solid fa-cloud-arrow-up"></i> Sauvegarder les modifications</button>
                </div>
            </form>
            
            <div style="height:40px;"></div>
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

        function getLocation() {
            const statusSpan = document.getElementById('geoStatus');
            statusSpan.style.display = 'block';
            statusSpan.textContent = "Recherche satellite en cours...";

            const isLocalHost = location.hostname === 'localhost' || location.hostname === '127.0.0.1';
            if (!window.isSecureContext && !isLocalHost) {
                statusSpan.innerHTML = "<i class='fa-solid fa-lock'></i> GPS bloqué: cette page est en HTTP. Utilisez HTTPS pour autoriser la géolocalisation.";
                statusSpan.style.color = "var(--danger)";
                return;
            }
            
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        
                        document.getElementById('edit_lat').value = lat;
                        document.getElementById('edit_lng').value = lng;
                        statusSpan.textContent = "Coordonnées GPS captées !";

                        // Reverse geocoding avec Nominatim API
                        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data && data.address) {
                                    const addressParts = [];
                                    if (data.address.house_number) addressParts.push(data.address.house_number);
                                    if (data.address.road) addressParts.push(data.address.road);
                                    
                                    const fullAddress = addressParts.join(' ');
                                    const city = data.address.city || data.address.town || data.address.village || '';
                                    
                                    if (fullAddress) document.getElementById('edit_adresse').value = fullAddress;
                                    if (city) document.getElementById('edit_ville').value = city;
                                    
                                    statusSpan.innerHTML = "<i class='fa-solid fa-check'></i> Adresse géolocalisée avec succès !";
                                    statusSpan.style.color = "var(--success)";
                                    setTimeout(() => statusSpan.style.display = 'none', 3000);
                                } else {
                                    statusSpan.textContent = "Coordonnées trouvées sans adresse précise.";
                                    statusSpan.style.color = "var(--warning)";
                                }
                            })
                            .catch(err => {
                                statusSpan.textContent = "Erreur résolution d'adresse.";
                                statusSpan.style.color = "var(--danger)";
                            });
                    },
                    function(error) {
                        switch (error.code) {
                            case error.PERMISSION_DENIED:
                                statusSpan.textContent = "Permission GPS refusée par le navigateur.";
                                break;
                            case error.POSITION_UNAVAILABLE:
                                statusSpan.textContent = "Position GPS indisponible (signal faible).";
                                break;
                            case error.TIMEOUT:
                                statusSpan.textContent = "Timeout GPS: réessayez en extérieur.";
                                break;
                            default:
                                statusSpan.textContent = "Géolocalisation refusée ou impossible.";
                        }
                        statusSpan.style.color = "var(--danger)";
                    },
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                );
            } else {
                statusSpan.textContent = "Naviguateur non compatible GPS.";
                statusSpan.style.color = "var(--danger)";
            }
        }
    </script>
</body>
</html>
