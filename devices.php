<?php
session_start();
if (!isset($_SESSION['Admin-name'])) {
  header("location: login.php");
  exit();
}
require 'connectDB.php';

/* ---------------- Flash helper ---------------- */
function flash($msg, $type = "info", $uid = null)
{
  $_SESSION['flash'] = ["msg" => $msg, "type" => $type, "uid" => $uid];
}
function read_flash()
{
  if (!isset($_SESSION['flash'])) return null;
  $f = $_SESSION['flash'];
  unset($_SESSION['flash']);
  return $f;
}

/* ---------------- Utils ---------------- */
function h($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function uid16(): string
{
  return bin2hex(random_bytes(8)); // 16 hex chars
}

function device_exists(mysqli $conn, string $uid): bool
{
  $st = $conn->prepare("SELECT 1 FROM devices WHERE device_uid=? LIMIT 1");
  $st->bind_param("s", $uid);
  $st->execute();
  $r = $st->get_result();
  $st->close();
  return ($r && $r->num_rows > 0);
}
function room_exists(mysqli $conn, int $room_id): bool
{
  $st = $conn->prepare("SELECT 1 FROM class_rooms WHERE room_id=? LIMIT 1");
  $st->bind_param("i", $room_id);
  $st->execute();
  $r = $st->get_result();
  $st->close();
  return ($r && $r->num_rows > 0);
}

/* ---------------- Tabs ---------------- */
$tab = $_GET['tab'] ?? 'devices';
$allowed_tabs = ['devices', 'room_devices', 'batch_rooms', 'relations', 'rooms'];
if (!in_array($tab, $allowed_tabs, true)) $tab = 'devices';

/* ---------------- Actions ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  /* ====== ADD DEVICE (UID auto-generated + MODE selectable) ====== */
  if ($action === 'save_device') {
    $name = trim($_POST['device_name'] ?? '');
    $dep  = trim($_POST['device_dep'] ?? '');
    $mode = (int)($_POST['device_mode'] ?? 1);
    $mode = ($mode === 0) ? 0 : 1;

    if ($name === '' || $dep === '') {
      flash("Device name and department are required.", "danger");
      header("Location: " . $_SERVER['PHP_SELF'] . "?tab=devices");
      exit();
    }

    $name = substr($name, 0, 50);
    $dep  = substr($dep, 0, 20);

    // Auto-generate unique UID
    $uid = uid16();
    for ($i = 0; $i < 6; $i++) {
      if (!device_exists($conn, $uid)) break;
      $uid = uid16();
    }
    if (device_exists($conn, $uid)) {
      flash("Could not generate a unique device UID. Try again.", "danger");
      header("Location: " . $_SERVER['PHP_SELF'] . "?tab=devices");
      exit();
    }

    $st = $conn->prepare("
      INSERT INTO devices (device_name, device_dep, device_uid, device_date, device_mode)
      VALUES (?, ?, ?, CURDATE(), ?)
    ");
    if (!$st) {
      flash("DB prepare failed: " . $conn->error, "danger");
      header("Location: " . $_SERVER['PHP_SELF'] . "?tab=devices");
      exit();
    }

    $st->bind_param("sssi", $name, $dep, $uid, $mode);
    $ok  = $st->execute();
    $err = $st->error;
    $st->close();

    if ($ok) flash("Device created successfully.", "success", $uid);
    else     flash("Insert failed: " . $err, "danger");

    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=devices");
    exit();
  }

  /* ====== UPDATE DEVICE MODE (0=enroll, 1=attendance) ====== */
  if ($action === 'update_device_mode') {
    $id   = (int)($_POST['id'] ?? 0);
    $mode = (int)($_POST['device_mode'] ?? 1);
    $mode = ($mode === 0) ? 0 : 1;

    if ($id <= 0) {
      flash("Invalid device id.", "danger");
      header("Location: " . $_SERVER['PHP_SELF'] . "?tab=devices");
      exit();
    }

    $st = $conn->prepare("UPDATE devices SET device_mode=? WHERE id=?");
    if (!$st) {
      flash("Update prepare failed: " . $conn->error, "danger");
      header("Location: " . $_SERVER['PHP_SELF'] . "?tab=devices");
      exit();
    }

    $st->bind_param("ii", $mode, $id);
    $ok  = $st->execute();
    $err = $st->error;
    $st->close();

    flash($ok ? "Device mode updated." : "Mode update failed: " . $err, $ok ? "success" : "danger");
    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=devices");
    exit();
  }

  /* ====== DELETE DEVICE (also deletes mappings) ====== */
  if ($action === 'delete_device') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
      header("Location: " . $_SERVER['PHP_SELF'] . "?tab=devices");
      exit();
    }

    // Find UID for cleanup
    $g = $conn->prepare("SELECT device_uid FROM devices WHERE id=? LIMIT 1");
    $g->bind_param("i", $id);
    $g->execute();
    $r = $g->get_result();
    $uid = ($r && $r->num_rows) ? (string)$r->fetch_assoc()['device_uid'] : '';
    $g->close();

    if ($uid !== '') {
      $d1 = $conn->prepare("DELETE FROM room_devices WHERE device_uid=?");
      if ($d1) { $d1->bind_param("s", $uid); $d1->execute(); $d1->close(); }

      $d2 = $conn->prepare("DELETE FROM batch_rooms WHERE device_uid=?");
      if ($d2) { $d2->bind_param("s", $uid); $d2->execute(); $d2->close(); }
    }

    $del = $conn->prepare("DELETE FROM devices WHERE id=?");
    $del->bind_param("i", $id);
    $ok  = $del->execute();
    $err = $del->error;
    $del->close();

    flash($ok ? "Device deleted." : "Delete failed: " . $err, $ok ? "success" : "danger");
    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=devices");
    exit();
  }

  /* ====== ASSIGN DEVICE -> ROOM ====== */
  if ($action === 'assign_device_room') {
    $uid     = trim($_POST['device_uid'] ?? '');
    $room_id = (int)($_POST['room_id'] ?? 0);

    if ($uid === '' || $room_id <= 0) {
      flash("Select a device and a room.", "danger");
      header("Location: " . $_SERVER['PHP_SELF'] . "?tab=room_devices");
      exit();
    }

    $uid = substr($uid, 0, 50);

    if (!device_exists($conn, $uid)) {
      flash("Device UID not found in devices table.", "danger");
      header("Location: " . $_SERVER['PHP_SELF'] . "?tab=room_devices");
      exit();
    }
    if (!room_exists($conn, $room_id)) {
      flash("Room not found. Create it in rooms.php first.", "danger");
      header("Location: " . $_SERVER['PHP_SELF'] . "?tab=room_devices");
      exit();
    }

    $st = $conn->prepare("
      INSERT INTO room_devices (device_uid, room_id)
      VALUES (?, ?)
      ON DUPLICATE KEY UPDATE room_id=VALUES(room_id)
    ");
    if (!$st) {
      flash("Assign prepare failed: " . $conn->error, "danger");
      header("Location: " . $_SERVER['PHP_SELF'] . "?tab=room_devices");
      exit();
    }

    $st->bind_param("si", $uid, $room_id);
    $ok  = $st->execute();
    $err = $st->error;
    $st->close();

    flash($ok ? "Device assigned to room." : "Assign failed: " . $err, $ok ? "success" : "danger");
    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=room_devices");
    exit();
  }

  /* ====== UNASSIGN DEVICE FROM ROOM ====== */
  if ($action === 'unassign_device_room') {
    $uid = trim($_POST['device_uid'] ?? '');
    if ($uid === '') {
      header("Location: " . $_SERVER['PHP_SELF'] . "?tab=room_devices");
      exit();
    }
    $uid = substr($uid, 0, 50);

    $st = $conn->prepare("DELETE FROM room_devices WHERE device_uid=?");
    if (!$st) {
      flash("Unassign prepare failed: " . $conn->error, "danger");
      header("Location: " . $_SERVER['PHP_SELF'] . "?tab=room_devices");
      exit();
    }

    $st->bind_param("s", $uid);
    $ok  = $st->execute();
    $err = $st->error;
    $st->close();

    flash($ok ? "Room mapping removed." : "Unassign failed: " . $err, $ok ? "success" : "danger");
    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=room_devices");
    exit();
  }

  /* ====== ASSIGN BATCH -> DEVICE (respects UNIQUE(device_uid)) ====== */
  if ($action === 'assign_batch_device') {
    $batch = trim($_POST['batch'] ?? '');
    $uid   = trim($_POST['device_uid'] ?? '');

    if ($batch === '' || $uid === '') {
      flash("Batch and device are required.", "danger");
      header("Location: " . $_SERVER['PHP_SELF'] . "?tab=batch_rooms");
      exit();
    }

    $uid = substr($uid, 0, 20); // batch_rooms.device_uid VARCHAR(20)
    if (!device_exists($conn, $uid)) {
      flash("Device UID not found in devices table.", "danger");
      header("Location: " . $_SERVER['PHP_SELF'] . "?tab=batch_rooms");
      exit();
    }

    // Device can belong to only one batch (UNIQUE device_uid)
    $chk = $conn->prepare("SELECT batch FROM batch_rooms WHERE device_uid=? LIMIT 1");
    if ($chk) {
      $chk->bind_param("s", $uid);
      $chk->execute();
      $r = $chk->get_result();
      $chk->close();

      if ($r && $r->num_rows > 0) {
        $row = $r->fetch_assoc();
        if ((string)$row['batch'] !== (string)$batch) {
          flash("This device is already assigned to batch " . $row['batch'] . ". Delete that mapping first.", "danger");
          header("Location: " . $_SERVER['PHP_SELF'] . "?tab=batch_rooms");
          exit();
        }
      }
    }

    $b = (int)$batch;

    // Upsert by batch (PK=batch)
    $st = $conn->prepare("
      INSERT INTO batch_rooms (batch, device_uid)
      VALUES (?, ?)
      ON DUPLICATE KEY UPDATE device_uid=VALUES(device_uid)
    ");
    if (!$st) {
      flash("Batch assign prepare failed: " . $conn->error, "danger");
      header("Location: " . $_SERVER['PHP_SELF'] . "?tab=batch_rooms");
      exit();
    }

    $st->bind_param("is", $b, $uid);
    $ok  = $st->execute();
    $err = $st->error;
    $st->close();

    flash($ok ? "Batch assigned to device." : "Assign failed: " . $err, $ok ? "success" : "danger");
    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=batch_rooms");
    exit();
  }

  /* ====== DELETE BATCH MAPPING ====== */
  if ($action === 'delete_batch_map') {
    $batch = (int)($_POST['batch'] ?? 0);
    if ($batch <= 0) {
      header("Location: " . $_SERVER['PHP_SELF'] . "?tab=batch_rooms");
      exit();
    }

    $st = $conn->prepare("DELETE FROM batch_rooms WHERE batch=?");
    if (!$st) {
      flash("Delete prepare failed: " . $conn->error, "danger");
      header("Location: " . $_SERVER['PHP_SELF'] . "?tab=batch_rooms");
      exit();
    }

    $st->bind_param("i", $batch);
    $ok  = $st->execute();
    $err = $st->error;
    $st->close();

    flash($ok ? "Batch mapping deleted." : "Delete failed: " . $err, $ok ? "success" : "danger");
    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=batch_rooms");
    exit();
  }

  flash("Unknown action.", "danger");
  header("Location: " . $_SERVER['PHP_SELF'] . "?tab=" . $tab);
  exit();
}

/* ---------------- Data load for UI ---------------- */
$flash = read_flash();

// devices list
$devices = [];
$q = $conn->query("SELECT id, device_name, device_dep, device_uid, device_date, device_mode FROM devices ORDER BY id DESC");
if ($q) while ($r = $q->fetch_assoc()) $devices[] = $r;

// rooms
$rooms = [];
$q = $conn->query("SELECT room_id, room_name FROM class_rooms ORDER BY room_name");
if ($q) while ($r = $q->fetch_assoc()) $rooms[] = $r;

// device->room map rows
$room_maps = [];
$q = $conn->query("
  SELECT d.device_name, d.device_uid, cr.room_name, rd.room_id
  FROM devices d
  LEFT JOIN room_devices rd ON rd.device_uid = d.device_uid
  LEFT JOIN class_rooms cr ON cr.room_id = rd.room_id
  ORDER BY d.id DESC
");
if ($q) while ($r = $q->fetch_assoc()) $room_maps[] = $r;

// batch->device map rows
$batch_maps = [];
$q = $conn->query("
  SELECT br.batch, br.device_uid, d.device_name
  FROM batch_rooms br
  LEFT JOIN devices d ON d.device_uid = br.device_uid
  ORDER BY br.batch DESC
");
if ($q) while ($r = $q->fetch_assoc()) $batch_maps[] = $r;

// relations view rows
$relations = [];
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
if ($q) while ($r = $q->fetch_assoc()) $relations[] = $r;
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Infrastructure</title>
  <link rel="stylesheet" href="css/manageusers.css">
  <style>
    :root{--card:#fff;--text:#0f172a;--muted:#64748b;--line:#e5e7eb;--shadow:0 10px 30px rgba(0,0,0,.10);--radius:14px;}
    .wrap{max-width:1200px;margin:0 auto;padding:20px 16px 40px;}
    .card{background:var(--card);border:1px solid rgba(0,0,0,.06);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;}
    .head{padding:16px 18px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;gap:12px;}
    .head h2{margin:0;font-size:18px;color:var(--text);}
    .tabs{display:flex;flex-wrap:wrap;gap:8px;}
    .tab{display:inline-block;padding:8px 12px;border-radius:999px;border:1px solid var(--line);text-decoration:none;font-weight:800;font-size:12px;color:#0f172a;background:#f8fafc;}
    .tab.active{background:#0ea5a4;color:#fff;border-color:#0ea5a4;}
    .body{padding:16px 18px;}
    .msg{padding:10px 12px;border-radius:12px;margin:10px 0 16px;border:1px solid transparent;font-size:14px;}
    .success{background:#ecfdf5;color:#065f46;border-color:#a7f3d0;}
    .danger{background:#fef2f2;color:#991b1b;border-color:#fecaca;}
    .info{background:#eff6ff;color:#1e40af;border-color:#bfdbfe;}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px 14px;margin-top:8px;}
    .field{display:flex;flex-direction:column;gap:6px;}
    .field label{font-size:13px;font-weight:800;color:var(--text);}
    input,select{width:100%;height:44px;padding:10px 12px;border-radius:10px;border:1px solid var(--line);outline:none;background:#fff;color:var(--text);}
    input:focus,select:focus{border-color:#7dd3fc;box-shadow:0 0 0 4px rgba(56,189,248,.20);}
    .actions{grid-column:1/-1;display:flex;justify-content:flex-end;}
    button{height:44px;padding:0 16px;border:0;border-radius:12px;background:#0ea5a4;color:#fff;font-weight:900;cursor:pointer;}
    button.dangerBtn{height:34px;padding:0 12px;border-radius:10px;background:#dc2626;color:#fff;font-weight:900;cursor:pointer;}
    .hint{color:var(--muted);font-size:12px;margin-top:6px;}
    .link{font-weight:900;text-decoration:none;color:#0b7285;}

    .table-wrap{margin-top:16px;border:1px solid var(--line);border-radius:14px;overflow:hidden;background:#fff;box-shadow:0 10px 30px rgba(0,0,0,.06);}
    .table-title{padding:12px 14px;border-bottom:1px solid var(--line);background:linear-gradient(180deg,#f8fafc,#ffffff);}
    .scroll{overflow:auto;max-height:420px;}
    table{width:100%;border-collapse:separate;border-spacing:0;min-width:980px;}
    thead th{position:sticky;top:0;z-index:2;background:#ffffff;color:#000;font-size:12px;text-transform:uppercase;letter-spacing:.06em;padding:12px 14px;text-align:left;border-bottom:1px solid var(--line);box-shadow:0 1px 0 rgba(0,0,0,.04);font-weight: 900;}
    tbody td{padding:12px 14px;border-bottom:1px solid #eef2f7;font-size:14px;color:var(--text);white-space:nowrap;background:#fff;vertical-align:middle;}
    tbody tr:hover td{background:#f8fafc;}
    tbody tr:nth-child(even) td{background:#fbfdff;}
    tbody tr:last-child td{border-bottom:0;}
    .mono{font-variant-numeric:tabular-nums;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace;}
    @media(max-width:980px){.grid{grid-template-columns:1fr}.actions{justify-content:stretch}button{width:100%}table{min-width:860px}}
  </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="wrap">
  <div class="card">
    <div class="head">
      <h2>Infrastructure (Devices • Rooms • Batch Mapping)</h2>
      <div class="tabs">
        <?php
          $tab = $_GET['tab'] ?? 'devices';
          $self = h($_SERVER['PHP_SELF']);
        ?>
        <a class="tab <?= $tab==='devices'?'active':'' ?>" href="<?= $self ?>?tab=devices">Devices</a>
        <a class="tab <?= $tab==='room_devices'?'active':'' ?>" href="<?= $self ?>?tab=room_devices">Room Devices</a>
        <a class="tab <?= $tab==='batch_rooms'?'active':'' ?>" href="<?= $self ?>?tab=batch_rooms">Batch Rooms</a>
        <a class="tab <?= $tab==='relations'?'active':'' ?>" href="<?= $self ?>?tab=relations">Relations</a>
        <a class="tab <?= $tab==='rooms'?'active':'' ?>" href="<?= $self ?>?tab=rooms">Rooms</a>
      </div>
    </div>

    <div class="body">

      <?php if ($flash): ?>
        <div class="msg <?= h($flash['type']) ?>" id="flash-msg"><?= h($flash['msg']) ?></div>
        <script>
          setTimeout(function() {
            var el = document.getElementById('flash-msg');
            if (el) { el.style.transition = 'opacity 0.7s'; el.style.opacity = 0; setTimeout(function(){el.remove();}, 800); }
          }, 2500);
        </script>

        <?php if (!empty($flash['uid'])): ?>
          <div style="margin:10px 0 14px;padding:10px 12px;border:1px dashed #94a3b8;border-radius:10px;background:#f8fafc;">
            <b>Generated Device UID</b><br>
            <code class="mono" style="font-size:14px;padding:4px 8px;background:#f1f5f9;border-radius:999px;border:1px solid #e2e8f0;display:inline-block;margin-top:6px;">
              <?= h($flash['uid']) ?>
            </code>
            <div class="hint" style="margin-top:8px;">Copy this UID and set it in your RFID device.</div>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($tab === 'devices'): ?>

        <strong>Create Device</strong>
        <div class="hint">Device UID is auto-generated. You can choose Enrollment or Attendance mode.</div>

        <form class="grid" method="POST">
          <input type="hidden" name="action" value="save_device">

          <div class="field">
            <label>Device Name</label>
            <input name="device_name" placeholder="Lecture Room 1" required>
          </div>

          <div class="field">
            <label>Department</label>
            <input name="device_dep" placeholder="ETE" required>
          </div>

          <div class="field">
            <label>Mode</label>
            <select name="device_mode" required>
              <option value="1" selected>Attendance</option>
              <option value="0">Enrollment</option>
            </select>
          </div>

          <div class="actions">
            <button type="submit">Create Device</button>
          </div>
        </form>

        <div class="table-wrap">
          <div class="table-title"><strong>Devices</strong></div>
          <div class="scroll">
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>UID</th>
                  <th>Name</th>
                  <th>Dept</th>
                  <th>Date</th>
                  <th>Mode</th>
                  <th>Change Mode</th>
                  <th>Delete</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($devices as $d): ?>
                  <tr>
                    <td class="mono"><?= (int)$d['id'] ?></td>
                    <td class="mono"><?= h($d['device_uid']) ?></td>
                    <td><?= h($d['device_name']) ?></td>
                    <td><?= h($d['device_dep']) ?></td>
                    <td class="mono"><?= h($d['device_date']) ?></td>
                    <td class="mono"><?= ((int)$d['device_mode']===0) ? 'ENROLL' : 'ATTEND' ?></td>
                    <td>
                      <form method="POST" style="display:flex; gap:8px; align-items:center;">
                        <input type="hidden" name="action" value="update_device_mode">
                        <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                        <select name="device_mode" style="height:34px; padding:6px 10px; border-radius:10px;">
                          <option value="1" <?= ((int)$d['device_mode']===1)?'selected':'' ?>>Attendance</option>
                          <option value="0" <?= ((int)$d['device_mode']===0)?'selected':'' ?>>Enrollment</option>
                        </select>
                        <button type="submit" style="height:34px; border-radius:10px; padding:0 12px;">Save</button>
                      </form>
                    </td>
                    <td>
                      <form method="POST" onsubmit="return confirm('Delete device? This also removes room/batch mappings.');">
                        <input type="hidden" name="action" value="delete_device">
                        <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                        <button class="dangerBtn" type="submit">Delete</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (count($devices) === 0): ?>
                  <tr><td colspan="8">No devices found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php elseif ($tab === 'room_devices'): ?>

        <strong>Assign Device → Room</strong>
        <div class="hint">Rooms are managed in <a class="link" href="rooms.php">rooms.php</a>. Here you only map device to room.</div>

        <form class="grid" method="POST">
          <input type="hidden" name="action" value="assign_device_room">

          <div class="field">
            <label>Device</label>
            <select name="device_uid" required>
              <option value="">-- Select Device --</option>
              <?php foreach ($devices as $d): ?>
                <option value="<?= h($d['device_uid']) ?>"><?= h($d['device_name']) ?> — <?= h($d['device_uid']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label>Room</label>
            <select name="room_id" required>
              <option value="">-- Select Room --</option>
              <?php foreach ($rooms as $r): ?>
                <option value="<?= (int)$r['room_id'] ?>"><?= h($r['room_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="actions">
            <button type="submit">Assign</button>
          </div>
        </form>

        <div class="table-wrap">
          <div class="table-title"><strong>Device → Room Mappings</strong></div>
          <div class="scroll">
            <table>
              <thead>
                <tr>
                  <th>Device</th>
                  <th>UID</th>
                  <th>Room</th>
                  <th>Unassign</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($room_maps as $m): ?>
                  <tr>
                    <td><?= h($m['device_name']) ?></td>
                    <td class="mono"><?= h($m['device_uid']) ?></td>
                    <td><?= h($m['room_name'] ?? '—') ?></td>
                    <td>
                      <?php if (!empty($m['room_id'])): ?>
                        <form method="POST" onsubmit="return confirm('Unassign device from room?');">
                          <input type="hidden" name="action" value="unassign_device_room">
                          <input type="hidden" name="device_uid" value="<?= h($m['device_uid']) ?>">
                          <button class="dangerBtn" type="submit">Unassign</button>
                        </form>
                      <?php else: ?>
                        —
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (count($room_maps) === 0): ?>
                  <tr><td colspan="4">No devices found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php elseif ($tab === 'batch_rooms'): ?>

        <strong>Assign Batch → Device</strong>
        <div class="hint">Each device can belong to only one batch (UNIQUE device_uid). Each batch maps to one device.</div>

        <form class="grid" method="POST">
          <input type="hidden" name="action" value="assign_batch_device">

          <div class="field">
            <label>Batch</label>
            <select name="batch" required>
              <option value="">-- Select Batch --</option>
              <?php for ($b = 12; $b <= 30; $b++): ?>
                <option value="<?= $b ?>"><?= $b ?></option>
              <?php endfor; ?>
            </select>
          </div>

          <div class="field">
            <label>Device</label>
            <select name="device_uid" required>
              <option value="">-- Select Device --</option>
              <?php foreach ($devices as $d): ?>
                <option value="<?= h($d['device_uid']) ?>"><?= h($d['device_name']) ?> — <?= h($d['device_uid']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="actions">
            <button type="submit">Assign</button>
          </div>
        </form>

        <div class="table-wrap">
          <div class="table-title"><strong>Batch → Device Mappings</strong></div>
          <div class="scroll">
            <table>
              <thead>
                <tr>
                  <th>Batch</th>
                  <th>Device</th>
                  <th>UID</th>
                  <th>Delete</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($batch_maps as $m): ?>
                  <tr>
                    <td class="mono"><?= h($m['batch']) ?></td>
                    <td><?= h($m['device_name'] ?? '—') ?></td>
                    <td class="mono"><?= h($m['device_uid']) ?></td>
                    <td>
                      <form method="POST" onsubmit="return confirm('Delete batch mapping?');">
                        <input type="hidden" name="action" value="delete_batch_map">
                        <input type="hidden" name="batch" value="<?= (int)$m['batch'] ?>">
                        <button class="dangerBtn" type="submit">Delete</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (count($batch_maps) === 0): ?>
                  <tr><td colspan="4">No batch mappings found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php elseif ($tab === 'rooms'): ?>

        <strong>Rooms</strong>
        <div class="hint">Manage rooms here. (This is the same as rooms.php, but loaded in the card.)</div>
        <form method="POST" action="room_save.php" class="form-row" style="margin-bottom:18px; display:flex; gap:12px; flex-wrap:wrap; align-items:end;">
          <div class="field">
            <label>Room Name</label>
            <input name="room_name" placeholder="e.g. Lecture Room 1" required>
          </div>
          <input type="hidden" name="redirect" value="devices.php?tab=rooms">
          <button type="submit" style="align-self:end; height:44px; border-radius:18px; background:#fff; color:#222; border:1.5px solid #e5e7eb; font-weight:800; box-shadow:0 2px 8px rgba(0,0,0,0.04); transition:background 0.18s, color 0.18s, border 0.18s;">Add Room</button>
        </form>
        <div class="table-wrap">
          <div class="table-title"><strong>Rooms</strong></div>
          <div class="scroll">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>Name</th>
                  <th>Edit</th>
                  <th>Delete</th>
                </tr>
              </thead>
              <tbody>
                <?php $i = 1; foreach ($rooms as $r): ?>
                  <tr>
                    <td class="mono"><?= $i++ ?></td>
                    <td><?= h($r['room_name']) ?></td>
                    <td>
                      <a href="room_edit.php?id=<?= (int)$r['room_id'] ?>&redirect=devices.php?tab=rooms" style="display:inline-block;padding:6px 14px;border-radius:8px;background:#2563eb;color:#fff;text-decoration:none;font-weight:900;">Edit</a>
                    </td>
                    <td>
                      <form method="POST" action="room_delete.php" onsubmit="return confirm('Delete room?');" style="display:inline;">
                        <input type="hidden" name="id" value="<?= (int)$r['room_id'] ?>">
                        <input type="hidden" name="redirect" value="devices.php?tab=rooms">
                        <button class="btn-del" type="submit">Delete</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (count($rooms) === 0): ?>
                  <tr><td colspan="4">No rooms found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php else: /* relations */ ?>

        <strong>Relations View</strong>
        <div class="hint">Shows device ↔ room ↔ batch in one table.</div>

        <div class="table-wrap">
          <div class="table-title"><strong>Device ↔ Room ↔ Batch</strong></div>
          <div class="scroll">
            <table>
              <thead>
                <tr>
                  <th>Device</th>
                  <th>UID</th>
                  <th>Dept</th>
                  <th>Room</th>
                  <th>Batch</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($relations as $r): ?>
                  <tr>
                    <td><?= h($r['device_name']) ?></td>
                    <td class="mono"><?= h($r['device_uid']) ?></td>
                    <td><?= h($r['device_dep']) ?></td>
                    <td><?= h($r['room_name'] ?? '—') ?></td>
                    <td class="mono"><?= h($r['batch'] ?? '—') ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (count($relations) === 0): ?>
                  <tr><td colspan="5">No devices found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php endif; ?>

    </div>
  </div>
</div>

</body>
</html>
