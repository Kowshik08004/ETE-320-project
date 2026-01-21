<?php
require 'connectDB.php';

echo "=== CLEANING UP TEST SESSIONS ===\n\n";

// Get all recent test sessions (created in last 5 days)
$test_sessions = $conn->query("
    SELECT session_id FROM course_sessions 
    WHERE session_date >= DATE_SUB(CURDATE(), INTERVAL 5 DAY)
    ORDER BY session_id ASC
");

$deleted_sessions = 0;
$deleted_attendance = 0;
$deleted_logs = 0;

while ($row = $test_sessions->fetch_assoc()) {
    $sid = $row['session_id'];
    
    // Get the session date for log cleanup
    $session = $conn->query("SELECT session_date FROM course_sessions WHERE session_id = $sid")->fetch_assoc();
    $session_date = $session['session_date'];
    
    // Delete attendance records
    $result = $conn->query("DELETE FROM attendance WHERE session_id = $sid");
    $deleted_attendance += $conn->affected_rows;
    
    // Delete the session
    $result = $conn->query("DELETE FROM course_sessions WHERE session_id = $sid");
    if ($conn->affected_rows > 0) {
        $deleted_sessions++;
        echo "✓ Deleted Session $sid\n";
    }
}

// Delete orphaned RFID logs from test sessions (from last 5 days)
$result = $conn->query("DELETE FROM users_logs WHERE checkindate >= DATE_SUB(CURDATE(), INTERVAL 5 DAY) AND checkindate <= CURDATE()");
$deleted_logs = $conn->affected_rows;

echo "\n=== CLEANUP SUMMARY ===\n";
echo "✓ Sessions deleted: $deleted_sessions\n";
echo "✓ Attendance records deleted: $deleted_attendance\n";
echo "✓ RFID logs deleted: $deleted_logs\n";
echo "\n✓ CLEANUP COMPLETE!\n";
?>
