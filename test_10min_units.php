<?php
require 'connectDB.php';

echo "=== TEST: 10-MINUTE UNITS WITH 5-MINUTE GRACE PERIOD ===\n\n";

// Get ETE 319 course
$course = $conn->query("
    SELECT c.course_id, c.course_code, c.course_name
    FROM courses c
    WHERE c.course_code = 'ETE 319'
    LIMIT 1
")->fetch_assoc();

$course_id = $course['course_id'];
echo "1. Course: {$course['course_code']}\n";

// Get all enrolled students
$students_res = $conn->query("
    SELECT sc.student_id, s.name, s.roll_no
    FROM student_courses sc
    JOIN students s ON s.student_id = sc.student_id
    WHERE sc.course_id = $course_id AND sc.status = 'active'
    ORDER BY sc.student_id
");

$all_students = [];
while ($s = $students_res->fetch_assoc()) {
    $all_students[] = $s;
}

echo "   Students: " . count($all_students) . "\n\n";

// Create test session (30 minutes = 3 units with 10-min units)
echo "2. Creating test session...\n";
$test_date = date('Y-m-d', strtotime('-1 day'));
$test_start = '13:00:00';
$test_end = '13:30:00'; // 30 minutes

// Clean up old test
$check = $conn->query("
    SELECT session_id FROM course_sessions 
    WHERE course_id = $course_id 
    AND session_date = '$test_date'
    AND start_time = '$test_start'
");

if ($check->num_rows > 0) {
    $old = $check->fetch_assoc();
    $conn->query("DELETE FROM attendance WHERE session_id = {$old['session_id']}");
    $conn->query("DELETE FROM course_sessions WHERE session_id = {$old['session_id']}");
}

$sql = "INSERT INTO course_sessions (course_id, session_date, start_time, end_time, grace_minutes, status) 
        VALUES ($course_id, '$test_date', '$test_start', '$test_end', 5, 'closed')";

if (!$conn->query($sql)) {
    echo "   ✗ Error: " . $conn->error . "\n";
    exit;
}

$session_id = $conn->insert_id;
echo "   ✓ Session created (session_id: $session_id)\n";
echo "   Duration: 30 minutes = 3 units (10 min per unit)\n";
echo "   Grace period: 5 minutes\n\n";

// Simulate RFID swipes (only first 4 students)
echo "3. Simulating RFID card swipes...\n";
$swipe_time = '13:04:00'; // 4 minutes after start (within grace period = present)
$present_students = [];

for ($i = 0; $i < 4 && $i < count($all_students); $i++) {
    $sid = $all_students[$i]['student_id'];
    $present_students[] = $sid;
    
    $check = $conn->query("
        SELECT * FROM users_logs 
        WHERE serialnumber = $sid 
        AND checkindate = '$test_date'
        AND timein = '$swipe_time'
    ");
    
    if ($check->num_rows == 0) {
        $conn->query("
            INSERT INTO users_logs (serialnumber, checkindate, timein) 
            VALUES ($sid, '$test_date', '$swipe_time')
        ");
    }
    echo "   ✓ {$all_students[$i]['name']} (PRESENT - swiped at 13:04, within 5-min grace)\n";
}

echo "   ✗ Remaining " . (count($all_students) - 4) . " students ABSENT\n\n";

// Generate attendance
echo "4. Generating attendance records...\n";
$duration_minutes = (strtotime($test_end) - strtotime($test_start)) / 60;
$num_units = max(1, ceil($duration_minutes / 10));
echo "   Expected units: $num_units\n";

$conn->query("DELETE FROM attendance WHERE session_id = $session_id");

$present_count = 0;
$absent_count = 0;

foreach ($all_students as $student) {
    $student_id = $student['student_id'];
    
    $log = $conn->query("
        SELECT timein FROM users_logs
        WHERE serialnumber = $student_id
        AND checkindate = '$test_date'
        AND timein BETWEEN '$test_start' AND '$test_end'
        LIMIT 1
    ");
    
    $status = ($log->num_rows > 0) ? 'present' : 'absent';
    
    for ($unit = 1; $unit <= $num_units; $unit++) {
        $conn->query("
            INSERT INTO attendance (session_id, student_id, status, unit_number, marked_at)
            VALUES ($session_id, $student_id, '$status', $unit, NOW())
        ");
    }
    
    if ($status === 'present') $present_count++;
    else $absent_count++;
}

echo "   ✓ Generated: $present_count present, $absent_count absent\n";
echo "   ✓ Total records: " . (($present_count + $absent_count) * $num_units) . " (" . ($present_count + $absent_count) . " students × $num_units units)\n\n";

// Show records
echo "5. Attendance records:\n";
$records = $conn->query("
    SELECT s.name, a.unit_number, a.status
    FROM attendance a
    JOIN students s ON s.student_id = a.student_id
    WHERE a.session_id = $session_id
    ORDER BY a.student_id, a.unit_number
");

while ($r = $records->fetch_assoc()) {
    $marker = in_array($r['student_id'], $present_students) ? "✓" : "✗";
    echo "   $marker {$r['name']}: Unit {$r['unit_number']} = {$r['status']}\n";
}

// Check summary
echo "\n6. Attendance Summary:\n";
$summary = $conn->query("
    SELECT student_id, name, attended, total_sessions, percentage
    FROM attendance_summary
    WHERE course_id = $course_id
    ORDER BY student_id
");

while ($row = $summary->fetch_assoc()) {
    $marker = in_array($row['student_id'], $present_students) ? "✓" : "✗";
    echo "   $marker {$row['name']}: {$row['attended']}/{$row['total_sessions']} units = {$row['percentage']}%\n";
}

echo "\n✓ TEST COMPLETE!\n";
?>
