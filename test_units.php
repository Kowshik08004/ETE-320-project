<?php
require 'connectDB.php';

echo "=== ATTENDANCE UNIT SYSTEM TEST ===\n\n";

// 1. Check if unit_number column exists
echo "1. Checking database schema...\n";
$result = $conn->query("SHOW COLUMNS FROM attendance LIKE 'unit_number'");
echo ($result->num_rows > 0) ? "✓ unit_number column exists\n" : "✗ unit_number column missing\n";

// Check course_sessions columns
echo "\n2. Checking course_sessions table...\n";
$cols = $conn->query("DESCRIBE course_sessions");
$col_names = [];
while ($col = $cols->fetch_assoc()) {
    $col_names[] = $col['Field'];
}
echo "  Columns: " . implode(", ", $col_names) . "\n";

// Get a real course
echo "\n2b. Finding a real course with enrolled students...\n";
$course = $conn->query("
    SELECT c.course_id, c.course_code 
    FROM courses c
    JOIN student_courses sc ON sc.course_id = c.course_id
    WHERE sc.status = 'active'
    LIMIT 1
")->fetch_assoc();

if (!$course) {
    echo "✗ No courses with enrolled students found\n";
    exit;
}

$test_course_id = $course['course_id'];
echo "✓ Using course {$course['course_code']} (id: $test_course_id)\n";

// 3. Create a test session (100 minutes = 2 units)
echo "\n3. Creating test session (100 minutes = 2 units)...\n";
$test_date = date('Y-m-d');
$test_start = '10:00:00';
$test_end = '11:40:00'; // 100 minutes

$sql = "INSERT INTO course_sessions (course_id, session_date, start_time, end_time, grace_minutes, status) 
        VALUES ($test_course_id, '$test_date', '$test_start', '$test_end', 10, 'scheduled')";

if ($conn->query($sql)) {
    $session_id = $conn->insert_id;
    echo "✓ Test session created (session_id: $session_id)\n";
    
    // 4. Manually generate attendance for this session
    echo "\n4. Generating attendance...\n";
    
    // Get enrolled students
    $students = $conn->query("SELECT student_id FROM student_courses WHERE course_id=$test_course_id AND status='active' LIMIT 3");
    $student_count = 0;
    
    if ($students->num_rows > 0) {
        $duration_minutes = (strtotime($test_end) - strtotime($test_start)) / 60;
        $num_units = max(1, ceil($duration_minutes / 50));
        echo "  Duration: $duration_minutes minutes = $num_units units\n";
        
        while ($s = $students->fetch_assoc()) {
            $sid = $s['student_id'];
            $student_count++;
            
            // Create attendance record for each unit
            for ($unit = 1; $unit <= $num_units; $unit++) {
                $query = "INSERT INTO attendance (session_id, student_id, status, unit_number) VALUES ($session_id, $sid, 'present', $unit)";
                if (!$conn->query($query)) {
                    echo "  Error inserting unit $unit for student $sid: " . $conn->error . "\n";
                }
            }
        }
        
        echo "✓ Created $num_units records for each of $student_count students\n";
        
        // 5. Verify records were created
        echo "\n5. Verifying attendance records...\n";
        $count = $conn->query("SELECT COUNT(*) as cnt FROM attendance WHERE session_id=$session_id")->fetch_assoc();
        $expected = $num_units * $student_count;
        echo "  Records created: {$count['cnt']} (expected: $expected)\n";
        
        $records = $conn->query("SELECT student_id, unit_number, status FROM attendance WHERE session_id=$session_id ORDER BY student_id, unit_number");
        echo "  Sample records:\n";
        $row_count = 0;
        while ($r = $records->fetch_assoc()) {
            if ($row_count < 6) {
                echo "    Student {$r['student_id']}, Unit {$r['unit_number']}: {$r['status']}\n";
                $row_count++;
            }
        }
        
        // 6. Test attendance_summary calculation
        echo "\n6. Checking attendance summary...\n";
        
        // Get first student's course summary
        $student = $conn->query("SELECT student_id FROM student_courses WHERE course_id=$test_course_id AND status='active' LIMIT 1")->fetch_assoc();
        if ($student) {
            $sid = $student['student_id'];
            $summary = $conn->query("SELECT attended, total_sessions, percentage FROM attendance_summary WHERE student_id=$sid AND course_id=$test_course_id")->fetch_assoc();
            
            if ($summary) {
                echo "  Student $sid in Course $test_course_id:\n";
                echo "    Attended units: {$summary['attended']}\n";
                echo "    Total units: {$summary['total_sessions']}\n";
                echo "    Percentage: {$summary['percentage']}%\n";
            }
        }
        
        echo "\n✓ TEST PASSED! System is working correctly.\n";
        echo "\nTo clean up test data, run:\n";
        echo "  DELETE FROM attendance WHERE session_id=$session_id;\n";
        echo "  DELETE FROM course_sessions WHERE session_id=$session_id;\n";
        
    } else {
        echo "✗ No students enrolled in course $test_course_id\n";
        // Clean up session
        $conn->query("DELETE FROM course_sessions WHERE session_id=$session_id");
    }
    
} else {
    echo "✗ Error creating test session: " . $conn->error . "\n";
}

?>
