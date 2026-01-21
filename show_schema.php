<?php
require 'connectDB.php';

echo "=== ATTENDANCE TABLE STRUCTURE ===\n";
$schema = $conn->query("SHOW CREATE TABLE attendance");
$row = $schema->fetch_assoc();
echo $row['Create Table'] . "\n";
?>
