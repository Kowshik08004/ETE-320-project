<?php
require 'connectDB.php';

echo "=== MODIFYING ATTENDANCE TABLE PRIMARY KEY ===\n\n";

// First, drop the old primary key and add a new one with unit_number
$sql = "ALTER TABLE attendance DROP PRIMARY KEY, ADD PRIMARY KEY (session_id, student_id, unit_number)";

if ($conn->query($sql)) {
    echo "✓ Successfully updated PRIMARY KEY to include unit_number\n";
    echo "  Old: PRIMARY KEY (session_id, student_id)\n";
    echo "  New: PRIMARY KEY (session_id, student_id, unit_number)\n";
} else {
    echo "✗ Error updating PRIMARY KEY: " . $conn->error . "\n";
    exit(1);
}

// Show new schema
echo "\n=== UPDATED TABLE STRUCTURE ===\n";
$schema = $conn->query("SHOW CREATE TABLE attendance");
$row = $schema->fetch_assoc();
echo $row['Create Table'] . "\n";

echo "\n✓ Migration complete!\n";
?>
