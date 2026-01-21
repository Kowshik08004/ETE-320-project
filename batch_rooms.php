<?php
require 'admin_guard.php';
$flash = read_flash();

$devices = [];
$q1 = $conn->query("SELECT device_uid, device_name FROM devices ORDER BY id DESC");
if ($q1) while ($r = $q1->fetch_assoc()) $devices[] = $r;

$maps = [];
$q2 = $conn->query("
  SELECT br.batch, br.device_uid, d.device_name
  FROM batch_rooms br
  LEFT JOIN devices d ON d.device_uid = br.device_uid
  ORDER BY br.batch DESC
");
if ($q2) while ($r = $q2->fetch_assoc()) $maps[] = $r;
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Batch Rooms</title>
  <link rel="stylesheet" href="css/manageusers.css">
  <style>
    .wrap{max-width:1100px;margin:0 auto;padding:18px}
    .card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,.06);overflow:hidden}
    .head{padding:14px 16px;border-bottom:1px solid #e5e7eb}
    .head h2{margin:0;font-size:18px}
    .body{padding:16px}
    .msg{padding:10px 12px;border-radius:10px;margin-bottom:12px;border:1px solid transparent}
    .success{background:#ecfdf5;color:#065f46;border-color:#a7f3d0}
    .danger{background:#fef2f2;color:#991b1b;border-color:#fecaca}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    input,select{width:100%;height:42px;padding:10px;border:1px solid #e5e7eb;border-radius:10px}
    button{height:42px;padding:0 14px;border:0;border-radius:10px;background:#0ea5a4;color:#fff;font-weight:800;cursor:pointer}
    table{width:100%;border-collapse:collapse;margin-top:14px}
    th,td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left;white-space:nowrap}
    thead th{background:#f1f5f9;color:#000;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
    a{color:#b91c1c;text-decoration:none;font-weight:800}
    @media(max-width:900px){.grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
<?php include 'header.php'; ?>
<div class="wrap">
  <div class="card">
    <div class="head"><h2>Assign Batch → Device</h2></div>
    <div class="body">

      <?php if ($flash): ?>
        <div class="msg <?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['msg']) ?></div>
      <?php endif; ?>

      <form class="grid" method="POST" action="batch_room_save.php">
        <div>
          <label style="font-weight:800;font-size:13px">Batch</label>
          <input name="batch" placeholder="e.g. 21" required>
        </div>

        <div>
          <label style="font-weight:800;font-size:13px">Device</label>
          <select name="device_uid" required>
            <option value="">-- Select Device --</option>
            <?php foreach ($devices as $d): ?>
              <option value="<?= htmlspecialchars($d['device_uid']) ?>">
                <?= htmlspecialchars($d['device_name']) ?> — <?= htmlspecialchars($d['device_uid']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="grid-column:1/-1">
          <button type="submit">Assign</button>
        </div>
      </form>

      <table>
        <thead><tr><th>Batch</th><th>Device</th><th>UID</th><th>Remove</th></tr></thead>
        <tbody>
          <?php foreach ($maps as $m): ?>
            <tr>
              <td><?= htmlspecialchars($m['batch']) ?></td>
              <td><?= htmlspecialchars($m['device_name'] ?? '—') ?></td>
              <td><?= htmlspecialchars($m['device_uid']) ?></td>
              <td><a href="batch_room_delete.php?batch=<?= urlencode($m['batch']) ?>" onclick="return confirm('Remove mapping?')">Delete</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

    </div>
  </div>
</div>
</body>
</html>
