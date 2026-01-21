<?php
require 'connectDB.php';

echo "=== TEST: 50-MINUTE UNITS WITH MIXED PRESENT & ABSENT ===\n\n";

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

$total_students = count($all_students);
echo "   Total enrolled: $total_students\n\n";

// 2. Create test session
echo "2. Creating test session (100 minutes = 2 units)...\n";
$test_date = date('Y-m-d', strtotime('-3 days'));
$test_start = '14:00:00';
$test_end = '15:40:00'; // 100 minutes

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
        VALUES ($course_id, '$test_date', '$test_start', '$test_end', 10, 'closed')";

if (!$conn->query($sql)) {
    echo "   ✗ Error: " . $conn->error . "\n";
    exit;
}

$session_id = $conn->insert_id;
echo "   ✓ Session created (session_id: $session_id, date: $test_date)\n\n";

// 3. Mark ONLY FIRST 3 STUDENTS as present (swipe)
echo "3. Simulating RFID card swipes for SOME students...\n";
$swipe_time = '14:05:00'; // 5 minutes after start
$present_students = [];

for ($i = 0; $i < 3 && $i < $total_students; $i++) {
    $sid = $all_students[$i]['student_id'];
    $present_students[] = $sid;
    
    // Check and insert RFID log
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
    echo "   ✓ {$all_students[$i]['name']} (SWIPED - PRESENT)\n";
}

echo "   (Remaining " . ($total_students - 3) . " students NOT swiped - ABSENT)\n\n";

// 4. Generate attendance for all students
echo "4. Generating attendance records...\n";
$duration_minutes = (strtotime($test_end) - strtotime($test_start)) / 60;
$num_units = max(1, ceil($duration_minutes / 50));
echo "   Duration: $duration_minutes minutes = $num_units units\n";

// Clean up
$conn->query("DELETE FROM attendance WHERE session_id = $session_id");

$present_count = 0;
$absent_count = 0;

foreach ($all_students as $student) {
    $student_id = $student['student_id'];
    
    // Check if student swiped
    $log = $conn->query("
        SELECT timein FROM users_logs
        WHERE serialnumber = $student_id
        AND checkindate = '$test_date'
        AND timein BETWEEN '$test_start' AND '$test_end'
        LIMIT 1
    ");
    
    $status = ($log->num_rows > 0) ? 'present' : 'absent';
    
    // Create records for each unit
    for ($unit = 1; $unit <= $num_units; $unit++) {
        $conn->query("
            INSERT INTO attendance (session_id, student_id, status, unit_number, marked_at)
            VALUES ($session_id, $student_id, '$status', $unit, NOW())
        ");
    }
    
    if ($status === 'present') $present_count++;
    else $absent_count++;
}

echo "   ✓ Generated attendance records\n";
echo "     - PRESENT: $present_count students\n";
echo "     - ABSENT: $absent_count students\n";
echo "     - TOTAL records: " . (($present_count + $absent_count) * $num_units) . "\n\n";

// 5. Show all attendance records
echo "5. All attendance records for this session:\n";
$records = $conn->query("
    SELECT s.student_id, s.roll_no, s.name, a.unit_number, a.status
    FROM attendance a
    JOIN students s ON s.student_id = a.student_id
    WHERE a.session_id = $session_id
    ORDER BY a.student_id, a.unit_number
");

$record_count = 0;
while ($r = $records->fetch_assoc()) {
    $marker = "✗";
    foreach ($present_students as $ps) {
        if ($ps == $r['student_id']) {
            $marker = "✓";
            break;
        }
    }
    echo "   $marker {$r['roll_no']} {$r['name']}: Unit {$r['unit_number']} = {$r['status']}\n";
    $record_count++;
}
echo "   Total records shown: $record_count\n\n";

// 6. Check attendance_summary
echo "6. Attendance Summary for ETE 319:\n";
$summary = $conn->query("
    SELECT student_id, name, attended, total_sessions, percentage
    FROM attendance_summary
    WHERE course_id = $course_id
    ORDER BY student_id
");

while ($row = $summary->fetch_assoc()) {
    $is_present = in_array($row['student_id'], $present_students) ? "✓" : "✗";
    echo "   $is_present {$row['name']}: {$row['attended']}/{$row['total_sessions']} units = {$row['percentage']}%\n";
}

echo "\n✓ TEST COMPLETE!\n";
echo "\nNote:\n";
echo "  ✓ = Student was PRESENT (swiped in)\n";
echo "  ✗ = Student was ABSENT (did not swipe in)\n";
echo "\nExpected results:\n";
echo "  - Present students should have records with 'present' status\n";
echo "  - Absent students should have records with 'absent' status\n";
echo "  - Both count towards total units but only present adds to attended count\n";
?>
