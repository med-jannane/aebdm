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
$intervention_data = null;

// Récupérer les données de l'intervention
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM interventions WHERE id = ?');
    $stmt->execute([$id]);
    $intervention_data = $stmt->fetch();
}

if (!$intervention_data) {
    header('Location: interventions.php');
    exit;
}

// Récupérer les fichiers existants
$fichiers = [];
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM fichiers_intervention WHERE intervention_id = ?');
    $stmt->execute([$id]);
    $fichiers = $stmt->fetchAll();
}

// Récupérer les listes pour les selects
$clients = $pdo->query('SELECT id, nom, code_client FROM clients ORDER BY nom')->fetchAll();
$contrats = $pdo->query('SELECT id, nom, code_contrat FROM contrats ORDER BY nom')->fetchAll();
$users = $pdo->query('SELECT id, nom, prenom, role FROM users ORDER BY nom')->fetchAll();
$produits = $pdo->query('SELECT id, nom, reference FROM produits ORDER BY nom')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code_client = $_POST['code_client'] ?? '';
    $code_contrat = $_POST['code_contrat'] ?? '';
    $details = $_POST['details'] ?? '';
    $statut = $_POST['statut'] ?? '';
    $user_id = $_POST['user_id'] ?? '';
    $produit_ajoute = $_POST['produit_ajoute'] ?? '';
    
    try {
        $stmt = $pdo->prepare('UPDATE interventions SET code_client = ?, code_contrat = ?, details = ?, statut = ?, user_id = ?, produit_ajoute = ? WHERE id = ?');
        $stmt->execute([$code_client, $code_contrat, $details, $statut, $user_id, $produit_ajoute, $id]);
        
        // Upload nouveaux fichiers
        if (isset($_FILES['fichiers']) && is_array($_FILES['fichiers']['name'])) {
            $upload_dir = '../uploads/interventions/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            for ($i = 0; $i < count($_FILES['fichiers']['name']); $i++) {
                if ($_FILES['fichiers']['error'][$i] === 0) {
                    $file_extension = pathinfo($_FILES['fichiers']['name'][$i], PATHINFO_EXTENSION);
                    $file_name = uniqid() . '.' . $file_extension;
                    $file_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($_FILES['fichiers']['tmp_name'][$i], $file_path)) {
                        $stmt = $pdo->prepare('INSERT INTO fichiers_intervention (intervention_id, nom_fichier, type_fichier) VALUES (?, ?, ?)');
                        $stmt->execute([$id, $file_name, $file_extension]);
                    }
                }
            }
        }
        
        $message = 'Intervention modifiée avec succès !';
        
        // Mettre à jour les données affichées
        $intervention_data = array_merge($intervention_data, $_POST);
        
        // Recharger les fichiers
        $stmt = $pdo->prepare('SELECT * FROM fichiers_intervention WHERE intervention_id = ?');
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
    <title>Modifier une intervention - AEBDM</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h2>Modifier une intervention</h2>
    <?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <form method="post" enctype="multipart/form-data">
        <label>Client : 
            <select name="code_client" required>
                <option value="">Sélectionner un client</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?= htmlspecialchars($client['code_client']) ?>" <?= $intervention_data['code_client'] === $client['code_client'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($client['nom']) ?> (<?= htmlspecialchars($client['code_client']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label><br>
        
        <label>Contrat : 
            <select name="code_contrat" required>
                <option value="">Sélectionner un contrat</option>
                <?php foreach ($contrats as $contrat): ?>
                    <option value="<?= htmlspecialchars($contrat['code_contrat']) ?>" <?= $intervention_data['code_contrat'] === $contrat['code_contrat'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($contrat['nom']) ?> (<?= htmlspecialchars($contrat['code_contrat']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label><br>
        
        <label>Détails : <textarea name="details" rows="4" required><?= htmlspecialchars($intervention_data['details']) ?></textarea></label><br>
        
        <label>Statut : 
            <select name="statut" required>
                <option value="">Sélectionner</option>
                <option value="encours" <?= $intervention_data['statut'] === 'encours' ? 'selected' : '' ?>>En cours</option>
                <option value="terminee" <?= $intervention_data['statut'] === 'terminee' ? 'selected' : '' ?>>Terminée</option>
                <option value="echouee" <?= $intervention_data['statut'] === 'echouee' ? 'selected' : '' ?>>Échouée</option>
                <option value="annulee" <?= $intervention_data['statut'] === 'annulee' ? 'selected' : '' ?>>Annulée</option>
            </select>
        </label><br>
        
        <label>Assigné à : 
            <select name="user_id" required>
                <option value="">Sélectionner un utilisateur</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= htmlspecialchars($user['id']) ?>" <?= $intervention_data['user_id'] == $user['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?> (<?= htmlspecialchars($user['role']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label><br>
        
        <label>Produit ajouté : <input type="text" name="produit_ajoute" value="<?= htmlspecialchars($intervention_data['produit_ajoute'] ?? '') ?>"></label><br>
        
        <?php if ($fichiers): ?>
            <label>Fichiers existants :</label><br>
            <?php foreach ($fichiers as $fichier): ?>
                <a href="../uploads/interventions/<?= htmlspecialchars($fichier['nom_fichier']) ?>" target="_blank">
                    <?= htmlspecialchars($fichier['nom_fichier']) ?>
                </a><br>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <label>Nouveaux fichiers : <input type="file" name="fichiers[]" multiple accept="image/*,.pdf,.doc,.docx"></label><br>
        
        <button type="submit">Modifier</button>
        <a href="interventions.php">Retour</a>
    </form>
</body>
</html> 