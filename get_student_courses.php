<?php
require 'connectDB.php';

$student_id = $_GET['student_id'] ?? null;

if (!$student_id) {
    exit("<tr><td colspan='2'>Invalid student</td></tr>");
}

/*
  IMPORTANT:
  Adjust column names here ONLY if your DB differs.
  This version avoids silent failure and shows SQL errors.
*/

$sql = "
    SELECT 
        c.course_code,
        c.course_name
    FROM student_courses sc
    INNER JOIN courses c 
        ON c.course_id = sc.course_id
    WHERE sc.student_id = ?
    ORDER BY c.course_code ASC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo "<tr><td colspan='2' style='color:red;'>SQL PREPARE FAILED</td></tr>";
    echo "<tr><td colspan='2'>" . $conn->error . "</td></tr>";
    exit();
}

$stmt->bind_param("i", $student_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo "<tr><td colspan='2'>No courses enrolled</td></tr>";
    exit();
}

while ($row = $res->fetch_assoc()) {
    echo "<tr>
            <td>{$row['course_code']}</td>
            <td>{$row['course_name']}</td>
          </tr>";
}
