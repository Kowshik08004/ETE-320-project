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
  header("Location: course_sessions.php");
  exit();
}

$flash = read_flash();
?>

<!DOCTYPE html>
<html>
<head>
  <title>Create Manual Session</title>
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

    @media (max-width:900px){
      h2{ font-size:22px; }
      .card-body{ padding:16px; }
      .card-header{ padding:14px 16px; }
      .form-grid{ grid-template-columns:1fr; }
      .actions{ justify-content:stretch; }
      button{ width:100%; }
    }
  </style>
</head>

<body>
<?php include 'header.php'; ?>

<div class="wrap">

  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
    <h2 style="margin:0;">Create Manual Session</h2>
    <a href="course_sessions.php" style="display:inline-flex; align-items:center; gap:8px; padding:10px 20px; background:#fff; border:2px solid #0ea5a4; border-radius:10px; color:#0ea5a4; text-decoration:none; font-weight:800; font-size:14px; transition:all .2s; box-shadow:0 2px 8px rgba(14,165,164,.15);" onmouseover="this.style.background='#0ea5a4'; this.style.color='#fff';" onmouseout="this.style.background='#fff'; this.style.color='#0ea5a4';">Back to Sessions</a>
  </div>

  <?php if ($flash): ?>
    <div class="msg <?= htmlspecialchars($flash['type']) ?>">
      <?= htmlspecialchars($flash['msg']) ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header">
      <div>
        <h3>Session Details</h3>
        <p class="sub">Create a one-time course session. Sessions saved to <b>course_sessions</b> table.</p>
      </div>
    </div>

    <div class="card-body">
      <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
        <div class="form-grid">

          <div class="field full">
            <label>Course</label>
            <select name="course_id" required>
              <option value="">-- Select Course --</option>
              <?php foreach ($courses as $c): ?>
                <option value="<?= (int)$c['course_id'] ?>">
                  <?= htmlspecialchars($c['course_code']) ?> — <?= htmlspecialchars($c['course_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field full">
            <label>Room</label>
            <select name="room_id" required>
              <option value="">-- Select Room --</option>
              <?php foreach ($rooms as $r): ?>
                <option value="<?= (int)$r['room_id'] ?>">
                  <?= htmlspecialchars($r['room_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label>Date</label>
            <input type="date" name="session_date" required>
          </div>

          <div class="field">
            <label>Grace Minutes</label>
            <input type="number" name="grace_minutes" value="10" min="0" max="60">
          </div>

          <div class="field">
            <label>Start Time</label>
            <input type="time" name="start_time" required>
          </div>

          <div class="field">
            <label>End Time</label>
            <input type="time" name="end_time" required>
          </div>

          <div class="actions">
            <button type="submit">Create Session</button>
          </div>

        </div>
      </form>
    </div>
  </div>

</div>

</body>
</html>
