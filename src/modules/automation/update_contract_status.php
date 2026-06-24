<?php
require_once __DIR__ . '/../../../config/db.php';

function updateExpiredContracts($conn) {
    // Set status to 'TERMINE' for contracts where Date_Fin < GETDATE() AND ETAT is not already 'TERMINE'
    $sql1 = "UPDATE CONTRAT 
            SET ETAT = 'TERMINE' 
            WHERE Date_Fin < GETDATE() 
            AND ETAT != 'TERMINE'";
    
    $stmt = sqlsrv_query($conn, $sql1);
    if ($stmt === false) {
        // Log error silently or handle as needed
        // error_log('Failed to update expired contracts.');
    }
}
?>
