<?php
require 'admin_guard.php';

$rows = [];
$q = $conn->query("
  SELECT
    d.device_uid,
    d.device_name,
    d.device_dep,
    cr.room_name,
    br.batch
  FROM devices d
  LEFT JOIN room_devices rd ON rd.device_uid = d.device_uid
  LEFT JOIN class_rooms cr ON cr.room_id = rd.room_id
  LEFT JOIN batch_rooms br ON br.device_uid = d.device_uid
  ORDER BY d.id DESC
");
if ($q) while ($r = $q->fetch_assoc()) $rows[] = $r;
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Device Relations</title>
  <link rel="stylesheet" href="css/manageusers.css">
  <style>
    .wrap{max-width:1100px;margin:0 auto;padding:18px}
    .card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,.06);overflow:hidden}
    .head{padding:14px 16px;border-bottom:1px solid #e5e7eb}
    table{width:100%;border-collapse:collapse}
    th,td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left;white-space:nowrap}
    thead th{background:#f1f5f9;color:#000;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
  </style>
</head>
<body>
<?php include 'header.php'; ?>
<div class="wrap">
  <div class="card">
    <div class="head"><h2 style="margin:0;font-size:18px">Device ↔ Room ↔ Batch</h2></div>
    <div style="padding:16px">
      <table>
        <thead><tr><th>Device</th><th>UID</th><th>Dept</th><th>Room</th><th>Batch</th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['device_name']) ?></td>
              <td><?= htmlspecialchars($r['device_uid']) ?></td>
              <td><?= htmlspecialchars($r['device_dep']) ?></td>
              <td><?= htmlspecialchars($r['room_name'] ?? '—') ?></td>
              <td><?= htmlspecialchars($r['batch'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
