<?php
require 'connectDB.php';
$views = $conn->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='rfidattendance' AND TABLE_TYPE='VIEW'");
echo "Views in database:\n";
while ($v = $views->fetch_assoc()) {
    echo "  - " . $v['TABLE_NAME'] . "\n";
}
?>
