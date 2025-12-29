<?php
session_start();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';

$roles_allowed = ['directeur', 'charge_compte', 'magasinier', 'ingenieur', 'technicien'];
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], $roles_allowed)) {
    header('Location: dashboard.php');
    exit;
}

$id = $_GET['id'] ?? 0;
$message = '';
$visite_data = null;

// Récupérer les données de la visite
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM visites_preventives WHERE id = ?');
    $stmt->execute([$id]);
    $visite_data = $stmt->fetch();
}

if (!$visite_data) {
    header('Location: visites.php');
    exit;
}

// Récupérer les fichiers existants
$fichiers = [];
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM fichiers_visite WHERE visite_id = ?');
    $stmt->execute([$id]);
    $fichiers = $stmt->fetchAll();
}

// Récupérer les listes pour les selects
$clients = $pdo->query('SELECT id, nom, code_client FROM clients ORDER BY nom')->fetchAll();
$contrats = $pdo->query('SELECT id, nom, code_contrat FROM contrats ORDER BY nom')->fetchAll();
$users = $pdo->query('SELECT id, nom, prenom, role FROM users ORDER BY nom')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code_client = $_POST['code_client'] ?? '';
    $code_contrat = $_POST['code_contrat'] ?? '';
    $details = $_POST['details'] ?? '';
    $statut = $_POST['statut'] ?? '';
    $user_id = $_POST['user_id'] ?? '';
    
    try {
        $stmt = $pdo->prepare('UPDATE visites_preventives SET code_client = ?, code_contrat = ?, details = ?, statut = ?, user_id = ? WHERE id = ?');
        $stmt->execute([$code_client, $code_contrat, $details, $statut, $user_id, $id]);
        
        // Upload nouveaux fichiers
        if (isset($_FILES['fichiers']) && is_array($_FILES['fichiers']['name'])) {
            $upload_dir = '../uploads/visites/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            for ($i = 0; $i < count($_FILES['fichiers']['name']); $i++) {
                if ($_FILES['fichiers']['error'][$i] === 0) {
                    $file_extension = pathinfo($_FILES['fichiers']['name'][$i], PATHINFO_EXTENSION);
                    $file_name = uniqid() . '.' . $file_extension;
                    $file_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($_FILES['fichiers']['tmp_name'][$i], $file_path)) {
                        $stmt = $pdo->prepare('INSERT INTO fichiers_visite (visite_id, nom_fichier, type_fichier) VALUES (?, ?, ?)');
                        $stmt->execute([$id, $file_name, $file_extension]);
                    }
                }
            }
        }
        
        $message = 'Visite préventive modifiée avec succès !';
        
        // Mettre à jour les données affichées
        $visite_data = array_merge($visite_data, $_POST);
        
        // Recharger les fichiers
        $stmt = $pdo->prepare('SELECT * FROM fichiers_visite WHERE visite_id = ?');
        $stmt->execute([$id]);
        $fichiers = $stmt->fetchAll();
    } catch (PDOException $e) {
        $message = 'Erreur : ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier une visite préventive - AEBDM</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h2>Modifier une visite préventive</h2>
    <?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <form method="post" enctype="multipart/form-data">
        <label>Client : 
            <select name="code_client" required>
                <option value="">Sélectionner un client</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?= htmlspecialchars($client['code_client']) ?>" <?= $visite_data['code_client'] === $client['code_client'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($client['nom']) ?> (<?= htmlspecialchars($client['code_client']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label><br>
        
        <label>Contrat : 
            <select name="code_contrat" required>
                <option value="">Sélectionner un contrat</option>
                <?php foreach ($contrats as $contrat): ?>
                    <option value="<?= htmlspecialchars($contrat['code_contrat']) ?>" <?= $visite_data['code_contrat'] === $contrat['code_contrat'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($contrat['nom']) ?> (<?= htmlspecialchars($contrat['code_contrat']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label><br>
        
        <label>Détails : <textarea name="details" rows="4" required><?= htmlspecialchars($visite_data['details']) ?></textarea></label><br>
        
        <label>Statut : 
            <select name="statut" required>
                <option value="">Sélectionner</option>
                <option value="encours" <?= $visite_data['statut'] === 'encours' ? 'selected' : '' ?>>En cours</option>
                <option value="terminee" <?= $visite_data['statut'] === 'terminee' ? 'selected' : '' ?>>Terminée</option>
                <option value="echouee" <?= $visite_data['statut'] === 'echouee' ? 'selected' : '' ?>>Échouée</option>
                <option value="annulee" <?= $visite_data['statut'] === 'annulee' ? 'selected' : '' ?>>Annulée</option>
            </select>
        </label><br>
        
        <label>Assigné à : 
            <select name="user_id" required>
                <option value="">Sélectionner un utilisateur</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= htmlspecialchars($user['id']) ?>" <?= $visite_data['user_id'] == $user['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?> (<?= htmlspecialchars($user['role']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label><br>
        
        <?php if ($fichiers): ?>
            <label>Fichiers existants :</label><br>
            <?php foreach ($fichiers as $fichier): ?>
                <a href="../uploads/visites/<?= htmlspecialchars($fichier['nom_fichier']) ?>" target="_blank">
                    <?= htmlspecialchars($fichier['nom_fichier']) ?>
                </a><br>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <label>Nouveaux fichiers : <input type="file" name="fichiers[]" multiple accept="image/*,.pdf,.doc,.docx"></label><br>
        
        <button type="submit">Modifier</button>
        <a href="visites.php">Retour</a>
    </form>
</body>
</html> 