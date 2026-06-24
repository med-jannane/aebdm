<?php
require_once __DIR__ . '/../config/db.php';

echo "Modification du schéma de TICKET...<br>";

// 1. Modifier ID_SITE en VARCHAR(50)
$sql1 = "ALTER TABLE TICKET ALTER COLUMN ID_SITE VARCHAR(50)";
$stmt1 = sqlsrv_query($conn, $sql1);
if ($stmt1 === false) {
    error_log('[FIX_SCHEMA_ID_SITE] ' . db_last_error_message());
    echo "Erreur interne ID_SITE.<br>";
} else {
    echo "Succès: ID_SITE converti en VARCHAR(50).<br>";
}

// 2. Modifier ID_CLIENT en VARCHAR(50) au cas où
$sql2 = "ALTER TABLE TICKET ALTER COLUMN ID_CLIENT VARCHAR(50)";
$stmt2 = sqlsrv_query($conn, $sql2);
if ($stmt2 === false) {
    error_log('[FIX_SCHEMA_ID_CLIENT] ' . db_last_error_message());
    echo "Erreur interne ID_CLIENT.<br>";
} else {
    echo "Succès: ID_CLIENT converti en VARCHAR(50).<br>";
}

// 3. Modifier Interventions.ticket_id en VARCHAR(50) au cas où
$sql3 = "ALTER TABLE Interventions ALTER COLUMN ticket_id VARCHAR(50)";
sqlsrv_query($conn, $sql3);

// 4. Ajouter date_fin
$sql4 = "IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[Interventions]') AND name = 'date_fin')
BEGIN ALTER TABLE Interventions ADD date_fin DATETIME NULL; END";
sqlsrv_query($conn, $sql4);

// 5. Ajouter les autres colonnes de rapport
$sql5 = "IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[Interventions]') AND name = 'travaux_recommandes')
BEGIN
    ALTER TABLE Interventions ADD travaux_recommandes NVARCHAR(MAX) NULL;
    ALTER TABLE Interventions ADD commentaire_client NVARCHAR(MAX) NULL;
    ALTER TABLE Interventions ADD nom_signataire_client NVARCHAR(100) NULL;
    ALTER TABLE Interventions ADD heure_arrivee_matin TIME NULL;
    ALTER TABLE Interventions ADD heure_depart_matin TIME NULL;
    ALTER TABLE Interventions ADD duree_trajet_matin INT NULL;
    ALTER TABLE Interventions ADD heure_arrivee_soir TIME NULL;
    ALTER TABLE Interventions ADD heure_depart_soir TIME NULL;
    ALTER TABLE Interventions ADD duree_trajet_soir INT NULL;
END";
sqlsrv_query($conn, $sql5);

echo "<h3>Modification terminée avec succès !</h3>";
echo "<p>La base de données a été mise à jour. Vous pouvez fermer cet onglet et rafraîchir la page de l'application.</p>";
