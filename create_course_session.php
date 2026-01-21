<?php
session_start();
if (!isset($_SESSION['Admin-name'])) exit("Unauthorized");
require 'connectDB.php';

$course_id = $_POST['course_id'];
$date      = $_POST['session_date'];
$start     = $_POST['start_time'];
$end       = $_POST['end_time'];
$grace     = $_POST['grace_minutes'] ?? 10;

if (!$course_id || !$date || !$start || !$end) {
    exit("Missing data");
}

$stmt = $conn->prepare("
  INSERT INTO class_sessions
  (course_id, session_date, start_time, end_time, grace_minutes)
  VALUES (?, ?, ?, ?, ?)
");

$stmt->bind_param("isssi",
  $course_id, $date, $start, $end, $grace
);

if (!$stmt->execute()) {
    exit("Session already exists");
}

header("Location: course_sessions.php");
exit();
