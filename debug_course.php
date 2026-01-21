<?php
require 'connectDB.php';

// Debug: Check what's happening with ETE 319
$result = $conn->query("
    SELECT s.student_id, s.name, c.course_id, c.course_code, c.course_name
    FROM students s
    JOIN student_courses sc ON sc.student_id = s.student_id AND sc.status = 'active'
    JOIN courses c ON c.course_id = sc.course_id
    WHERE c.course_code = 'ETE 319'
    LIMIT 1
");

if ($result->num_rows > 0) {
    $student = $result->fetch_assoc();
    $sid = $student['student_id'];
    $cid = $student['course_id'];
    
    echo "Debugging student {$student['name']} in course {$student['course_code']}:\n\n";
    
    // Check closed sessions for this course
    $sessions = $conn->query("
        SELECT session_id, course_id, session_date, start_time, end_time, status
        FROM course_sessions
        WHERE course_id = $cid AND status = 'closed'
    ");
    
    echo "Closed sessions for course $cid:\n";
    if ($sessions->num_rows > 0) {
        while ($s = $sessions->fetch_assoc()) {
            $duration = (strtotime($s['end_time']) - strtotime($s['start_time'])) / 60;
            $units = ceil($duration / 50);
            echo "  Session {$s['session_id']}: {$s['session_date']} {$s['start_time']}-{$s['end_time']} ({$duration}min = $units units)\n";
            
            // Check attendance for this session
            $att = $conn->query("
                SELECT status FROM attendance 
                WHERE session_id = {$s['session_id']} AND student_id = $sid
            ");
            echo "    Attendance records for student: {$att->num_rows}\n";
        }
    } else {
        echo "  ✗ NO CLOSED SESSIONS FOUND for course $cid!\n";
    }
}
?>
