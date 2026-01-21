<?php
session_start();
if (!isset($_SESSION['Admin-name'])) {
  header("location: login.php");
  exit();
}

require 'connectDB.php';

/* ---------------- Flash helper ---------------- */
function flash($msg, $type = "info") {
  $_SESSION['flash'] = ["msg" => $msg, "type" => $type];
}
function read_flash() {
  if (!isset($_SESSION['flash'])) return null;
  $f = $_SESSION['flash'];
  unset($_SESSION['flash']);
  return $f;
}

/* ---------------- Common validation ---------------- */
function is_valid_time($t) { return preg_match('/^\d{2}:\d{2}$/', $t); }
function is_valid_date($d) { return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d); }
function time_less($a, $b) { return strtotime($a) < strtotime($b); }

/* ---------------- Status refresh ---------------- */
function refresh_session_status(mysqli $conn): void {
  // Sessions open 10 minutes before start_time to allow early arrivals
  $conn->query("
    UPDATE course_sessions
    SET status = CASE
      WHEN session_date = CURDATE()
       AND CURTIME() >= SUBTIME(start_time, '00:10:00')
       AND CURTIME() <= end_time
      THEN 'scheduled'
      ELSE 'closed'
    END
    WHERE status <> 'attendance_generated'
  ");
}

/* ---------------- Ensure routine table exists ---------------- */
function ensure_routine_table(mysqli $conn): void {
  $sql = "
    CREATE TABLE IF NOT EXISTS course_session_routines (
      routine_id INT AUTO_INCREMENT PRIMARY KEY,
      course_id INT NOT NULL,
      room_id INT NOT NULL,
      day_of_week TINYINT NOT NULL,
      start_time TIME NOT NULL,
      end_time TIME NOT NULL,
      grace_minutes INT DEFAULT 10,
      start_date DATE DEFAULT NULL,
      end_date DATE DEFAULT NULL,
      is_active TINYINT(1) DEFAULT 1,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX idx_dow (day_of_week),
      INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
  ";
  $conn->query($sql);
}

/* ---------------- Overlap helper ---------------- */
function room_overlap_exists(mysqli $conn, int $room_id, string $date, string $start_time, string $end_time): bool {
  $check = $conn->prepare("
    SELECT session_id
    FROM course_sessions
    WHERE room_id = ?
      AND session_date = ?
      AND status <> 'attendance_generated'
      AND (? < ADDTIME(end_time, SEC_TO_TIME(COALESCE(grace_minutes,10)*60)))
      AND (? > start_time)
    LIMIT 1
  ");
  if (!$check) return true;
  $check->bind_param("isss", $room_id, $date, $start_time, $end_time);
  $check->execute();
  $res = $check->get_result();
  $has = $res && $res->num_rows > 0;
  $check->close();
  return $has;
}

/* ---------------- Insert helper ---------------- */
function insert_course_session(mysqli $conn, int $course_id, int $room_id, string $date, string $start_time, string $end_time, int $grace_minutes, bool $refreshStatus = true): array {
  if (room_overlap_exists($conn, $room_id, $date, $start_time, $end_time)) {
    return [false, "Overlapping session exists in this room for this date/time."];
  }

  $status = 'closed';
  $stmt = $conn->prepare("
    INSERT INTO course_sessions (course_id, room_id, session_date, start_time, end_time, grace_minutes, status)
    VALUES (?, ?, ?, ?, ?, ?, ?)
  ");
  if (!$stmt) {
    return [false, "DB prepare failed (insert): " . $conn->error];
  }

  $stmt->bind_param("iisssis", $course_id, $room_id, $date, $start_time, $end_time, $grace_minutes, $status);
  $ok = $stmt->execute();
  $err = $stmt->error;
  $stmt->close();

  if (!$ok) return [false, "Failed to create session: " . $err];
  if ($refreshStatus) refresh_session_status($conn);
  return [true, "Session created successfully (saved to course_sessions)."];
}

/* ---------------- Routine generator ---------------- */
function generate_sessions_from_routines(mysqli $conn, int $daysAhead): array {
  $created = 0;
  $skippedOverlap = 0;
  $skippedRange = 0;
  $errors = 0;

  $routines = [];
  $q = $conn->query("SELECT * FROM course_session_routines WHERE is_active = 1");
  if ($q) while ($row = $q->fetch_assoc()) $routines[] = $row;

  if (empty($routines)) return [0,0,0,0];

  $today = new DateTime('today');

  for ($i = 0; $i < $daysAhead; $i++) {
    $dt = (clone $today)->modify("+{$i} day");
    $dateStr = $dt->format('Y-m-d');
    $dow = (int)$dt->format('w');

    foreach ($routines as $r) {
      if ((int)$r['day_of_week'] !== $dow) continue;
      if ($r['start_date'] && $dateStr < $r['start_date']) { $skippedRange++; continue; }
      if ($r['end_date'] && $dateStr > $r['end_date']) { $skippedRange++; continue; }

      // Avoid duplicate same course/time/date
      $dup = $conn->prepare("SELECT session_id FROM course_sessions WHERE course_id = ? AND session_date = ? AND start_time = ? LIMIT 1");
      if (!$dup) { $errors++; continue; }
      $dup->bind_param("iss", $r['course_id'], $dateStr, $r['start_time']);
      $dup->execute();
      $existing = $dup->get_result();
      $hasDup = $existing && $existing->num_rows > 0;
      $dup->close();
      if ($hasDup) { $skippedOverlap++; continue; }

      [$ok, $msg] = insert_course_session(
        $conn,
        (int)$r['course_id'],
        (int)$r['room_id'],
        $dateStr,
        $r['start_time'],
        $r['end_time'],
        (int)($r['grace_minutes'] ?? 10),
        false
      );

      if ($ok) {
        $created++;
      } else {
        if (strpos($msg, 'Overlap') !== false) {
          $skippedOverlap++;
        } else {
          $errors++;
        }
      }
    }
  }

  refresh_session_status($conn);
  return [$created, $skippedOverlap, $skippedRange, $errors];
}

$dowLabels = [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

ensure_routine_table($conn);

/* ---------------- Load Rooms ---------------- */
$rooms = [];
$qRooms = $conn->query("SELECT room_id, room_name FROM class_rooms ORDER BY room_name");
if ($qRooms) {
  while ($row = $qRooms->fetch_assoc()) $rooms[] = $row;
}

/* ---------------- Load Courses ---------------- */
$courses = [];
$q1 = $conn->query("SELECT course_id, course_code, course_name FROM courses ORDER BY course_code");
if ($q1) while ($row = $q1->fetch_assoc()) $courses[] = $row;

/* ---------------- POST handler ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'create_routine') {
    $course_id     = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
    $room_id       = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
    $day_of_week   = isset($_POST['day_of_week']) ? (int)$_POST['day_of_week'] : -1;
    $start_time    = $_POST['start_time'] ?? '';
    $end_time      = $_POST['end_time'] ?? '';
    $grace_minutes = isset($_POST['grace_minutes']) ? (int)$_POST['grace_minutes'] : 10;
    $start_date    = $_POST['start_date'] ?? '';
    $end_date      = $_POST['end_date'] ?? '';
    $is_active     = isset($_POST['is_active']) ? 1 : 0;

    if ($course_id <= 0 || $room_id <= 0 || $day_of_week < 0 || $day_of_week > 6 || !is_valid_time($start_time) || !is_valid_time($end_time)) {
      flash("Missing/invalid routine data.", "danger");
      header("Location: " . $_SERVER['PHP_SELF']);
      exit();
    }
    if (!time_less($start_time, $end_time)) {
      flash("Start time must be before end time.", "danger");
      header("Location: " . $_SERVER['PHP_SELF']);
      exit();
    }
    if ($start_date && !is_valid_date($start_date)) $start_date = '';
    if ($end_date && !is_valid_date($end_date)) $end_date = '';
    if ($start_date && $end_date && $end_date < $start_date) {
      flash("End date must be after start date.", "danger");
      header("Location: " . $_SERVER['PHP_SELF']);
      exit();
    }
    if ($grace_minutes < 0) $grace_minutes = 0;
    if ($grace_minutes > 60) $grace_minutes = 60;

    $stmt = $conn->prepare("INSERT INTO course_session_routines (course_id, room_id, day_of_week, start_time, end_time, grace_minutes, start_date, end_date, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
      flash("DB prepare failed (routine insert): " . $conn->error, "danger");
      header("Location: " . $_SERVER['PHP_SELF']);
      exit();
    }
    $startDateVal = $start_date ?: null;
    $endDateVal   = $end_date ?: null;
    $stmt->bind_param("iiississi", $course_id, $room_id, $day_of_week, $start_time, $end_time, $grace_minutes, $startDateVal, $endDateVal, $is_active);
    $ok = $stmt->execute();
    $err = $stmt->error;
    $stmt->close();

    if (!$ok) {
      flash("Failed to save routine: " . $err, "danger");
    } else {
      flash("Routine saved successfully.", "success");
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
  }

  if ($action === 'update_routine') {
    $routine_id   = isset($_POST['routine_id']) ? (int)$_POST['routine_id'] : 0;
    $course_id     = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
    $room_id       = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
    $day_of_week   = isset($_POST['day_of_week']) ? (int)$_POST['day_of_week'] : -1;
    $start_time    = $_POST['start_time'] ?? '';
    $end_time      = $_POST['end_time'] ?? '';
    $grace_minutes = isset($_POST['grace_minutes']) ? (int)$_POST['grace_minutes'] : 10;
    $start_date    = $_POST['start_date'] ?? '';
    $end_date      = $_POST['end_date'] ?? '';
    $is_active     = isset($_POST['is_active']) ? 1 : 0;

    if ($routine_id <= 0 || $course_id <= 0 || $room_id <= 0 || $day_of_week < 0 || $day_of_week > 6 || !is_valid_time($start_time) || !is_valid_time($end_time)) {
      flash("Missing/invalid routine data.", "danger");
      header("Location: " . $_SERVER['PHP_SELF']);
      exit();
    }
    if (!time_less($start_time, $end_time)) {
      flash("Start time must be before end time.", "danger");
      header("Location: " . $_SERVER['PHP_SELF']);
      exit();
    }
    if ($start_date && !is_valid_date($start_date)) $start_date = '';
    if ($end_date && !is_valid_date($end_date)) $end_date = '';
    if ($start_date && $end_date && $end_date < $start_date) {
      flash("End date must be after start date.", "danger");
      header("Location: " . $_SERVER['PHP_SELF']);
      exit();
    }
    if ($grace_minutes < 0) $grace_minutes = 0;
    if ($grace_minutes > 60) $grace_minutes = 60;

    $stmt = $conn->prepare("UPDATE course_session_routines SET course_id = ?, room_id = ?, day_of_week = ?, start_time = ?, end_time = ?, grace_minutes = ?, start_date = ?, end_date = ?, is_active = ? WHERE routine_id = ?");
    if (!$stmt) {
      flash("DB prepare failed (routine update): " . $conn->error, "danger");
      header("Location: " . $_SERVER['PHP_SELF']);
      exit();
    }

    $startDateVal = $start_date ?: null;
    $endDateVal   = $end_date ?: null;
    $stmt->bind_param("iiississii", $course_id, $room_id, $day_of_week, $start_time, $end_time, $grace_minutes, $startDateVal, $endDateVal, $is_active, $routine_id);
    $ok = $stmt->execute();
    $err = $stmt->error;
    $stmt->close();

    if (!$ok) {
      flash("Failed to update routine: " . $err, "danger");
    } else {
      flash("Routine updated successfully.", "success");
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
  }

  if ($action === 'toggle_routine') {
    $routine_id = isset($_POST['routine_id']) ? (int)$_POST['routine_id'] : 0;
    $new_state  = isset($_POST['new_state']) ? (int)$_POST['new_state'] : 0;
    if ($routine_id > 0) {
      $stmt = $conn->prepare("UPDATE course_session_routines SET is_active = ? WHERE routine_id = ?");
      if ($stmt) {
        $stmt->bind_param("ii", $new_state, $routine_id);
        $stmt->execute();
        $stmt->close();
        flash($new_state ? "Routine enabled." : "Routine paused.", "success");
      }
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
  }

  if ($action === 'delete_routine') {
    $routine_id = isset($_POST['routine_id']) ? (int)$_POST['routine_id'] : 0;
    if ($routine_id > 0) {
      $stmt = $conn->prepare("DELETE FROM course_session_routines WHERE routine_id = ?");
      if ($stmt) {
        $stmt->bind_param("i", $routine_id);
        $stmt->execute();
        $stmt->close();
        flash("Routine deleted.", "success");
      }
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
  }

  if ($action === 'generate_from_routines') {
    $weeksAhead = isset($_POST['weeks_ahead']) ? (int)$_POST['weeks_ahead'] : 2;
    if ($weeksAhead < 1) $weeksAhead = 1;
    if ($weeksAhead > 12) $weeksAhead = 12;
    $daysAhead = $weeksAhead * 7;

    [$created, $skippedOverlap, $skippedRange, $errors] = generate_sessions_from_routines($conn, $daysAhead);
    $msg = "Auto-created {$created} sessions for the next {$weeksAhead} week(s). Skipped overlap/dup: {$skippedOverlap}. Outside date range: {$skippedRange}. Errors: {$errors}.";
    $type = $errors > 0 ? "danger" : "success";
    flash($msg, $type);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
  }
}

/* ---------------- Load routines ---------------- */
$routines = [];
$q3 = $conn->query("
  SELECT cr.*, c.course_code, c.course_name, r.room_name
  FROM course_session_routines cr
  JOIN courses c ON c.course_id = cr.course_id
  JOIN class_rooms r ON r.room_id = cr.room_id
  ORDER BY cr.is_active DESC, cr.day_of_week, c.course_code
");
if ($q3) while ($row = $q3->fetch_assoc()) $routines[] = $row;

$flash = read_flash();
?>

<!DOCTYPE html>
<html>
<head>
  <title>Weekly Routines</title>
  <meta charset="utf-8">
  <link rel="stylesheet" href="css/manageusers.css">
  <style>
    :root { --card:#fff; --text:#0f172a; --muted:#64748b; --line:#e5e7eb; --shadow:0 10px 30px rgba(0,0,0,.10); --radius:14px; --primary:#0ea5a4; --success:#10b981; --danger:#ef4444; --warning:#f59e0b; }
    body{ background:#f1f5f9; }
    .wrap{ max-width:1200px; margin:0 auto; padding:22px 16px 40px; }
    h2{ margin:8px 0 18px; font-size:28px; color:#0f172a; font-weight:700; }
    .card{ background:var(--card); border-radius:var(--radius); box-shadow:var(--shadow); border:1px solid rgba(0,0,0,.06); overflow:hidden; margin-bottom:20px; }
    .card-header{ padding:20px 24px; border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; }
    .card-header h3{ margin:0; font-size:22px; color:var(--text); font-weight:800; }
    .sub{ margin:4px 0 0; font-size:14px; color:var(--muted); line-height:1.4; }
    .card-body{ padding:24px; }

    .msg{ padding:14px 16px; border-radius:12px; margin:0 0 20px; border:1px solid transparent; font-size:14px; font-weight:600; }
    .success{ background:#ecfdf5; color:#065f46; border-color:#a7f3d0; }
    .danger{ background:#fef2f2; color:#991b1b; border-color:#fecaca; }
    .info{ background:#eff6ff; color:#1e40af; border-color:#bfdbfe; }

    .form-grid{ display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:12px; }
    .field{ display:flex; flex-direction:column; gap:8px; }
    .field label{ font-size:14px; font-weight:700; color:var(--text); }
    input,select{ width:100%; height:46px; padding:12px 14px; border-radius:10px; border:1px solid var(--line); outline:none; background:#fff; color:var(--text); font-size:14px; transition:border .2s; }
    input:focus,select:focus{ border-color:var(--primary); }
    .full{ grid-column:1/-1; }
    .actions{ grid-column:1/-1; display:flex; justify-content:flex-end; gap:12px; margin-top:8px; }
    button{ height:46px; padding:0 20px; border:none; border-radius:12px; background:var(--primary); color:#fff; font-weight:800; cursor:pointer; font-size:14px; transition:transform .1s; }
    button:hover{ transform:translateY(-1px); }
    button:active{ transform:translateY(0); }

    /* CTA button for save routine */
    .btn-cta{ background:linear-gradient(135deg, #0ea5a4 0%, #0ea5e9 100%); box-shadow:0 10px 25px rgba(14,165,233,.25); border-radius:14px; padding:0 24px; height:48px; letter-spacing:.01em; }
    .btn-cta:hover{ transform:translateY(-2px); box-shadow:0 14px 30px rgba(14,165,233,.30); }
    .btn-cta:active{ transform:translateY(0); box-shadow:0 8px 18px rgba(14,165,233,.25); }

    .badge{ display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.03em; }
    .badge-primary{ background:#dbeafe; color:#1e40af; }
    .badge-success{ background:#d1fae5; color:#065f46; }
    .badge-danger{ background:#fee2e2; color:#991b1b; }
    .badge-warning{ background:#fef3c7; color:#92400e; }
    .badge-muted{ background:#f1f5f9; color:#64748b; }

    .routine-grid{ display:grid; gap:16px; grid-template-columns:repeat(auto-fill, minmax(340px, 1fr)); }
    .routine-card{ background:#fff; border:2px solid #e5e7eb; border-radius:14px; padding:18px; position:relative; transition:all .2s; }
    .routine-card:hover{ border-color:#cbd5e1; box-shadow:0 4px 12px rgba(0,0,0,.08); }
    .routine-card.inactive{ opacity:.6; background:#f8fafc; }
    .routine-header{ display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; }
    .routine-title{ font-size:16px; font-weight:800; color:#0f172a; margin:0; }
    .routine-meta{ display:flex; flex-direction:column; gap:8px; margin-bottom:12px; }
    .routine-row{ display:flex; align-items:center; gap:8px; font-size:13px; color:#475569; }
    .routine-icon{ width:16px; height:16px; opacity:.6; }
    .routine-actions{ display:flex; gap:8px; margin-top:14px; padding-top:14px; border-top:1px solid #e5e7eb; }
    .btn-sm{ height:36px; padding:0 14px; font-size:12px; border-radius:8px; font-weight:700; cursor:pointer; transition:all .15s; border:none; background:#0ea5a4; color:#fff; }
    .btn-sm:hover{ transform:translateY(-1px); }
    .btn-sm:active{ transform:translateY(0); }
    .btn-outline{ background:#fff; border:1.5px solid #e5e7eb; color:#475569; }
    .btn-outline:hover{ border-color:#cbd5e1; background:#f8fafc; transform:translateY(-1px); }
    .btn-danger{ background:#ef4444; color:#fff; }
    .btn-danger:hover{ background:#dc2626; }

    .empty-state{ text-align:center; padding:48px 24px; color:var(--muted); }
    .empty-state svg{ width:64px; height:64px; margin:0 auto 16px; opacity:.3; }
    .empty-state h4{ margin:0 0 8px; font-size:18px; color:#475569; }
    .empty-state p{ margin:0; font-size:14px; }

    .inline-gen-form{ display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
    .inline-gen-form label{ font-size:12px; font-weight:700; color:var(--text); }
    .inline-gen-form input{ width:90px; height:36px; padding:0 10px; border:1px solid var(--line); border-radius:8px; font-weight:700; }
    .inline-gen-form button{ height:36px; padding:0 14px; background:#0ea5a4; color:#fff; font-weight:800; border-radius:8px; border:none; cursor:pointer; }

    @media (max-width:900px){
      h2{ font-size:22px; }
      .card-body{ padding:18px; }
      .card-header{ padding:16px 18px; }
      .form-grid{ grid-template-columns:1fr; }
      .actions{ justify-content:stretch; }
      button{ width:100%; }
      .routine-grid{ grid-template-columns:1fr; }
      .auto-gen-panel{ padding:18px; }
    }
  </style>
</head>

<body>
<?php include 'header.php'; ?>

<div class="wrap">

  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
    <h2 style="margin:0;">Weekly Routines</h2>
    <a href="course_sessions.php" style="display:inline-flex; align-items:center; gap:8px; padding:10px 20px; background:#fff; border:2px solid #0ea5a4; border-radius:10px; color:#0ea5a4; text-decoration:none; font-weight:800; font-size:14px; transition:all .2s; box-shadow:0 2px 8px rgba(14,165,164,.15);" onmouseover="this.style.background='#0ea5a4'; this.style.color='#fff';" onmouseout="this.style.background='#fff'; this.style.color='#0ea5a4';">Back to Sessions</a>
  </div>

  <?php if ($flash): ?>
    <div class="msg <?= htmlspecialchars($flash['type']) ?>">
      <?= htmlspecialchars($flash['msg']) ?>
    </div>
  <?php endif; ?>

  <!-- Create new routine -->
  <div class="card">
    <div class="card-header">
      <div>
        <h3 id="formTitle">Create New Routine</h3>
        <p class="sub">Define a weekly recurring session pattern (recommended for Sun-Thu schedules).</p>
      </div>
    </div>

    <div class="card-body">
      <form id="routineForm" method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
        <input type="hidden" name="action" id="formAction" value="create_routine">
        <input type="hidden" name="routine_id" id="routineId" value="">

        <div class="form-grid">
          <div class="field full">
            <label>Course</label>
            <select name="course_id" id="courseField" required>
              <option value="">-- Select Course --</option>
              <?php foreach ($courses as $c): ?>
                <option value="<?= (int)$c['course_id'] ?>">
                  <?= htmlspecialchars($c['course_code']) ?> - <?= htmlspecialchars($c['course_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label>Room</label>
            <select name="room_id" id="roomField" required>
              <option value="">-- Select Room --</option>
              <?php foreach ($rooms as $r): ?>
                <option value="<?= (int)$r['room_id'] ?>"><?= htmlspecialchars($r['room_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label>Day of Week</label>
            <select name="day_of_week" id="dayField" required>
              <option value="">-- Choose day --</option>
              <?php foreach ($dowLabels as $idx => $label): ?>
                <option value="<?= (int)$idx ?>"><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label>Start Time</label>
            <input type="time" name="start_time" id="startField" required>
          </div>

          <div class="field">
            <label>End Time</label>
            <input type="time" name="end_time" id="endField" required>
          </div>

          <div class="field">
            <label>Grace Minutes</label>
            <input type="number" name="grace_minutes" id="graceField" value="10" min="0" max="60">
          </div>

          <div class="field">
            <label>Start Date (optional)</label>
            <input type="date" name="start_date" id="startDateField" placeholder="Leave empty for no restriction">
          </div>

          <div class="field">
            <label>End Date (optional)</label>
            <input type="date" name="end_date" id="endDateField" placeholder="Leave empty for no restriction">
          </div>

          <div class="field full" style="flex-direction:row; align-items:center; gap:10px;">
            <input type="checkbox" name="is_active" id="is_active" checked style="width:20px; height:20px; cursor:pointer;">
            <label for="is_active" style="margin:0; cursor:pointer;">Active (starts generating sessions immediately)</label>
          </div>

          <div class="actions">
            <button id="formSubmit" class="btn-cta" type="submit">Save Routine</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Weekly Timetable Viewer -->
  <div class="card">
    <div class="card-header" style="align-items:center; gap:12px;">
      <div>
        <h3>Weekly Class Routine</h3>
        <p class="sub">University timetable view; paused routines stay visible with a pause badge.</p>
      </div>
      <form class="inline-gen-form" method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" style="margin-left:auto;">
        <input type="hidden" name="action" value="generate_from_routines">
        <label for="weeks_ahead">Weeks</label>
        <input id="weeks_ahead" type="number" name="weeks_ahead" value="2" min="1" max="12">
        <button type="submit">Generate</button>
      </form>
    </div>

    <div class="card-body">
      <?php
      // Period definitions with time ranges
      $periodDef = [
        1 => ['start' => '08:10:00', 'end' => '09:00:00', 'label' => 'Period 1', 'time' => '8:10-9:00'],
        2 => ['start' => '09:00:00', 'end' => '09:50:00', 'label' => 'Period 2', 'time' => '9:00-9:50'],
        3 => ['start' => '09:50:00', 'end' => '10:40:00', 'label' => 'Period 3', 'time' => '9:50-10:40'],
        'break' => ['start' => '10:40:00', 'end' => '11:00:00', 'label' => 'BREAK', 'time' => '10:40-11:00'],
        4 => ['start' => '11:00:00', 'end' => '11:50:00', 'label' => 'Period 4', 'time' => '11:00-11:50'],
        5 => ['start' => '11:50:00', 'end' => '12:40:00', 'label' => 'Period 5', 'time' => '11:50-12:40'],
        6 => ['start' => '12:40:00', 'end' => '13:30:00', 'label' => 'Period 6', 'time' => '12:40-13:30'],
        'lunch' => ['start' => '13:30:00', 'end' => '14:30:00', 'label' => 'LUNCH', 'time' => '13:30-14:30'],
        7 => ['start' => '14:30:00', 'end' => '15:20:00', 'label' => 'Period 7', 'time' => '14:30-15:20'],
        8 => ['start' => '15:20:00', 'end' => '16:10:00', 'label' => 'Period 8', 'time' => '15:20-16:10'],
        9 => ['start' => '16:10:00', 'end' => '17:00:00', 'label' => 'Period 9', 'time' => '16:10-17:00'],
      ];

      // Build timetable structure
      $dayNames = [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday'];
      $timetable = [];
      
      // Initialize empty timetable
      foreach ($dayNames as $dayNum => $dayName) {
        $timetable[$dayNum] = [
          'name' => $dayName,
          'slots' => []
        ];
        foreach ([1, 2, 3, 'break', 4, 5, 6, 'lunch', 7, 8, 9] as $p) {
          $timetable[$dayNum]['slots'][$p] = [];
        }
      }

      // Fill timetable with routines (including paused ones for visibility)
      foreach ($routines as $r) {
        $dayNum = (int)$r['day_of_week'];
        if (!isset($timetable[$dayNum])) continue;
        
        $rStart = $r['start_time'];
        $rEnd = $r['end_time'];
        
        // Find which periods this routine occupies and calculate span
        $occupiedPeriods = [];
        foreach ([1, 2, 3, 'break', 4, 5, 6, 'lunch', 7, 8, 9] as $p) {
          $pStart = $periodDef[$p]['start'];
          $pEnd = $periodDef[$p]['end'];
          
          // Check if routine overlaps with this period
          if ($rStart < $pEnd && $rEnd > $pStart) {
            $occupiedPeriods[] = $p;
          }
        }
        
        // Add to first occupied period with colspan info
        if (!empty($occupiedPeriods)) {
          $firstPeriod = $occupiedPeriods[0];
          $timetable[$dayNum]['slots'][$firstPeriod][] = [
            'course' => $r['course_code'],
            'room' => $r['room_name'],
            'time' => substr($r['start_time'], 0, 5) . '-' . substr($r['end_time'], 0, 5),
            'colspan' => count($occupiedPeriods),
            'periods' => $occupiedPeriods,
            'is_active' => (int)$r['is_active']
          ];
          
          // Mark other periods as occupied by this session
          for ($i = 1; $i < count($occupiedPeriods); $i++) {
            $timetable[$dayNum]['slots'][$occupiedPeriods[$i]][] = ['skip' => true];
          }
        }
      }
      ?>

      <style>
        .timetable-wrapper{ background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08); margin-top:16px; border:1px solid #e5e7eb; }
        .timetable-scroll{ overflow-x:auto; }
        .timetable{ width:100%; border-collapse:collapse; min-width:1100px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; }
        
        .timetable thead th{ background:linear-gradient(135deg, #1e293b 0%, #334155 100%); color:#fff; padding:14px 10px; text-align:center; font-weight:700; font-size:12px; letter-spacing:.02em; border:1px solid rgba(255,255,255,.2); position:sticky; top:0; z-index:10; }
        .timetable thead .period-time{ display:block; font-size:10px; font-weight:500; opacity:.85; margin-top:4px; letter-spacing:.01em; }
        
        .timetable tbody td{ padding:12px 8px; min-height:60px; vertical-align:middle; text-align:center; border:1px solid #e5e7eb; background:#fff; transition:all .2s; }
        .timetable tbody tr:hover td:not(.day-header){ background:#f8fafc; }
        
        .timetable .day-header{ background:#f1f5f9; font-weight:800; color:#0f172a; font-size:13px; width:90px; border:1px solid #cbd5e1; text-align:left; padding-left:16px; }
        .timetable tbody tr:hover .day-header{ background:#e5e7eb; }
        
        .timetable .break-col, .timetable .lunch-col{ background:linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); font-weight:800; color:#92400e; font-size:12px; letter-spacing:.05em; border:1px solid #fcd34d; }
        
        .timetable .empty-slot{ color:#94a3b8; font-size:11px; font-style:italic; }
        
        .timetable .class-item{ position:relative; background:linear-gradient(135deg, #e0f2fe 0%, #dbeafe 100%); border:1.5px solid #3b82f6; border-radius:8px; padding:8px 10px; margin:3px 0; display:inline-block; min-width:90%; transition:all .2s; cursor:pointer; }
        .timetable .class-item:hover{ transform:translateY(-2px); box-shadow:0 4px 12px rgba(59,130,246,.25); }
        .timetable .class-item.paused{ background:linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-color:#f59e0b; }
        .timetable .class-status{ position:absolute; top:6px; right:6px; background:#fff7ed; color:#c2410c; border:1px solid #fdba74; border-radius:999px; padding:2px 8px; font-size:10px; font-weight:800; letter-spacing:.02em; display:inline-flex; align-items:center; gap:4px; }
        .timetable .class-status span{ font-size:11px; }
        .timetable .class-course{ font-weight:800; font-size:13px; color:#1e40af; display:block; margin-bottom:3px; }
        .timetable .class-room{ font-size:10px; color:#64748b; font-weight:600; display:block; }
        .timetable .class-time{ font-size:9px; color:#94a3b8; font-weight:500; display:block; margin-top:2px; font-family:ui-monospace,monospace; }
        
        .timetable-legend{ display:flex; gap:20px; margin-top:16px; padding:12px; background:#f8fafc; border-radius:8px; flex-wrap:wrap; }
        .legend-item{ display:flex; align-items:center; gap:8px; font-size:12px; color:#475569; }
        .legend-box{ width:16px; height:16px; border-radius:4px; border:1px solid; }
        .legend-class{ background:#dbeafe; border-color:#3b82f6; }
        .legend-break{ background:#fef3c7; border-color:#fcd34d; }
        .legend-empty{ background:#fff; border-color:#e5e7eb; }
        
        @media (max-width:900px){
          .timetable thead th{ padding:10px 6px; font-size:10px; }
          .timetable tbody td{ padding:8px 4px; }
          .timetable .class-item{ padding:6px 8px; }
          .timetable .class-course{ font-size:11px; }
        }

        /* Modal Styles */
        .modal-overlay{ display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.5); z-index:1000; align-items:center; justify-content:center; }
        .modal-overlay.active{ display:flex; }
        .modal-content{ background:#fff; border-radius:16px; max-width:500px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:modalSlideIn .3s ease-out; }
        @keyframes modalSlideIn{ from{ opacity:0; transform:translateY(-30px); } to{ opacity:1; transform:translateY(0); } }
        .modal-header{ padding:20px 24px; border-bottom:2px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center; }
        .modal-header h3{ margin:0; font-size:20px; color:#0f172a; font-weight:800; }
        .modal-close{ background:none; border:none; font-size:28px; color:#64748b; cursor:pointer; line-height:1; padding:0; width:32px; height:32px; border-radius:50%; transition:all .2s; }
        .modal-close:hover{ background:#f1f5f9; color:#0f172a; }
        .modal-body{ padding:24px; }
        .modal-field{ margin-bottom:16px; }
        .modal-label{ font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.03em; margin-bottom:6px; display:block; }
        .modal-value{ font-size:15px; color:#0f172a; font-weight:600; }
        .modal-footer{ padding:16px 24px; border-top:1px solid #e5e7eb; display:flex; gap:10px; justify-content:flex-end; background:#f8fafc; border-radius:0 0 16px 16px; }
        .modal-btn{ height:42px; padding:0 20px; border-radius:10px; font-weight:700; cursor:pointer; transition:all .2s; font-size:14px; border:none; }
        .modal-btn-secondary{ background:#fff; border:1.5px solid #e5e7eb; color:#475569; }
        .modal-btn-secondary:hover{ background:#f1f5f9; border-color:#cbd5e1; }
        .modal-btn-danger{ background:#ef4444; color:#fff; }
        .modal-btn-danger:hover{ background:#dc2626; }
        .modal-btn-primary{ background:#0ea5a4; color:#fff; }
        .modal-btn-primary:hover{ background:#0891b2; }
        .status-badge{ display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:999px; font-size:12px; font-weight:700; }
        .status-active{ background:#d1fae5; color:#065f46; }
        .status-paused{ background:#fee2e2; color:#991b1b; }
      </style>

      <div class="timetable-wrapper">
        <div class="timetable-scroll">
          <table class="timetable">
            <thead>
              <tr>
                <th style="width:90px;">Day</th>
                <th>Period 1<span class="period-time">8:10-9:00</span></th>
                <th>Period 2<span class="period-time">9:00-9:50</span></th>
                <th>Period 3<span class="period-time">9:50-10:40</span></th>
                <th class="break-col">BREAK<span class="period-time">10:40-11:00</span></th>
                <th>Period 4<span class="period-time">11:00-11:50</span></th>
                <th>Period 5<span class="period-time">11:50-12:40</span></th>
                <th>Period 6<span class="period-time">12:40-13:30</span></th>
                <th class="lunch-col">LUNCH<span class="period-time">13:30-14:30</span></th>
                <th>Period 7<span class="period-time">14:30-15:20</span></th>
                <th>Period 8<span class="period-time">15:20-16:10</span></th>
                <th>Period 9<span class="period-time">16:10-17:00</span></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ([0, 1, 2, 3, 4] as $dayNum): ?>
                <tr>
                  <td class="day-header"><?= $timetable[$dayNum]['name'] ?></td>
                  <?php 
                  foreach ([1, 2, 3, 'break', 4, 5, 6, 'lunch', 7, 8, 9] as $period):
                    $classes = $timetable[$dayNum]['slots'][$period];
                    
                    // Skip if this cell is part of a colspan
                    if (!empty($classes)) {
                      $firstClass = $classes[0];
                      if (isset($firstClass['skip']) && $firstClass['skip'] === true) {
                        continue;
                      }
                    }
                    
                    // Determine colspan
                    $colspanAttr = '';
                    $colspanStyle = '';
                    if (!empty($classes) && isset($classes[0]['colspan']) && $classes[0]['colspan'] > 1) {
                      $colspanValue = (int)$classes[0]['colspan'];
                      $colspanAttr = ' colspan="' . $colspanValue . '"';
                      // Force width calculation for visual effect
                      $colspanStyle = ' style="min-width:' . ($colspanValue * 120) . 'px;"';
                    }
                    
                    // Check if this is a break/lunch period
                    $cellClass = '';
                    if ($period == 'break' || $period == 'lunch') {
                      $cellClass = $period == 'break' ? ' class="break-col"' : ' class="lunch-col"';
                    }
                  ?>
                    <td<?= $colspanAttr ?><?= $colspanStyle ?><?= $cellClass ?>>
                      <?php if ($period == 'break' || $period == 'lunch'): ?>
                        &nbsp;
                      <?php elseif (empty($classes)): ?>
                        <span class="empty-slot">—</span>
                      <?php else: ?>
                        <?php foreach ($classes as $class): ?>
                          <?php if (isset($class['skip'])) continue; ?>
                          <?php
                          // Find the full routine data for this session
                          $routineData = null;
                          foreach ($routines as $r) {
                            if ($r['course_code'] == $class['course'] && (int)$r['day_of_week'] == $dayNum) {
                              $routineData = $r;
                              break;
                            }
                          }
                          $isActive = isset($class['is_active']) ? (int)$class['is_active'] === 1 : true;
                          ?>
                          <div class="class-item<?= $isActive ? '' : ' paused' ?>" 
                               onclick="showRoutineModal(<?= htmlspecialchars(json_encode($routineData), ENT_QUOTES) ?>)">
                            <?php if (!$isActive): ?>
                              <div class="class-status"><span>⏸</span>Paused</div>
                            <?php endif; ?>
                            <span class="class-course"><?= htmlspecialchars($class['course']) ?></span>
                            <?php if ($class['room']): ?>
                              <span class="class-room"><?= htmlspecialchars($class['room']) ?></span>
                            <?php endif; ?>
                            <span class="class-time"><?= htmlspecialchars($class['time']) ?></span>
                          </div>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="timetable-legend">
        <div class="legend-item">
          <div class="legend-box legend-class"></div>
          <span>Class Session</span>
        </div>
        <div class="legend-item">
          <div class="legend-box legend-break"></div>
          <span>Break / Lunch</span>
        </div>
        <div class="legend-item">
          <div class="legend-box legend-empty"></div>
          <span>Free Period</span>
        </div>
      </div>
      <!-- Debug Info -->
      <?php if (isset($_GET['debug'])): ?>
        <div style="margin-top:20px; padding:15px; background:#f8fafc; border:1px solid #e5e7eb; border-radius:8px; font-family:monospace; font-size:12px;">
          <strong>Debug Info:</strong><br>
          Total routines: <?= count($routines) ?><br>
          Active routines: <?= count(array_filter($routines, function($r){ return (int)$r['is_active'] === 1; })) ?><br><br>
          <strong>Timetable Structure:</strong><br>
          <?php foreach ([0, 1, 2, 3, 4] as $dayNum): ?>
            <strong><?= $timetable[$dayNum]['name'] ?>:</strong><br>
            <?php foreach ([1, 2, 3, 'break', 4, 5, 6, 'lunch', 7, 8, 9] as $period): ?>
              Period <?= $period ?>: 
              <?php 
              $slots = $timetable[$dayNum]['slots'][$period];
              if (!empty($slots)) {
                foreach ($slots as $slot) {
                  if (isset($slot['skip'])) {
                    echo "[SKIP] ";
                  } else {
                    echo htmlspecialchars($slot['course']);
                    if (isset($slot['colspan'])) echo " (colspan=" . $slot['colspan'] . ")";
                    echo " ";
                  }
                }
              } else {
                echo "empty";
              }
              ?>
              <br>
            <?php endforeach; ?>
            <br>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- Routine Detail Modal -->
<div id="routineModal" class="modal-overlay">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Routine Details</h3>
      <button class="modal-close" onclick="closeRoutineModal()">&times;</button>
    </div>
    <div class="modal-body">
      <div class="modal-field">
        <span class="modal-label">Course</span>
        <div class="modal-value" id="modalCourse"></div>
      </div>
      <div class="modal-field">
        <span class="modal-label">Room</span>
        <div class="modal-value" id="modalRoom"></div>
      </div>
      <div class="modal-field">
        <span class="modal-label">Day</span>
        <div class="modal-value" id="modalDay"></div>
      </div>
      <div class="modal-field">
        <span class="modal-label">Time</span>
        <div class="modal-value" id="modalTime"></div>
      </div>
      <div class="modal-field">
        <span class="modal-label">Grace Period</span>
        <div class="modal-value" id="modalGrace"></div>
      </div>
      <div class="modal-field">
        <span class="modal-label">Date Range</span>
        <div class="modal-value" id="modalDateRange"></div>
      </div>
      <div class="modal-field">
        <span class="modal-label">Status</span>
        <div id="modalStatus"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="modal-btn modal-btn-primary" onclick="startEditRoutine()">Edit</button>
      <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" style="display:inline;" id="toggleForm">
        <input type="hidden" name="action" value="toggle_routine">
        <input type="hidden" name="routine_id" id="modalRoutineId">
        <input type="hidden" name="new_state" id="modalNewState">
        <button type="submit" class="modal-btn modal-btn-secondary" id="toggleBtn"></button>
      </form>
      <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" style="display:inline;" onsubmit="return confirm('Delete this routine permanently?');">
        <input type="hidden" name="action" value="delete_routine">
        <input type="hidden" name="routine_id" id="modalDeleteId">
        <button type="submit" class="modal-btn modal-btn-danger">Delete</button>
      </form>
      <button type="button" class="modal-btn modal-btn-secondary" onclick="closeRoutineModal()">Close</button>
    </div>
  </div>
</div>

<script>
let currentRoutine = null;
const dayLabels = <?= json_encode($dowLabels) ?>;

function showRoutineModal(routine) {
  if (!routine) return;
  currentRoutine = routine;
  
  document.getElementById('modalCourse').textContent = routine.course_code + ' - ' + routine.course_name;
  document.getElementById('modalRoom').textContent = routine.room_name;
  document.getElementById('modalDay').textContent = dayLabels[routine.day_of_week] || routine.day_of_week;
  document.getElementById('modalTime').textContent = routine.start_time.substring(0, 5) + ' - ' + routine.end_time.substring(0, 5);
  document.getElementById('modalGrace').textContent = routine.grace_minutes + ' minutes';
  
  const startDate = routine.start_date || 'Any';
  const endDate = routine.end_date || 'Any';
  document.getElementById('modalDateRange').textContent = startDate + ' to ' + endDate;
  
  const isActive = parseInt(routine.is_active) === 1;
  const statusHtml = '<span class="status-badge ' + (isActive ? 'status-active' : 'status-paused') + '">' + (isActive ? 'Active' : 'Paused') + '</span>';
  document.getElementById('modalStatus').innerHTML = statusHtml;
  
  document.getElementById('modalRoutineId').value = routine.routine_id;
  document.getElementById('modalDeleteId').value = routine.routine_id;
  document.getElementById('modalNewState').value = isActive ? '0' : '1';
  document.getElementById('toggleBtn').textContent = isActive ? 'Pause' : 'Enable';
  
  document.getElementById('routineModal').classList.add('active');
}

function closeRoutineModal() {
  document.getElementById('routineModal').classList.remove('active');
}

function startEditRoutine() {
  if (!currentRoutine) return;
  editRoutine(currentRoutine);
  closeRoutineModal();
}

function editRoutine(routine) {
  if (!routine) return;
  const form = document.getElementById('routineForm');
  if (!form) return;

  document.getElementById('formAction').value = 'update_routine';
  document.getElementById('routineId').value = routine.routine_id;
  document.getElementById('courseField').value = routine.course_id;
  document.getElementById('roomField').value = routine.room_id;
  document.getElementById('dayField').value = routine.day_of_week;
  document.getElementById('startField').value = routine.start_time ? routine.start_time.substring(0,5) : '';
  document.getElementById('endField').value = routine.end_time ? routine.end_time.substring(0,5) : '';
  document.getElementById('graceField').value = routine.grace_minutes;
  document.getElementById('startDateField').value = routine.start_date || '';
  document.getElementById('endDateField').value = routine.end_date || '';
  document.getElementById('is_active').checked = parseInt(routine.is_active) === 1;

  const submitBtn = document.getElementById('formSubmit');
  const title = document.getElementById('formTitle');
  if (submitBtn) submitBtn.textContent = 'Update Routine';
  if (title) title.textContent = 'Edit Routine';

  form.scrollIntoView({ behavior:'smooth', block:'start' });
}

// Close modal when clicking outside
document.getElementById('routineModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeRoutineModal();
  }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeRoutineModal();
  }
});
</script>

</body>
</html>
