<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['accueil', 'admin']);

if (!isset($_GET['id'])) header("Location: tickets.php");
$id = $_GET['id'];

$t = sqlsrv_fetch_array(query("SELECT ID_TICKET as id, PRIORITE as priorite, COMMENT as description FROM TICKET WHERE ID_TICKET = ?", [$id]), SQLSRV_FETCH_ASSOC);
if (!$t) die("Ticket introuvable");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $desc = $_POST['description'];
    $prio = $_POST['priorite'];
    
    $sql = "UPDATE TICKET SET COMMENT = ?, PRIORITE = ? WHERE ID_TICKET = ?";
    if (sqlsrv_query($conn, $sql, [$desc, $prio, $id])) {
        header("Location: tickets.php");
    } else {
        error_log('[ACCUEIL_TICKET_EDIT] ' . db_last_error_message());
        die("Erreur interne lors de la mise a jour du ticket.");
    }
}
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
            <div style="display:flex; align-items:center; gap:20px;">
                <button class="mobile-toggle"><i class="fa-solid fa-bars"></i></button>
                <h1>Modifier Ticket #<?php echo $id; ?></h1>
            </div>
            <a href="tickets.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Annuler</a>
        </header>
        <div class="card">
            <form method="POST">
                <div class="form-group">
                    <label><i class="fa-solid fa-gauge-high"></i> Priorité</label>
                    <select name="priorite" class="form-control">
                        <option value="normale" <?php if($t['priorite']=='normale') echo 'selected'; ?>>Normale</option>
                        <option value="haute" <?php if($t['priorite']=='haute') echo 'selected'; ?>>Haute</option>
                        <option value="urgente" <?php if($t['priorite']=='urgente') echo 'selected'; ?>>Urgente</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-align-left"></i> Description</label>
                    <textarea name="description" rows="5" class="form-control"><?php echo htmlspecialchars($t['description']); ?></textarea>
                </div>
                <button type="submit" class="btn btn-full"><i class="fa-solid fa-save"></i> Enregistrer les modifications</button>
            </form>
        </div>
    </div>
</body>
</html>
