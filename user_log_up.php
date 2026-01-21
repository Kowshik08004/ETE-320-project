<?php
require 'connectDB.php';
date_default_timezone_set('Asia/Dhaka');
?>

<div class="table-responsive" style="max-height: 500px;"> 
<table class="table">
  <thead class="table-primary">
    <tr>
      <th>ID</th>
      <th>Name</th>
      <th>Student ID</th>
      <th>Card UID</th>
      <th>Device</th>
      <th>Date</th>
      <th>Time In</th>
      <th>Time Out</th>
    </tr>
  </thead>
  <tbody class="table-secondary">

<?php
// ---------------- DEFAULT: SHOW ALL LOGS ----------------
$where = "1=1";

// ---------------- FILTERS ----------------
if (isset($_POST['log_date'])) {

    if (!empty($_POST['date_sel_start'])) {
        $where = "ul.checkindate='".$_POST['date_sel_start']."'";
    }

    if (!empty($_POST['date_sel_start']) && !empty($_POST['date_sel_end'])) {
        $where = "ul.checkindate BETWEEN '".$_POST['date_sel_start']."' AND '".$_POST['date_sel_end']."'";
    }

    if (!empty($_POST['time_sel_start']) && !empty($_POST['time_sel_end'])) {
        if ($_POST['time_sel'] === "Time_in") {
            $where .= " AND ul.timein BETWEEN '".$_POST['time_sel_start']."' AND '".$_POST['time_sel_end']."'";
        } else {
            $where .= " AND ul.timeout BETWEEN '".$_POST['time_sel_start']."' AND '".$_POST['time_sel_end']."'";
        }
    }

    if (!empty($_POST['card_sel']) && $_POST['card_sel'] != 0) {
        $where .= " AND ul.card_uid='".$_POST['card_sel']."'";
    }

    if (!empty($_POST['dev_uid']) && $_POST['dev_uid'] != 0) {
        $where .= " AND ul.device_uid='".$_POST['dev_uid']."'";
    }
}

// ---------------- QUERY ----------------
$sql = "
SELECT
    ul.id,
    ul.card_uid,
    ul.device_dep,
    ul.checkindate,
    ul.timein,
    ul.timeout,
    ul.serialnumber AS student_id,
    s.name AS student_name
FROM users_logs ul
LEFT JOIN students s
    ON s.student_id = ul.serialnumber
WHERE $where
ORDER BY ul.id DESC
";

$res = mysqli_query($conn, $sql);

if ($res && mysqli_num_rows($res) > 0) {
    while ($row = mysqli_fetch_assoc($res)) {
        echo "
        <tr>
          <td>{$row['id']}</td>
          <td>".($row['student_name'] ?? 'Unknown')."</td>
          <td>{$row['student_id']}</td>
          <td>{$row['card_uid']}</td>
          <td>{$row['device_dep']}</td>
          <td>{$row['checkindate']}</td>
          <td>{$row['timein']}</td>
          <td>{$row['timeout']}</td>
        </tr>";
    }
} else {
    echo "<tr><td colspan='8'>No logs found</td></tr>";
}
?>

  </tbody>
</table>
</div>
