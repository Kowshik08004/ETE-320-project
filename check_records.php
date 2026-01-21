<?php
require 'connectDB.php';
$result = $conn->query("SELECT COUNT(*) as cnt FROM attendance WHERE session_id=5");
$row = $result->fetch_assoc();
echo "Total attendance records for session 5: " . $row['cnt'] . "\n";

// Show details
$result = $conn->query("SELECT session_id, student_id, status, unit_number FROM attendance WHERE session_id=5 ORDER BY student_id, unit_number");
while ($r = $result->fetch_assoc()) {
    echo "Student {$r['student_id']}, Unit {$r['unit_number']}: {$r['status']}\n";
}
?>
