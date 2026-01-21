<?php
session_start();
if (!isset($_SESSION['Admin-name'])) {
    header("location: login.php");
    exit();
}

require 'connectDB.php';

// ----------- INPUT -----------
$class_id = $_POST['class_id'] ?? null;
$date     = $_POST['session_date'] ?? null;
$time     = $_POST['start_time'] ?? null;
$end_time = $_POST['end_time'] ?? null;

// ----------- VALIDATION -------
if (!$class_id || !$date || !$time || !$end_time) {
    die("Missing session data");
}

// ----------- DUPLICATE CHECK (FIXED) -------
$sql = "
    SELECT session_id
    FROM class_sessions
    WHERE class_id = ?
      AND session_date = ?
      AND start_time = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $class_id, $date, $time);
$stmt->execute();

if ($stmt->get_result()->num_rows > 0) {
    die("Duplicate session: same class, same date, same start time");
}


// ----------- INSERT SESSION ---------------
$sql = "
    INSERT INTO class_sessions 
        (class_id, session_date, start_time, end_time)
    VALUES (?, ?, ?, ?)
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("isss", $class_id, $date, $time, $end_time);
$stmt->execute();

// ----------- DONE -------------------------
header("Location: class_sessions.php");
exit();
