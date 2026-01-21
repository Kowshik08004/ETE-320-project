<?php
require 'connectDB.php';

echo "=== TEST: 50-MINUTE UNIT SYSTEM WITH ETE 319 ===\n\n";

// Get ETE 319 course details
$course = $conn->query("
    SELECT c.course_id, c.course_code, c.course_name
    FROM courses c
    WHERE c.course_code = 'ETE 319'
    LIMIT 1
")->fetch_assoc();

if (!$course) {
    echo "✗ Course ETE 319 not found\n";
    exit;
}

$course_id = $course['course_id'];
echo "1. Course: {$course['course_code']} - {$course['course_name']}\n";

// Get enrolled students
$students_res = $conn->query("
    SELECT sc.student_id, s.name, s.roll_no
    FROM student_courses sc
    JOIN students s ON s.student_id = sc.student_id
    WHERE sc.course_id = $course_id AND sc.status = 'active'
");

$student_count = $students_res->num_rows;
echo "   Enrolled students: $student_count\n\n";

if ($student_count == 0) {
    echo "✗ No students enrolled in ETE 319\n";
    exit;
}

// 2. Create test session (120 minutes = 3 units, need to round up from 2.4)
echo "2. Creating test session (120 minutes = 3 units ceiling)...\n";
$test_date = date('Y-m-d', strtotime('-2 days')); // 2 days ago
$test_start = '09:00:00';
$test_end = '11:00:00'; // 120 minutes

// Check if session already exists
$check = $conn->query("
    SELECT session_id FROM course_sessions 
    WHERE course_id = $course_id 
    AND session_date = '$test_date'
    AND start_time = '$test_start'
");

if ($check->num_rows > 0) {
    // Clean up old one
    $old_session = $check->fetch_assoc();
    $conn->query("DELETE FROM attendance WHERE session_id = {$old_session['session_id']}");
    $conn->query("DELETE FROM course_sessions WHERE session_id = {$old_session['session_id']}");
    echo "   Cleaned up old test session\n";
}

$sql = "INSERT INTO course_sessions (course_id, session_date, start_time, end_time, grace_minutes, status) 
        VALUES ($course_id, '$test_date', '$test_start', '$test_end', 10, 'closed')";

if (!$conn->query($sql)) {
    echo "   ✗ Error creating session: " . $conn->error . "\n";
    exit;
}

$session_id = $conn->insert_id;
echo "   ✓ Session created (session_id: $session_id, date: $test_date)\n\n";

// 3. Simulate RFID swipes for some students
echo "3. Simulating RFID card swipes...\n";
$students_res = $conn->query("
    SELECT sc.student_id FROM student_courses sc
    WHERE sc.course_id = $course_id AND sc.status = 'active'
");

$swipe_time = '09:05:00'; // 5 minutes after session start
$swiped_students = [];

while ($s = $students_res->fetch_assoc()) {
    $swiped_students[] = $s['student_id'];
    // Check if log already exists
    $check = $conn->query("
        SELECT * FROM users_logs 
        WHERE serialnumber = {$s['student_id']} 
        AND checkindate = '$test_date'
        AND timein = '$swipe_time'
    ");
    
    if ($check->num_rows == 0) {
        $conn->query("
            INSERT INTO users_logs (serialnumber, checkindate, timein) 
            VALUES ({$s['student_id']}, '$test_date', '$swipe_time')
        ");
    }
}

echo "   ✓ RFID swipes for " . count($swiped_students) . " students\n\n";

// 4. Generate attendance
echo "4. Generating attendance records...\n";
$duration_minutes = (strtotime($test_end) - strtotime($test_start)) / 60;
$num_units = max(1, ceil($duration_minutes / 50));
echo "   Duration: $duration_minutes minutes = $num_units units\n";

// Clean up old attendance for this session if any
$conn->query("DELETE FROM attendance WHERE session_id = $session_id");

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

echo "   ✓ Attendance generated\n";
echo "     - Present: $present_count students\n";
echo "     - Absent: $absent_count students\n";
echo "     - Total records: " . (($present_count + $absent_count) * $num_units) . " (" . ($present_count + $absent_count) . " students × $num_units units)\n\n";

// 5. Show sample attendance records
echo "5. Sample attendance records:\n";
$records = $conn->query("
    SELECT s.roll_no, s.name, a.unit_number, a.status
    FROM attendance a
    JOIN students s ON s.student_id = a.student_id
    WHERE a.session_id = $session_id
    ORDER BY a.student_id, a.unit_number
    LIMIT 10
");

while ($r = $records->fetch_assoc()) {
    echo "   {$r['roll_no']} {$r['name']}: Unit {$r['unit_number']} = {$r['status']}\n";
}

// 6. Check attendance_summary
echo "\n6. Checking attendance_summary for ETE 319...\n";
$summary = $conn->query("
    SELECT student_id, name, course_code, attended, total_sessions, percentage
    FROM attendance_summary
    WHERE course_id = $course_id
    ORDER BY student_id
");

if ($summary->num_rows > 0) {
    echo "   Data from attendance_summary:\n";
    $count = 0;
    while ($row = $summary->fetch_assoc()) {
        if ($count < 8) {
            echo "   {$row['name']}: {$row['attended']}/{$row['total_sessions']} = {$row['percentage']}%\n";
            $count++;
        }
    }
} else {
    echo "   ✗ No data in attendance_summary\n";
}

echo "\n✓ TEST COMPLETE!\n";
echo "\nCleanup commands (if needed):\n";
echo "  DELETE FROM attendance WHERE session_id = $session_id;\n";
echo "  DELETE FROM course_sessions WHERE session_id = $session_id;\n";
?>
