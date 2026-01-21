<?php
require 'connectDB.php';

echo "Starting migration...\n";

// Check if unit_number column exists
$result = $conn->query("SHOW COLUMNS FROM attendance LIKE 'unit_number'");

if ($result->num_rows == 0) {
    echo "Adding unit_number column to attendance table...\n";
    
    $sql = "ALTER TABLE attendance ADD COLUMN unit_number INT DEFAULT 1 AFTER status";
    if ($conn->query($sql)) {
        echo "✓ Column unit_number added successfully\n";
    } else {
        echo "✗ Error adding column: " . $conn->error . "\n";
        exit(1);
    }
} else {
    echo "✓ Column unit_number already exists\n";
}

// If there are existing attendance records without unit_number, we should handle the migration
$check = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE unit_number IS NULL OR unit_number = 0");
$row = $check->fetch_assoc();

if ($row['count'] > 0) {
    echo "Found {$row['count']} existing attendance records. Setting unit_number = 1 for backward compatibility...\n";
    $conn->query("UPDATE attendance SET unit_number = 1 WHERE unit_number IS NULL OR unit_number = 0");
    echo "✓ Migration complete\n";
} else {
    echo "✓ No migration needed\n";
}

echo "\nAttendance table schema:\n";
$schema = $conn->query("DESCRIBE attendance");
while ($col = $schema->fetch_assoc()) {
    echo "  {$col['Field']}: {$col['Type']} {$col['Null']} {$col['Default']}\n";
}

echo "\nMigration finished successfully!\n";
?>
