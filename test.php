<?php
require 'c:/Users/map45/Desktop/sav/config/db.php';
$stmt = sqlsrv_query($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='TICKET'");
while($r=sqlsrv_fetch_array($stmt)) { echo $r[0]."\n"; }
