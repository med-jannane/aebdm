<?php
session_start();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['directeur', 'charge_compte'])) {
    header('Location: dashboard.php');
    exit;
}

$id = $_GET['id'] ?? 0;
$message = '';
$contrat_data = null;

// Récupérer les données du contrat
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM contrats WHERE id = ?');
    $stmt->execute([$id]);
    $contrat_data = $stmt->fetch();
}

if (!$contrat_data) {
    header('Location: contrats.php');
    exit;
}

// Récupérer la liste des clients pour le select
$clients = $pdo->query('SELECT id, nom, code_client FROM clients ORDER BY nom')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code_contrat = $_POST['code_contrat'] ?? '';
    $nom = $_POST['nom'] ?? '';
    $details = $_POST['details'] ?? '';
    $code_client = $_POST['code_client'] ?? '';
    $date_debut = $_POST['date_debut'] ?? '';
    $date_fin = $_POST['date_fin'] ?? '';
    
    // Upload photo si nouvelle
    $photo = $contrat_data['photo'];
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $upload_dir = '../uploads/contrats/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $file_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $file_path)) {
            $photo = 'uploads/contrats/' . $file_name;
        }
    }
    
    // Upload PDF si nouveau
    $pdf = $contrat_data['pdf'];
    if (isset($_FILES['pdf']) && $_FILES['pdf']['error'] === 0) {
        $upload_dir = '../uploads/contrats/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $file_extension = pathinfo($_FILES['pdf']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['pdf']['tmp_name'], $file_path)) {
            $pdf = 'uploads/contrats/' . $file_name;
        }
    }
    
    try {
        $stmt = $pdo->prepare('UPDATE contrats SET code_contrat = ?, nom = ?, details = ?, photo = ?, pdf = ?, code_client = ?, date_debut = ?, date_fin = ? WHERE id = ?');
        $stmt->execute([$code_contrat, $nom, $details, $photo, $pdf, $code_client, $date_debut, $date_fin, $id]);
        $message = 'Contrat modifié avec succès !';
        
        // Mettre à jour les données affichées
        $contrat_data = array_merge($contrat_data, $_POST);
        $contrat_data['photo'] = $photo;
        $contrat_data['pdf'] = $pdf;
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
    <title>Modifier un contrat - AEBDM</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h2>Modifier un contrat</h2>
    <?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <form method="post" enctype="multipart/form-data">
        <label>Code contrat : <input type="text" name="code_contrat" value="<?= htmlspecialchars($contrat_data['code_contrat']) ?>" required></label><br>
        <label>Nom : <input type="text" name="nom" value="<?= htmlspecialchars($contrat_data['nom']) ?>" required></label><br>
        <label>Détails : <textarea name="details" rows="4"><?= htmlspecialchars($contrat_data['details']) ?></textarea></label><br>
        <label>Client : 
            <select name="code_client" required>
                <option value="">Sélectionner un client</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?= htmlspecialchars($client['code_client']) ?>" <?= $contrat_data['code_client'] === $client['code_client'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($client['nom']) ?> (<?= htmlspecialchars($client['code_client']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label><br>
        <label>Date début : <input type="date" name="date_debut" value="<?= htmlspecialchars($contrat_data['date_debut']) ?>" required></label><br>
        <label>Date fin : <input type="date" name="date_fin" value="<?= htmlspecialchars($contrat_data['date_fin']) ?>" required></label><br>
        
        <?php if ($contrat_data['photo']): ?>
            <label>Photo actuelle : <img src="../<?= htmlspecialchars($contrat_data['photo']) ?>" width="100"></label><br>
        <?php endif; ?>
        <label>Nouvelle photo : <input type="file" name="photo" accept="image/*"></label><br>
        
        <?php if ($contrat_data['pdf']): ?>
            <label>PDF actuel : <a href="../<?= htmlspecialchars($contrat_data['pdf']) ?>" target="_blank">Voir PDF</a></label><br>
        <?php endif; ?>
        <label>Nouveau PDF : <input type="file" name="pdf" accept=".pdf"></label><br>
        
        <button type="submit">Modifier</button>
        <a href="contrats.php">Retour</a>
    </form>
</body>
</html> 