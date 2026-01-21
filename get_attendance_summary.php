<?php
require 'connectDB.php';

$department_id = $_GET['department_id'];
$level = $_GET['level'];
$term  = $_GET['term'];

$sql = "
SELECT
    s.student_id,
    s.roll_no,
    s.name,
    c.course_code,
    c.course_name,
    c.credit,
    cs.session_id,
    cs.start_time,
    cs.end_time,
    COUNT(a.attendance_id) AS attended_units,
    a.status

FROM students s

JOIN student_courses sc
    ON sc.student_id = s.student_id
    AND sc.status = 'active'

JOIN courses c
    ON c.course_id = sc.course_id

LEFT JOIN course_sessions cs
    ON cs.course_id = c.course_id

LEFT JOIN attendance a
    ON a.session_id = cs.session_id
    AND a.student_id = s.student_id
    AND a.status IN ('present','late')

WHERE
    s.department_id = ?
    AND s.level = ?
    AND s.term = ?

GROUP BY
    s.student_id, c.course_id, cs.session_id, a.attendance_id

ORDER BY
    s.roll_no, c.course_code, cs.session_id
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $department_id, $level, $term);
$stmt->execute();
$result = $stmt->get_result();

// Process results to calculate units
$data = [];
$session_units = []; // Cache unit counts per session

while ($row = $result->fetch_assoc()) {
    $key = $row['student_id'] . '_' . $row['course_code'];
    
    if (!isset($data[$key])) {
        $data[$key] = [
            'student_id' => $row['student_id'],
            'roll_no' => $row['roll_no'],
            'name' => $row['name'],
            'course_code' => $row['course_code'],
            'course_name' => $row['course_name'],
            'credit' => $row['credit'],
            'total_sessions' => 0,
            'attended_sessions' => 0,
            'sessions' => []
        ];
    }
    
    if ($row['session_id'] && !in_array($row['session_id'], $data[$key]['sessions'])) {
        $data[$key]['sessions'][] = $row['session_id'];
        
        // Calculate units for this session
        if (!isset($session_units[$row['session_id']])) {
            $duration_minutes = (strtotime($row['end_time']) - strtotime($row['start_time'])) / 60;
            $session_units[$row['session_id']] = max(1, ceil($duration_minutes / 50));
        }
        
        $num_units = $session_units[$row['session_id']];
        $data[$key]['total_sessions'] += $num_units;
        
        // If student attended this session, add attended units
        if ($row['attended_units'] > 0 && $row['status'] && in_array($row['status'], ['present', 'late'])) {
            $data[$key]['attended_sessions'] += $num_units;
        }
    }
}

// Convert to array and calculate percentage
$result_data = [];
foreach ($data as $row) {
    $total = max(1, $row['total_sessions']);
    $row['attendance_percent'] = round(($row['attended_sessions'] / $total) * 100, 2);
    $row['expected_sessions'] = $row['credit'] * 13;
    unset($row['sessions']);
    $result_data[] = $row;
}

echo json_encode($result_data);