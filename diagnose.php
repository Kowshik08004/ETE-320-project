<?php
require 'connectDB.php';

echo "=== ATTENDANCE SYSTEM DIAGNOSTIC ===\n\n";

// 1. Check attendance_summary view
echo "1. Checking attendance_summary view...\n";
try {
    $result = $conn->query("SELECT * FROM attendance_summary LIMIT 1");
    if ($result) {
        $row = $result->fetch_assoc();
        if ($row) {
            echo "   ✓ View working, sample row:\n";
            echo "     attended: {$row['attended']}\n";
            echo "     total_sessions: {$row['total_sessions']}\n";
            echo "     percentage: {$row['percentage']}\n";
        } else {
            echo "   ✓ View exists but no data\n";
        }
    } else {
        echo "   ✗ View query failed: " . $conn->error . "\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// 2. Check attendance table structure
echo "\n2. Checking attendance table...\n";
$schema = $conn->query("DESCRIBE attendance");
echo "   Columns:\n";
while ($col = $schema->fetch_assoc()) {
    echo "     - {$col['Field']}: {$col['Type']}\n";
}

// 3. Check if there are any existing closed sessions with attendance
echo "\n3. Checking existing closed sessions...\n";
$closed = $conn->query("
    SELECT cs.session_id, cs.course_id, cs.session_date, cs.start_time, cs.end_time,
           COUNT(a.session_id) as att_count
    FROM course_sessions cs
    LEFT JOIN attendance a ON a.session_id = cs.session_id
    WHERE cs.status = 'closed'
    GROUP BY cs.session_id
    LIMIT 5
");

if ($closed->num_rows > 0) {
    echo "   Found closed sessions:\n";
    while ($row = $closed->fetch_assoc()) {
        echo "     Session {$row['session_id']}: {$row['session_date']} ({$row['start_time']}-{$row['end_time']}) - {$row['att_count']} attendance records\n";
    }
} else {
    echo "   ✗ No closed sessions found\n";
}

// 4. Check a specific student's attendance summary
echo "\n4. Checking student attendance data...\n";
$student = $conn->query("
    SELECT student_id, name FROM students LIMIT 1
")->fetch_assoc();

if ($student) {
    $sid = $student['student_id'];
    $summary = $conn->query("
        SELECT * FROM attendance_summary 
        WHERE student_id = $sid
        LIMIT 3
    ");
    
    if ($summary->num_rows > 0) {
        echo "   Student {$student['name']} ($sid) summary:\n";
        while ($row = $summary->fetch_assoc()) {
            echo "     Course {$row['course_code']}: {$row['attended']}/{$row['total_sessions']} = {$row['percentage']}%\n";
        }
    } else {
        echo "   ✗ No summary data for student $sid\n";
    }
}

// 5. Check the view definition
echo "\n5. View definition check...\n";
$view_def = $conn->query("SHOW CREATE VIEW attendance_summary")->fetch_assoc();
if (strpos($view_def['Create View'], 'closed') !== false) {
    echo "   ✓ View correctly filters by status = 'closed'\n";
} else {
    echo "   ⚠ View may not be filtering correctly\n";
}

echo "\n=== DIAGNOSTICS COMPLETE ===\n";
?>
