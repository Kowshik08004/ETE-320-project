<?php
require 'connectDB.php';

echo "=== TEST SESSIONS IN DATABASE ===\n\n";

// List all sessions
$sessions = $conn->query("
    SELECT cs.session_id, c.course_code, cs.session_date, cs.start_time, cs.end_time, cs.status,
           COUNT(a.session_id) as att_count
    FROM course_sessions cs
    LEFT JOIN courses c ON c.course_id = cs.course_id
    LEFT JOIN attendance a ON a.session_id = cs.session_id
    GROUP BY cs.session_id
    ORDER BY cs.session_id DESC
");

echo "All sessions:\n";
$count = 0;
while ($s = $sessions->fetch_assoc()) {
    $count++;
    $date_check = (strtotime($s['session_date']) >= strtotime(date('Y-m-d', strtotime('-5 days')))) ? "[TEST]" : "";
    echo "$count. Session {$s['session_id']}: {$s['course_code']} | {$s['session_date']} ({$s['start_time']}-{$s['end_time']}) | {$s['status']} | {$s['att_count']} records $date_check\n";
}

echo "\n=== CLEANUP NEEDED? ===\n";
echo "To remove test sessions, run:\n";
echo "  php cleanup_sessions.php\n";
?>
