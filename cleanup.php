<?php
require 'connectDB.php';
$conn->query('DELETE FROM attendance WHERE session_id >= 5');
$conn->query('DELETE FROM course_sessions WHERE session_id >= 5');
echo "Cleaned up test data\n";
?>
