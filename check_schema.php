<?php
require 'connectDB.php';

// Check attendance_summary view structure
$result = $conn->query('SELECT * FROM attendance_summary LIMIT 1');
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "Columns in attendance_summary view:\n";
    print_r(array_keys($row));
} else {
    echo "View is empty, checking schema...\n";
    $schema = $conn->query("SHOW FIELDS FROM attendance_summary");
    while ($col = $schema->fetch_assoc()) {
        echo $col['Field'] . " - " . $col['Type'] . "\n";
    }
}
?>
