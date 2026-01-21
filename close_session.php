<?php
require 'connectDB.php';

$session_id = $_POST['session_id'];

$conn->query("
    INSERT INTO attendance (session_id, student_id, status)
    SELECT $session_id, sc.student_id, 'absent'
    FROM student_courses sc
    JOIN courses c ON c.course_id = sc.course_id
    JOIN course_sessions cs ON cs.course_id = c.course_id
    WHERE cs.session_id = $session_id
      AND sc.student_id NOT IN (
        SELECT student_id FROM attendance WHERE session_id = $session_id
      )
");

$conn->query("
    UPDATE course_sessions
    SET status='closed'
    WHERE session_id=$session_id
");

exit("done");
