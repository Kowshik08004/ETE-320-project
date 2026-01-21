<?php
session_start();
if (!isset($_SESSION['Admin-name'])) exit();
require 'connectDB.php';

$department_id = $_GET['department_id'] ?? '';
$batch         = $_GET['batch'] ?? '';
$level         = $_GET['level'] ?? '';
$term          = $_GET['term'] ?? '';
$course_id     = $_GET['course_id'] ?? '';

/* -------- GENERATE FILENAME -------- */
$filenameParts = [];
if ($department_id) {
    $dept_result = $conn->query("SELECT department_name FROM departments WHERE department_id=$department_id");
    if ($dept_result) {
        $dept_data = $dept_result->fetch_assoc();
        $filenameParts[] = str_replace(' ', '', $dept_data['department_name']);
    }
}
if ($level) $filenameParts[] = "L".$level;
if ($term) $filenameParts[] = "T".$term;
if ($course_id) {
    $course_result = $conn->query("SELECT course_code FROM courses WHERE course_id=$course_id");
    if ($course_result) {
        $course_data = $course_result->fetch_assoc();
        $filenameParts[] = $course_data['course_code'];
    }
}
$filename = "Attendance_" . (!empty($filenameParts) ? implode("_", $filenameParts) . "_" : "") . date('Y-m-d_H-i-s') . ".xls";

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=$filename");

echo "Attendance Summary\n";
echo "Department:\t$department_id\tBatch:\t$batch\tLevel:\t$level\tTerm:\t$term\tCourse:\t$course_id\n\n";

echo "Student\tCourse\tAttended\tTotal\tPercentage\n";

$sql = "
SELECT s.name, c.course_code, v.attended, v.total_sessions, v.percentage
FROM attendance_summary v
JOIN students s ON s.student_id = v.student_id
JOIN courses c ON c.course_id = v.course_id
WHERE 1=1
";

if ($department_id) $sql .= " AND s.department_id=$department_id";
if ($batch)         $sql .= " AND s.batch='$batch'";
if ($level)         $sql .= " AND s.level=$level";
if ($term)          $sql .= " AND s.term=$term";
if ($course_id)     $sql .= " AND c.course_id=$course_id";

$res = $conn->query($sql);
while ($r = $res->fetch_assoc()) {
    echo "{$r['name']}\t{$r['course_code']}\t{$r['attended']}\t{$r['total_sessions']}\t{$r['percentage']}%\n";
}