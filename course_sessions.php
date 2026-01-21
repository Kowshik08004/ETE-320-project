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

/* ---------------- Status refresh ----------------
   course_sessions.status enum:
   scheduled, closed, attendance_generated

   We'll treat "scheduled" as "attendance window open right now".
-------------------------------------------------- */
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
      day_of_week TINYINT NOT NULL, -- 0=Sun
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
        false // refresh at end
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

/* ---------------- Load Rooms (class_rooms table) ---------------- */
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

  if ($action === 'create_session') {
    $course_id     = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
    $room_id       = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
    $date          = $_POST['session_date'] ?? '';
    $start_time    = $_POST['start_time'] ?? '';
    $end_time      = $_POST['end_time'] ?? '';
    $grace_minutes = isset($_POST['grace_minutes']) ? (int)$_POST['grace_minutes'] : 10;

    if ($course_id <= 0 || $room_id <= 0 || !is_valid_date($date) || !is_valid_time($start_time) || !is_valid_time($end_time)) {
      flash("Missing/invalid data (course/room/date/time).", "danger");
      header("Location: " . $_SERVER['PHP_SELF']);
      exit();
    }

    if (!time_less($start_time, $end_time)) {
      flash("Start time must be before end time.", "danger");
      header("Location: " . $_SERVER['PHP_SELF']);
      exit();
    }

    if ($grace_minutes < 0) $grace_minutes = 0;
    if ($grace_minutes > 60) $grace_minutes = 60;

    refresh_session_status($conn);
    [$ok, $msg] = insert_course_session($conn, $course_id, $room_id, $date, $start_time, $end_time, $grace_minutes, true);
    flash($msg, $ok ? "success" : "danger");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
  }

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
      flash("Routine saved.", "success");
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
        flash($new_state ? "Routine enabled." : "Routine disabled.", "success");
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

  if ($action === 'cancel_session') {
    $session_id = isset($_POST['session_id']) ? (int)$_POST['session_id'] : 0;
    if ($session_id > 0) {
      $stmt = $conn->prepare("DELETE FROM course_sessions WHERE session_id = ? AND status <> 'attendance_generated'");
      if ($stmt) {
        $stmt->bind_param("i", $session_id);
        $ok = $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        if ($ok && $affected > 0) {
          flash("Session cancelled successfully.", "success");
        } else {
          flash("Failed to cancel session (may be locked).", "danger");
        }
      }
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
  }

  if ($action === 'generate_from_routines') {
    $daysAhead = isset($_POST['days_ahead']) ? (int)$_POST['days_ahead'] : 14;
    if ($daysAhead < 1) $daysAhead = 1;
    if ($daysAhead > 60) $daysAhead = 60;

    [$created, $skippedOverlap, $skippedRange, $errors] = generate_sessions_from_routines($conn, $daysAhead);
    $msg = "Auto-created {$created} sessions. Skipped overlap/dup: {$skippedOverlap}. Outside date range: {$skippedRange}. Errors: {$errors}.";
    $type = $errors > 0 ? "danger" : "success";
    flash($msg, $type);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
  }
}

/* ---------------- Always refresh statuses before showing list ---------------- */
refresh_session_status($conn);

/* Get filter values from URL */
$filterDate = isset($_GET['filter_date']) ? trim($_GET['filter_date']) : '';
if ($filterDate && !is_valid_date($filterDate)) $filterDate = '';

$filterCourse = isset($_GET['filter_course']) ? (int)$_GET['filter_course'] : 0;

/* Load courses for filter dropdown */
$coursesForFilter = [];
$qCourses = $conn->query("SELECT DISTINCT c.course_id, c.course_code, c.course_name FROM courses c ORDER BY c.course_code");
if ($qCourses) while ($row = $qCourses->fetch_assoc()) $coursesForFilter[] = $row;

/* Load sessions with filters */
$sessions = [];
$query = "SELECT
  cs.session_id,
  cs.course_id,
  c.course_code,
  c.course_name,
  r.room_name,
  cs.session_date,
  cs.start_time,
  cs.end_time,
  cs.grace_minutes,
  cs.status
FROM course_sessions cs
JOIN courses c ON c.course_id = cs.course_id
JOIN class_rooms r ON r.room_id = cs.room_id
WHERE 1";

if ($filterDate) {
  $query .= " AND cs.session_date = '" . $conn->real_escape_string($filterDate) . "'";
}
if ($filterCourse > 0) {
  $query .= " AND cs.course_id = " . (int)$filterCourse;
}

$query .= " ORDER BY cs.session_date DESC, cs.start_time DESC LIMIT 100";

