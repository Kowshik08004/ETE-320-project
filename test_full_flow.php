<?php
require 'connectDB.php';

echo "=== QUICK TEST: 50-MINUTE UNIT SYSTEM ===\n\n";

// 1. Find a real course with students
$course = $conn->query("
    SELECT c.course_id, c.course_code, c.course_name, COUNT(sc.student_id) as student_count
    FROM courses c
    JOIN student_courses sc ON sc.course_id = c.course_id AND sc.status = 'active'
    GROUP BY c.course_id
    LIMIT 1
")->fetch_assoc();

if (!$course) {
    echo "✗ No courses with enrolled students found\n";
    exit;
}

echo "1. Selected Course: {$course['course_code']} - {$course['course_name']}\n";
echo "   Students enrolled: {$course['student_count']}\n\n";

// 2. Create test session (150 minutes = 3 units)
echo "2. Creating test session (150 minutes = 3 units)...\n";
$course_id = $course['course_id'];
$test_date = date('Y-m-d', strtotime('-1 day')); // Yesterday
$test_start = '10:00:00';
$test_end = '12:30:00'; // 150 minutes

$sql = "INSERT INTO course_sessions (course_id, session_date, start_time, end_time, grace_minutes, status) 
        VALUES ($course_id, '$test_date', '$test_start', '$test_end', 10, 'closed')";

if (!$conn->query($sql)) {
    echo "   ✗ Error creating session: " . $conn->error . "\n";
    exit;
}

$session_id = $conn->insert_id;
echo "   ✓ Session created (session_id: $session_id, date: $test_date)\n\n";

// 3. Simulate RFID card swipes for some students
echo "3. Simulating RFID card swipes...\n";
$students = $conn->query("
    SELECT student_id FROM student_courses 
    WHERE course_id = $course_id AND status = 'active'
    LIMIT 5
");

$swipe_time = '10:05:00'; // 5 minutes after session start
$student_ids = [];
while ($s = $students->fetch_assoc()) {
    $student_ids[] = $s['student_id'];
    $conn->query("
        INSERT INTO users_logs (serialnumber, checkindate, timein) 
        VALUES ({$s['student_id']}, '$test_date', '$swipe_time')
    ");
}
echo "   ✓ Created RFID swipes for " . count($student_ids) . " students\n\n";

// 4. Generate attendance (simulating the lock session action)
echo "4. Generating attendance records...\n";
$duration_minutes = (strtotime($test_end) - strtotime($test_start)) / 60;
$num_units = max(1, ceil($duration_minutes / 50));
echo "   Duration: $duration_minutes minutes = $num_units units\n";

$all_students = $conn->query("
    SELECT student_id FROM student_courses 
    WHERE course_id = $course_id AND status = 'active'
");

$present_count = 0;
$absent_count = 0;

while ($s = $all_students->fetch_assoc()) {
    $student_id = $s['student_id'];
    
    // Check if student swiped in
    $log = $conn->query("
        SELECT timein FROM users_logs
        WHERE serialnumber = $student_id
        AND checkindate = '$test_date'
        AND timein BETWEEN '$test_start' AND '$test_end'
        ORDER BY timein ASC
        LIMIT 1
    ");
    
    $status = ($log->num_rows > 0) ? 'present' : 'absent';
    
    // Create one record per unit
    for ($unit = 1; $unit <= $num_units; $unit++) {
        $conn->query("
            INSERT INTO attendance (session_id, student_id, status, unit_number, marked_at)
            VALUES ($session_id, $student_id, '$status', $unit, NOW())
        ");
    }
    
    if ($status === 'present') $present_count++;
    else $absent_count++;
}

echo "   ✓ Created attendance records\n";
echo "     - Present students: $present_count\n";
echo "     - Absent students: $absent_count\n";
echo "     - Total records: " . (($present_count + $absent_count) * $num_units) . "\n\n";

// 5. Verify attendance records
echo "5. Verifying attendance records...\n";
$records = $conn->query("
    SELECT student_id, unit_number, status 
    FROM attendance 
    WHERE session_id = $session_id 
    ORDER BY student_id, unit_number
");

echo "   Sample of created records:\n";
$count = 0;
while ($r = $records->fetch_assoc()) {
    if ($count < 8) {
        echo "     Student {$r['student_id']}: Unit {$r['unit_number']} = {$r['status']}\n";
        $count++;
    }
}

// 6. Check attendance summary
echo "\n6. Checking attendance summary...\n";
$summary = $conn->query("
    SELECT student_id, name, attended, total_sessions, percentage
    FROM attendance_summary
    WHERE course_id = $course_id
    ORDER BY student_id
    LIMIT 3
");

if ($summary->num_rows > 0) {
    echo "   Sample from attendance_summary:\n";
    while ($row = $summary->fetch_assoc()) {
        echo "     {$row['name']}: {$row['attended']}/{$row['total_sessions']} = {$row['percentage']}%\n";
    }
} else {
    echo "   ⚠ No data in attendance_summary yet (view only includes 'closed' sessions)\n";
}

echo "\n✓ TEST COMPLETE!\n";
echo "\nTo clean up test data:\n";
echo "  DELETE FROM attendance WHERE session_id = $session_id;\n";
echo "  DELETE FROM course_sessions WHERE session_id = $session_id;\n";
echo "  DELETE FROM users_logs WHERE checkindate = '$test_date';\n";
?>
