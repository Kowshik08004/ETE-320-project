<?php
require 'connectDB.php';

echo "=== UPDATING attendance_summary VIEW ===\n\n";

// Drop the old view
$conn->query("DROP VIEW IF EXISTS attendance_summary");
echo "✓ Dropped old view\n";

// Create new view that accounts for 10-minute units
$sql = "
CREATE VIEW attendance_summary AS
SELECT 
    s.student_id,
    s.name,
    c.course_id,
    c.course_code,
    c.course_name,
    COUNT(CASE WHEN a.status IN ('present', 'late') THEN 1 END) AS attended,
    COALESCE(
        SUM(
            CEILING(
                (EXTRACT(HOUR FROM TIMEDIFF(cs.end_time, cs.start_time)) * 60 + 
                 EXTRACT(MINUTE FROM TIMEDIFF(cs.end_time, cs.start_time))) / 10
            )
        ), 0
    ) AS total_sessions,
    CASE 
        WHEN COALESCE(SUM(CEILING(
            (EXTRACT(HOUR FROM TIMEDIFF(cs.end_time, cs.start_time)) * 60 + 
             EXTRACT(MINUTE FROM TIMEDIFF(cs.end_time, cs.start_time))) / 10
        )), 0) = 0 THEN 0
        ELSE ROUND(
            COUNT(CASE WHEN a.status IN ('present', 'late') THEN 1 END) / 
            COALESCE(SUM(CEILING(
                (EXTRACT(HOUR FROM TIMEDIFF(cs.end_time, cs.start_time)) * 60 + 
                 EXTRACT(MINUTE FROM TIMEDIFF(cs.end_time, cs.start_time))) / 10
            )), 0) * 100,
            2
        )
    END AS percentage
FROM students s
JOIN student_courses sc ON (sc.student_id = s.student_id AND sc.status = 'active')
JOIN courses c ON (c.course_id = sc.course_id)
LEFT JOIN course_sessions cs ON (cs.course_id = c.course_id AND cs.status = 'closed')
LEFT JOIN attendance a ON (a.student_id = s.student_id AND a.session_id = cs.session_id AND a.status IN ('present', 'late'))
GROUP BY s.student_id, c.course_id
";

if ($conn->query($sql)) {
    echo "✓ Created new attendance_summary view with unit support\n\n";
    
    // Show the new view definition
    $result = $conn->query("SHOW CREATE VIEW attendance_summary");
    $row = $result->fetch_assoc();
    echo "New view created:\n";
    echo $row['Create View'] . "\n";
} else {
    echo "✗ Error creating view: " . $conn->error . "\n";
    exit(1);
}

echo "\n✓ View update complete!\n";
?>
