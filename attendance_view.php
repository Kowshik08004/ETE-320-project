<?php
require 'connectDB.php';

$session_id = $_GET['session_id'];

$res = $conn->query("
  SELECT s.roll_no, s.name, a.status
  FROM attendance a
  JOIN students s ON s.student_id = a.student_id
  WHERE a.session_id = $session_id
  ORDER BY s.roll_no
");
?>

<table border="1">
<tr>
<th>Student ID</th>
<th>Name</th>
<th>Status</th>
</tr>

<?php while ($r = $res->fetch_assoc()) { ?>
<tr>
<td><?= $r['roll_no'] ?></td>
<td><?= $r['name'] ?></td>
<td><?= strtoupper($r['status']) ?></td>
</tr>
<?php } ?>
</table>
