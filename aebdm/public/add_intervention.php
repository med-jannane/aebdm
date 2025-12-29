<?php
session_start();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';

$roles_allowed = ['directeur', 'charge_compte', 'magasinier', 'ingenieur', 'technicien'];
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], $roles_allowed)) {
    header('Location: dashboard.php');
    exit;
}

$user = $_SESSION['user'];

// Récupérer la liste des clients et contrats
$clients = $pdo->query('SELECT id, nom, code_client FROM clients ORDER BY nom')->fetchAll();
$contrats = $pdo->query('SELECT id, nom, code_contrat FROM contrats ORDER BY nom')->fetchAll();
$users = $pdo->query('SELECT id, nom, prenom, role FROM users ORDER BY nom')->fetchAll();
$produits = $pdo->query('SELECT id, nom, reference FROM produits ORDER BY nom')->fetchAll();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code_client = $_POST['code_client'] ?? '';
    $code_contrat = $_POST['code_contrat'] ?? '';
    $details = $_POST['details'] ?? '';
    $statut = $_POST['statut'] ?? '';
    $user_id = $_POST['user_id'] ?? '';
    $produit_ajoute = $_POST['produit_ajoute'] ?? '';
    try {
        $stmt = $pdo->prepare('INSERT INTO interventions (code_client, code_contrat, details, statut, user_id, produit_ajoute) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$code_client, $code_contrat, $details, $statut, $user_id, $produit_ajoute]);
        $intervention_id = $pdo->lastInsertId();
        // Upload multi-fichiers
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
                        $stmt->execute([$intervention_id, $file_name, $file_extension]);
                    }
                }
            }
        }
        header('Location: interventions.php?message=Intervention ajoutée avec succès !');
        exit;
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
    <title>Ajouter une intervention - AEBDM</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container">
        <div class="page-header">
            <h2>Ajouter une intervention</h2>
            <a href="interventions.php" class="btn-edit"><i class="fas fa-arrow-left"></i> Retour</a>
        </div>
        <?php if ($message): ?>
            <div class="error"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="modern-form">
            <div class="form-grid">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Client</label>
                    <select name="code_client" required>
                        <option value="">Sélectionner un client</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= htmlspecialchars($client['code_client']) ?>">
                                <?= htmlspecialchars($client['nom']) ?> (<?= htmlspecialchars($client['code_client']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-file-contract"></i> Contrat</label>
                    <select name="code_contrat" required>
                        <option value="">Sélectionner un contrat</option>
                        <?php foreach ($contrats as $contrat): ?>
                            <option value="<?= htmlspecialchars($contrat['code_contrat']) ?>">
                                <?= htmlspecialchars($contrat['nom']) ?> (<?= htmlspecialchars($contrat['code_contrat']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group full-width">
                    <label><i class="fas fa-align-left"></i> Détails</label>
                    <textarea name="details" rows="4" required placeholder="Détails de l'intervention"></textarea>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-flag"></i> Statut</label>
                    <select name="statut" required>
                        <option value="">Sélectionner</option>
                        <option value="encours">En cours</option>
                        <option value="terminee">Terminée</option>
                        <option value="echouee">Échouée</option>
                        <option value="annulee">Annulée</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-user-cog"></i> Assigné à</label>
                    <select name="user_id" required>
                        <option value="">Sélectionner un utilisateur</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= htmlspecialchars($user['id']) ?>">
                                <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?> (<?= htmlspecialchars($user['role']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-box"></i> Produit ajouté</label>
                    <input type="text" name="produit_ajoute" value="<?= htmlspecialchars($produit_ajoute ?? '') ?>" placeholder="Produit ajouté">
                </div>
                <div class="form-group full-width">
                    <label><i class="fas fa-paperclip"></i> Fichiers</label>
                    <input type="file" name="fichiers[]" multiple accept="image/*,.pdf,.doc,.docx" class="file-input">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-add"><i class="fas fa-plus"></i> Ajouter l'intervention</button>
                <a href="interventions.php" class="btn-edit"><i class="fas fa-times"></i> Annuler</a>
            </div>
        </form>
    </div>
    <?php include 'includes/footer.php'; ?>
    <script src="assets/script.js"></script>
</body>
</html> 