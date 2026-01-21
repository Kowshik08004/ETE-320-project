<?php
session_start();
if (!isset($_SESSION['Admin-name'])) {
    header("location: login.php");
    exit();
}
require 'connectDB.php';
require 'vendor/autoload.php';

use Dompdf\Dompdf;

/* -------- FILTERS -------- */
$department_id = $_GET['department_id'] ?? '';
$batch         = $_GET['batch'] ?? '';
$level         = $_GET['level'] ?? '';
$term          = $_GET['term'] ?? '';
$course_id     = $_GET['course_id'] ?? '';

/* -------- META INFO -------- */
$deptName = $courseCode = $courseName = "All";

/* -------- GENERATE FILENAME -------- */
$filenameParts = [];
if ($department_id) {
    $r = $conn->query("SELECT department_name FROM departments WHERE department_id=$department_id")->fetch_assoc();
    if ($r) {
        $deptName = $r['department_name'];
        $filenameParts[] = preg_replace('/\s+/', '', $deptName);
    }
}
if ($level) $filenameParts[] = "L".$level;
if ($term) $filenameParts[] = "T".$term;
if ($course_id) {
    $r = $conn->query("SELECT course_code, course_name FROM courses WHERE course_id=$course_id")->fetch_assoc();
    if ($r) {
        $courseCode = $r['course_code'];
        $courseName = $r['course_name'];
        $filenameParts[] = $courseCode;
    }
}
$filename = "Attendance_" . (!empty($filenameParts) ? implode("_", $filenameParts) . "_" : "") . date('Y-m-d_H-i-s') . ".pdf";

// Gather rows
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
$sql .= " ORDER BY s.roll_no, c.course_code";

$res = $conn->query($sql);
$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
}

// Build HTML for PDF
$html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { text-align: center; font-weight: bold; font-size: 20px; margin-bottom: 10px; }
        .meta { text-align: center; font-size: 11px; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background-color: #f2f2f2; border: 1px solid #000; padding: 8px; text-align: left; font-weight: bold; }
        td { border: 1px solid #000; padding: 8px; }
        tr:nth-child(even) { background-color: #f9f9f9; }
    </style>
</head>
<body>
    <h1>Attendance Summary</h1>
    <div class="meta">
        <strong>Department:</strong> ' . htmlspecialchars($deptName) . ' | 
        <strong>Level:</strong> ' . htmlspecialchars($level ?: 'All') . ' | 
        <strong>Term:</strong> ' . htmlspecialchars($term ?: 'All') . ' | 
        <strong>Course:</strong> ' . htmlspecialchars($courseCode) . '
    </div>
    <table>
        <thead>
            <tr>
                <th>Student</th>
                <th>Course</th>
                <th>Attended</th>
                <th>Total</th>
                <th>Percentage</th>
            </tr>
        </thead>
        <tbody>';

foreach ($rows as $r) {
    $html .= '<tr>
                <td>' . htmlspecialchars($r['name']) . '</td>
                <td>' . htmlspecialchars($r['course_code']) . '</td>
                <td>' . $r['attended'] . '</td>
                <td>' . $r['total_sessions'] . '</td>
                <td>' . $r['percentage'] . '%</td>
            </tr>';
}

$html .= '
        </tbody>
    </table>
</body>
</html>';

// Generate PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Output PDF
$dompdf->stream($filename, array("Attachment" => 1));
exit();