$q2 = $conn->query($query);
if ($q2) while ($row = $q2->fetch_assoc()) $sessions[] = $row;

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
  <title>Course Sessions</title>
  <meta charset="utf-8">
  <link rel="stylesheet" href="css/manageusers.css">
  <style>
    :root { --card:#fff; --text:#0f172a; --muted:#64748b; --line:#e5e7eb; --shadow:0 10px 30px rgba(0,0,0,.10); --radius:14px; }
    .wrap{ max-width:1050px; margin:0 auto; padding:22px 16px 40px; }
    h2{ margin:8px 0 18px; font-size:28px; color:rgba(0,0,0,.45); font-weight:700; }
    .card{ background:var(--card); border-radius:var(--radius); box-shadow:var(--shadow); border:1px solid rgba(0,0,0,.06); overflow:hidden; }
    .card-header{ padding:18px 22px; border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .card-header h3{ margin:0; font-size:20px; color:var(--text); }
    .sub{ margin:0; font-size:13px; color:var(--muted); }
    .card-body{ padding:18px 22px 22px; }

    .msg{ padding:12px 14px; border-radius:12px; margin:10px 0 16px; border:1px solid transparent; font-size:14px; }
    .success{ background:#ecfdf5; color:#065f46; border-color:#a7f3d0; }
    .danger{ background:#fef2f2; color:#991b1b; border-color:#fecaca; }
    .info{ background:#eff6ff; color:#1e40af; border-color:#bfdbfe; }

    .form-grid{ display:grid; grid-template-columns:1fr 1fr; gap:14px 16px; margin-top:10px; }
    .field{ display:flex; flex-direction:column; gap:6px; }
    .field label{ font-size:13px; font-weight:700; color:var(--text); }
    input,select{ width:100%; height:44px; padding:10px 12px; border-radius:10px; border:1px solid var(--line); outline:none; background:#fff; color:var(--text); }
    .full{ grid-column:1/-1; }
    .actions{ grid-column:1/-1; display:flex; justify-content:flex-end; margin-top:4px; }
    button{ height:44px; padding:0 18px; border:none; border-radius:12px; background:#0ea5a4; color:#fff; font-weight:800; cursor:pointer; }

    .row{ display:flex; gap:16px; align-items:flex-start; flex-wrap:wrap; }
    .card + .card{ margin-top:18px; }
    .pill{ display:inline-flex; align-items:center; gap:8px; padding:6px 10px; border-radius:999px; background:#f8fafc; color:#0f172a; font-size:12px; font-weight:700; }
    .tag{ display:inline-flex; padding:3px 8px; border-radius:8px; background:#e5e7eb; font-size:12px; font-weight:700; color:#111827; }
    .btn-ghost{ height:36px; padding:0 12px; border-radius:10px; border:1px solid var(--line); background:#fff; color:#0f172a; font-weight:700; cursor:pointer; }
    .btn-ghost:hover{ background:#f1f5f9; }
    .btn-danger{ border-color:#fecaca; color:#991b1b; }
    .inline-form{ display:flex; gap:8px; align-items:center; }
    .inline-form input{ height:36px; padding:6px 10px; border-radius:8px; }
    .muted{ color:var(--muted); font-size:13px; }
    .text-strong{ font-weight:800; color:#0f172a; }

    .table-wrap{ margin-top:18px; border:1px solid var(--line); border-radius:12px; overflow:hidden; }
    .table-title{ padding:12px 14px; border-bottom:1px solid var(--line); background:#f8fafc; }
    .table-title h4{ margin:0; font-size:16px; color:var(--text); }
    .table-scroll{ overflow:auto; max-height:360px; }
    table{ width:100%; border-collapse:collapse; min-width:920px; }
    thead th{ position:sticky; top:0; background:#f1f5f9; color:#000; font-size:12px; letter-spacing:.04em; text-transform:uppercase; padding:10px 12px; text-align:left; z-index:1; border-bottom:1px solid #e5e7eb; }
    tbody td{ padding:10px 12px; border-bottom:1px solid var(--line); font-size:14px; color:#0f172a; background:#fff; white-space:nowrap; }
    tbody tr:nth-child(even) td{ background:#f8fafc; }
    .mono{ font-variant-numeric:tabular-nums; font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace; }
    .status{ display:inline-flex; align-items:center; gap:8px; padding:5px 10px; border-radius:999px; font-size:12px; font-weight:800; border:1px solid; }
    .status-open{ background:#ecfdf5; color:#065f46; border-color:#a7f3d0; }
    .status-closed{ background:#fef2f2; color:#991b1b; border-color:#fecaca; }
    .dot{ width:8px; height:8px; border-radius:999px; background:currentColor; opacity:.8; }

    @media (max-width:900px){
      h2{ font-size:22px; }
      .card-body{ padding:16px; }
      .card-header{ padding:14px 16px; }
      .form-grid{ grid-template-columns:1fr; }
      .actions{ justify-content:stretch; }
      button{ width:100%; }
      table{ min-width:860px; }
    }
  </style>
</head>

<body>
<?php include 'header.php'; ?>

<div class="wrap">

  <?php if ($flash): ?>
    <div class="msg <?= htmlspecialchars($flash['type']) ?>">
      <?= htmlspecialchars($flash['msg']) ?>
    </div>
  <?php endif; ?>

  <!-- Action Buttons -->
  <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px;">
    <a href="create_manual_session.php" style="display:flex; flex-direction:column; align-items:center; justify-content:center; background:#fff; border:2px solid #e5e7eb; border-radius:14px; padding:32px 24px; text-decoration:none; transition:all .2s; box-shadow:var(--shadow);">
      <h3 style="margin:0 0 8px; font-size:20px; color:#0f172a; font-weight:800;">Manual Creation</h3>
      <p style="margin:0; font-size:14px; color:#64748b; text-align:center;">Create individual sessions one at a time</p>
    </a>
    <a href="weekly_routines.php" style="display:flex; flex-direction:column; align-items:center; justify-content:center; background:#fff; border:2px solid #e5e7eb; border-radius:14px; padding:32px 24px; text-decoration:none; transition:all .2s; box-shadow:var(--shadow);">
      <h3 style="margin:0 0 8px; font-size:20px; color:#0f172a; font-weight:800;">Automatic (Weekly Routines)</h3>
      <p style="margin:0; font-size:14px; color:#64748b; text-align:center;">Set up recurring weekly sessions</p>
    </a>
  </div>

  <!-- Recent Sessions Table -->
  <div class="card">
    <div class="card-header">
      <div>
        <h3>Recent Sessions</h3>
        <p class="sub">All course sessions from <b>course_sessions</b> table.</p>
      </div>
    </div>

    <div class="card-body">
      <!-- Filters -->
      <form method="GET" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" style="margin-bottom:16px; padding:12px; background:#f8fafc; border-radius:10px; border:1px solid #e5e7eb;">
        <div style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
          <div style="display:flex; flex-direction:column; gap:4px;">
            <label style="font-size:12px; font-weight:700; color:#0f172a;">Filter by Date</label>
            <input type="date" name="filter_date" value="<?= htmlspecialchars($filterDate) ?>" style="height:36px; padding:6px 10px; border-radius:8px; border:1px solid #e5e7eb;">
          </div>
          <div style="display:flex; flex-direction:column; gap:4px;">
            <label style="font-size:12px; font-weight:700; color:#0f172a;">Filter by Subject</label>
            <select name="filter_course" style="height:36px; padding:6px 10px; border-radius:8px; border:1px solid #e5e7eb;">
              <option value="">-- All Courses --</option>
              <?php foreach ($coursesForFilter as $c): ?>
                <option value="<?= (int)$c['course_id'] ?>" <?= $filterCourse === (int)$c['course_id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($c['course_code']) ?> - <?= htmlspecialchars($c['course_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" style="height:36px; padding:0 16px; border-radius:8px; background:#0ea5a4; color:#fff; font-weight:700; border:none; cursor:pointer;">Apply Filters</button>
          <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" style="height:36px; padding:0 16px; border-radius:8px; background:#fff; color:#0f172a; font-weight:700; border:1px solid #e5e7eb; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center;">Clear</a>
        </div>
      </form>
      <?php if (count($sessions) > 0): ?>
        <div class="table-wrap" style="margin-top:0;">
          <div class="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Course Code</th>
                  <th>Room</th>
                  <th>Date</th>
                  <th>Start</th>
                  <th>End</th>
                  <th>Grace</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($sessions as $s): ?>
                  <?php $isOpen = ($s['status'] === 'scheduled'); ?>
                  <tr>
                    <td class="mono"><?= (int)$s['session_id'] ?></td>
                    <td class="mono"><?= htmlspecialchars($s['course_code']) ?></td>
                    <td><?= htmlspecialchars($s['room_name']) ?></td>
                    <td class="mono"><?= htmlspecialchars($s['session_date']) ?></td>
                    <td class="mono"><?= htmlspecialchars($s['start_time']) ?></td>
                    <td class="mono"><?= htmlspecialchars($s['end_time']) ?></td>
                    <td class="mono"><?= (int)$s['grace_minutes'] ?></td>
                    <td>
                      <span class="status <?= $isOpen ? 'status-open' : 'status-closed' ?>">
                        <span class="dot"></span>
                        <?= htmlspecialchars($s['status']) ?>
                      </span>
                    </td>
                    <td>
                      <?php 
                      $isPast = $s['session_date'] < date('Y-m-d') || ($s['session_date'] === date('Y-m-d') && $s['end_time'] < date('H:i'));
                      $isCompleted = $s['status'] === 'attendance_generated';
                      ?>
                      <?php if (!$isPast && !$isCompleted): ?>
                        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" style="display:inline;" onsubmit="return confirm('Cancel this session? This cannot be undone.');">
                          <input type="hidden" name="action" value="cancel_session">
                          <input type="hidden" name="session_id" value="<?= (int)$s['session_id'] ?>">
                          <button type="submit" class="btn-ghost btn-danger" style="height:32px; padding:0 10px; font-size:12px;">Cancel</button>
                        </form>
                      <?php else: ?>
                        <span class="muted" style="font-size:11px;"><?= $isPast ? 'Completed' : 'Locked' ?></span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php else: ?>
        <p class="muted" style="text-align:center; padding:32px;">No sessions yet. Create your first session using one of the buttons above.</p>
      <?php endif; ?>
    </div>
  </div>

</div>

</body>
</html>
