<?php
require 'connectDB.php';
date_default_timezone_set('Asia/Dhaka');

$now = date("H:i:s");
$today = date("Y-m-d");

// find sessions to close
$sessions = $conn->query("
    SELECT session_id, course_id
    FROM course_sessions
    WHERE session_date = '$today'
      AND status = 'active'
      AND '$now' >= end_time
");

while ($s = $sessions->fetch_assoc()) {

    $sid = $s['session_id'];
    $cid = $s['course_id'];

    // mark ABSENT students
    $stmt = $conn->prepare("
        INSERT INTO attendance (session_id, student_id, status)
        SELECT ?, sc.student_id, 'absent'
        FROM student_courses sc
        WHERE sc.course_id = ?
          AND sc.status = 'active'
          AND sc.student_id NOT IN (
              SELECT student_id
              FROM attendance
              WHERE session_id = ?
          )
    ");
    $stmt->bind_param("iii", $sid, $cid, $sid);
    $stmt->execute();

    // close session
    $conn->query("
        UPDATE course_sessions
        SET status = 'closed'
        WHERE session_id = $sid
    ");
}