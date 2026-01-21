<?php
require 'connectDB.php';
$result = $conn->query("SHOW CREATE VIEW attendance_summary");
$row = $result->fetch_assoc();
echo $row['Create View'] . "\n";
?>
