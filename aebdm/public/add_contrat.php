<?php
session_start();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['directeur', 'charge_compte'])) {
    header('Location: dashboard.php');
    exit;
}

$user = $_SESSION['user'];

// Récupérer la liste des clients pour le select
$clients = $pdo->query('SELECT id, nom, code_client FROM clients ORDER BY nom')->fetchAll();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code_contrat = $_POST['code_contrat'] ?? '';
    $nom = $_POST['nom'] ?? '';
    $details = $_POST['details'] ?? '';
    $code_client = $_POST['code_client'] ?? '';
    $date_debut = $_POST['date_debut'] ?? '';
    $date_fin = $_POST['date_fin'] ?? '';
    
    // Upload photo
    $photo = '';
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
    // Upload PDF
    $pdf = '';
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
        $stmt = $pdo->prepare('INSERT INTO contrats (code_contrat, nom, details, photo, pdf, code_client, date_debut, date_fin) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$code_contrat, $nom, $details, $photo, $pdf, $code_client, $date_debut, $date_fin]);
        header('Location: contrats.php?message=Contrat ajouté avec succès !');
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
    <title>Ajouter un contrat - AEBDM</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container">
        <div class="page-header">
            <h2>Ajouter un contrat</h2>
            <a href="contrats.php" class="btn-edit"><i class="fas fa-arrow-left"></i> Retour</a>
        </div>
        <?php if ($message): ?>
            <div class="error"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="modern-form">
            <div class="form-grid">
                <div class="form-group">
                    <label><i class="fas fa-barcode"></i> Code contrat</label>
                    <input type="text" name="code_contrat" required placeholder="Code contrat unique">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-file-signature"></i> Nom</label>
                    <input type="text" name="nom" required placeholder="Nom du contrat">
                </div>
                <div class="form-group full-width">
                    <label><i class="fas fa-align-left"></i> Détails</label>
                    <textarea name="details" rows="4" placeholder="Détails du contrat"></textarea>
                </div>
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
                    <label><i class="fas fa-calendar-plus"></i> Date début</label>
                    <input type="date" name="date_debut" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar-check"></i> Date fin</label>
                    <input type="date" name="date_fin" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-image"></i> Photo</label>
                    <input type="file" name="photo" accept="image/*" class="file-input">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-file-pdf"></i> PDF</label>
                    <input type="file" name="pdf" accept=".pdf" class="file-input">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-add"><i class="fas fa-plus"></i> Ajouter le contrat</button>
                <a href="contrats.php" class="btn-edit"><i class="fas fa-times"></i> Annuler</a>
            </div>
        </form>
    </div>
    <?php include 'includes/footer.php'; ?>
    <script src="assets/script.js"></script>
</body>
</html> 