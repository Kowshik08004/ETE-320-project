<?php
session_start();
if (!isset($_SESSION['Admin-name'])) {
  header("location: login.php");
  exit();
}

require 'connectDB.php';
date_default_timezone_set('Asia/Dhaka');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* =========================================================
   AJAX: return ONLY table HTML (filtered by course + cohort)
========================================================= */
function render_logs_table(mysqli $conn): void
{

  // Defaults as requested
  $dept_name   = $_POST['dept_name']   ?? 'ETE';
  $batch       = $_POST['batch']       ?? '21';
  $level       = $_POST['level']       ?? '3';
  $term        = $_POST['term']        ?? '2';
  $course_code = $_POST['course_code'] ?? 'ETE 313';

  // Search filters
  $search_name   = $_POST['search_name']   ?? '';
  $search_roll   = $_POST['search_roll']   ?? '';
  $filter_status = $_POST['filter_status'] ?? '';

  // Optional date range (default today)
  $date_start = $_POST['date_sel_start'] ?? '';
  $date_end   = $_POST['date_sel_end'] ?? '';

  if ($date_start === '' && $date_end === '') {
    $date_start = date('Y-m-d');
    $date_end   = date('Y-m-d');
  } elseif ($date_start !== '' && $date_end === '') {
    $date_end = $date_start;
  } elseif ($date_start === '' && $date_end !== '') {
    $date_start = $date_end;
  }

  $where  = [];
  $params = [];
  $types  = "";

  // Department filter
  $where[]  = "d.department_name = ?";
  $params[] = $dept_name;
  $types   .= "s";

  // Batch/Level/Term filters (students table)
  $where[]  = "s.batch = ?";
  $params[] = $batch;
  $types   .= "s";

  $where[]  = "s.level = ?";
  $params[] = (int)$level;
  $types   .= "i";

  $where[]  = "s.term = ?";
  $params[] = (int)$term;
  $types   .= "i";

  // Course filter (assumes courses.course_code exists)
  $where[]  = "c.course_code = ?";
  $params[] = $course_code;
  $types   .= "s";

  // Date filter (session date, not attendance time)
  $where[]  = "DATE(cs.session_date) BETWEEN ? AND ?";
  $params[] = $date_start;
  $params[] = $date_end;
  $types   .= "ss";


  // Search by student name
  if ($search_name !== '') {
    $where[]  = "s.name LIKE ?";
    $params[] = "%" . $search_name . "%";
    $types   .= "s";
  }

  // Search by roll number
  if ($search_roll !== '') {
    $where[]  = "s.roll_no LIKE ?";
    $params[] = "%" . $search_roll . "%";
    $types   .= "s";
  }

  // Filter by status (FIXED for ABSENT)
  if ($filter_status !== '') {
    if ($filter_status === 'absent') {
      $where[] = "a.status IS NULL";
    } else {
      $where[]  = "a.status = ?";
      $params[] = $filter_status;
      $types   .= "s";
    }
  }

  $where_sql = "WHERE " . implode(" AND ", $where);

  $sql = "
      SELECT
        COALESCE(a.status, 'absent') AS status,
        a.marked_at,
        s.name,
        s.roll_no,
        d.department_name,
        c.course_code,
        cr.room_name
      FROM students s
      JOIN departments d      ON d.department_id = s.department_id
      JOIN student_courses sc ON sc.student_id = s.student_id
      JOIN courses c          ON c.course_id = sc.course_id
      JOIN course_sessions cs ON cs.course_id = c.course_id
      LEFT JOIN class_rooms cr ON cr.room_id = cs.room_id

      LEFT JOIN attendance a
        ON a.student_id = s.student_id
      AND a.session_id = cs.session_id

      $where_sql
      ORDER BY
        CASE
          WHEN a.status IS NULL THEN 2
          WHEN a.status = 'present' THEN 0
          WHEN a.status = 'late' THEN 1
        END,
        s.roll_no ASC
      LIMIT 500
    ";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();

?>
  <div class="device-card">
    <div class="device-table-wrap">
      <table class="device-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Name</th>
            <th>Student ID</th>
            <th>Dept</th>
            <th>Batch</th>
            <th>L-T</th>
            <th>Course</th>
            <th>Room</th>
            <th>Date</th>
            <th>Time</th>
            <th>Status</th>
          </tr>
        </thead>

        <tbody>
          <?php
          if ($res && $res->num_rows > 0) {
            $i = 1;
            while ($row = $res->fetch_assoc()) {

              $name = $row['name'] ?? 'Unknown';
              $roll = $row['roll_no'];
              if ($roll === null || trim($roll) === "") $roll = '—';

              $dept = $row['department_name'] ?? '—';
              $course = $row['course_code'] ?? '—';
              $room = $row['room_name'] ?? '—';

              $dt = $row['marked_at'];
              $date = $dt ? substr($dt, 0, 10) : '—';
              $time = $dt ? substr($dt, 11, 8) : '—';

              $status = strtoupper($row['status'] ?? '');
              $badgeClass = ($status === 'PRESENT') ? 'ok' : (($status === 'LATE') ? 'warn' : 'bad');

              $lt = htmlspecialchars(((int)($_POST['level'] ?? 3)) . "-" . ((int)($_POST['term'] ?? 2)));
              $batchDisp = htmlspecialchars((string)($_POST['batch'] ?? '21'));

          ?>
              <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo htmlspecialchars($name); ?></td>
                <td><?php echo htmlspecialchars($roll); ?></td>
                <td><?php echo htmlspecialchars($dept); ?></td>
                <td><?php echo $batchDisp; ?></td>
                <td><?php echo $lt; ?></td>
                <td><?php echo htmlspecialchars($course); ?></td>
                <td><?php echo htmlspecialchars($room); ?></td>
                <td><?php echo htmlspecialchars($date); ?></td>
                <td><?php echo htmlspecialchars($time); ?></td>
                <td><span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($status); ?></span></td>
              </tr>
          <?php
            }
          } else {
            echo "<tr><td colspan='11' style='text-align:center; padding:18px;'>No logs found</td></tr>";
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>
<?php
  exit();
}

// AJAX endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] == '1') {
  render_logs_table($conn);
}

/* =========================
   Page (GET): dropdown data
========================= */

// Departments dropdown
$departments = [];
$qDept = $conn->query("SELECT department_name FROM departments ORDER BY department_name");
if ($qDept) while ($r = $qDept->fetch_assoc()) $departments[] = $r['department_name'];

// Course dropdown (course_code)
$courses = [];
$qCourse = $conn->query("SELECT course_code FROM courses ORDER BY course_code");
if ($qCourse) while ($r = $qCourse->fetch_assoc()) $courses[] = $r['course_code'];

// Dropdown options (static)
$batches = ['21', '22', '23', '24', '25', '26'];
$levels  = [1, 2, 3, 4];
$terms   = [1, 2];

// Defaults
$defaultDept   = "ETE";
$defaultBatch  = "21";
$defaultLevel  = "3";
$defaultTerm   = "2";
$defaultCourse = "ETE 313";
?>
<!DOCTYPE html>
<html>

<head>
  <title>Course Attendance Logs</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="stylesheet" type="text/css" href="css/userslog.css">
  <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
  <script type="text/javascript" src="js/bootstrap.js"></script>

  <style>
    .device-card {
      background: #fff;
      border-radius: 18px;
      border: 1px solid rgba(0, 0, 0, .08);
      box-shadow: 0 14px 40px rgba(0, 0, 0, .12);
      overflow: hidden;
    }

    .device-table-wrap {
      max-height: 520px;
      overflow-y: auto;
      overflow-x: auto;
    }

    .device-table {
      width: 100%;
      min-width: 1100px;
      margin: 0;
      border-collapse: separate;
      border-spacing: 0;
    }

    .device-table thead th {
      position: sticky;
      top: 0;
      z-index: 3;
      background: #f1f5f9;
      color: #0f172a;
      font-size: 12px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: .06em;
      padding: 14px 16px;
      text-align: left;
      border-bottom: 2px solid #e5e7eb;
    }

    .device-table td {
      padding: 14px 16px;
      white-space: nowrap;
      color: #0f172a !important;
      font-weight: 600;
      border-bottom: 1px solid #e5e7eb;
    }

    .device-table tbody tr:nth-child(odd) {
      background: #ffffff;
    }

    .device-table tbody tr:nth-child(even) {
      background: #f8fafc;
    }

    .device-table tbody tr:hover {
      background: #e6f4f6;
    }

    .device-table td:first-child {
      font-weight: 900;
      color: #0ea5a4 !important;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 6px 14px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 900;
      letter-spacing: .03em;
      border: 1px solid;
      min-width: 92px;
    }

    .badge.ok {
      background: #ecfdf5;
      color: #065f46;
      border-color: #a7f3d0;
    }

    .badge.warn {
      background: #fff7ed;
      color: #9a3412;
      border-color: #fed7aa;
    }

    .badge.bad {
      background: #fef2f2;
      color: #991b1b;
      border-color: #fecaca;
    }

    .filter-card {
      background: #fff;
      border: 1px solid rgba(0, 0, 0, .08);
      box-shadow: 0 10px 28px rgba(0, 0, 0, .10);
      border-radius: 12px;
      padding: 12px 16px;
      margin: 10px 0 14px;
    }

    .filter-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 18px 18px;
      align-items: end;
    }

    .filter-grid label {
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .04em;
      color: #0f172a;
      margin-bottom: 4px;
      display: block;
    }

    .filter-grid select,
    .filter-grid input {
      width: 100%;
      height: 32px;
      border-radius: 8px;
      border: 1px solid #e5e7eb;
      padding: 6px 8px;
      outline: none;
      font-size: 13px;
    }

    .filter-actions {
      display: flex;
      gap: 10px;
      justify-content: flex-start;
    }

    .btn-main {
      height: 44px;
      border: none;
      border-radius: 20px;
      padding: 0 16px;
      background: #0ea5a4;
      color: #fff;
      font-weight: 900;
      cursor: pointer;
    }

    .btn-ghost {
      height: 44px;
      border-radius: 20px;
      padding: 0 16px;
      background: #fff;
      border: 1px solid #e5e7eb;
      font-weight: 800;
      cursor: pointer;
    }

    @media (max-width: 1100px) {
      .filter-grid {
        grid-template-columns: 1fr 1fr;
      }

      .filter-actions {
        grid-column: 1/-1;
        justify-content: stretch;
      }

      .btn-main,
      .btn-ghost {
        width: 100%;
      }

      .device-table {
        min-width: 980px;
      }
    }
  </style>

  <script>
    function loadLogs() {
      $.ajax({
        url: "userlog.php",
        type: "POST",
        data: {
          ajax: 1,
          dept_name: $("#dept_name").val(),
          batch: $("#batch").val(),
          level: $("#level").val(),
          term: $("#term").val(),
          course_code: $("#course_code").val(),
          date_sel_start: $("#date_sel_start").val(),
          date_sel_end: $("#date_sel_end").val(),
          search_name: $("#search_name").val(),
          search_roll: $("#search_roll").val(),
          filter_status: $("#filter_status").val()
        }
      }).done(function(data) {
        $("#userslog").html(data);
      });
    }

    $(document).ready(function() {
      // set defaults
      $("#dept_name").val("<?php echo htmlspecialchars($defaultDept); ?>");
      $("#batch").val("<?php echo htmlspecialchars($defaultBatch); ?>");
      $("#level").val("<?php echo htmlspecialchars($defaultLevel); ?>");
      $("#term").val("<?php echo htmlspecialchars($defaultTerm); ?>");
      $("#course_code").val("<?php echo htmlspecialchars($defaultCourse); ?>");

      loadLogs();
      setInterval(loadLogs, 5000);

      $("#apply_filter").on("click", function() {
        loadLogs();
      });

      $("#reset_filter").on("click", function() {
        $("#dept_name").val("<?php echo htmlspecialchars($defaultDept); ?>");
        $("#batch").val("<?php echo htmlspecialchars($defaultBatch); ?>");
        $("#level").val("<?php echo htmlspecialchars($defaultLevel); ?>");
        $("#term").val("<?php echo htmlspecialchars($defaultTerm); ?>");
        $("#course_code").val("<?php echo htmlspecialchars($defaultCourse); ?>");
        $("#date_sel_start").val("");
        $("#date_sel_end").val("");
        $("#search_name").val("");
        $("#search_roll").val("");
        $("#filter_status").val("");
        loadLogs();
      });
    });
  </script>
</head>

<body>
  <?php include 'header.php'; ?>

  <section class="container py-lg-5">

    <!-- NEW FILTER -->
    <div class="filter-card">
      <div class="filter-grid">

        <div>
          <label>Department</label>
          <select id="dept_name">
            <?php foreach ($departments as $dn): ?>
              <option value="<?php echo htmlspecialchars($dn); ?>"><?php echo htmlspecialchars($dn); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>Batch</label>
          <select id="batch">
            <?php foreach ($batches as $b): ?>
              <option value="<?php echo htmlspecialchars($b); ?>"><?php echo htmlspecialchars($b); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>Level</label>
          <select id="level">
            <?php foreach ($levels as $l): ?>
              <option value="<?php echo (int)$l; ?>"><?php echo (int)$l; ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>Term</label>
          <select id="term">
            <?php foreach ($terms as $t): ?>
              <option value="<?php echo (int)$t; ?>"><?php echo (int)$t; ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>Course Code</label>
          <select id="course_code">
            <?php foreach ($courses as $cc): ?>
              <option value="<?php echo htmlspecialchars($cc); ?>"><?php echo htmlspecialchars($cc); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>From Date</label>
          <input type="date" id="date_sel_start">
        </div>

        <div>
          <label>To Date</label>
          <input type="date" id="date_sel_end">
        </div>

        <div>
          <label>Search by Name</label>
          <input type="text" id="search_name" placeholder="Student name...">
        </div>

        <div>
          <label>Search by Roll No</label>
          <input type="text" id="search_roll" placeholder="Roll number...">
        </div>

        <div>
          <label>Filter by Status</label>
          <select id="filter_status">
            <option value="">All Status</option>
            <option value="present">Present</option>
            <option value="late">Late</option>
            <option value="absent">Absent</option>
          </select>
        </div>

        <div class="filter-actions" style="grid-column: 1 / -1;">
          <button class="btn-ghost" type="button" id="reset_filter">Reset</button>
          <button class="btn-main" type="button" id="apply_filter">Apply</button>
        </div>

      </div>
    </div>

    <div class="slideInRight animated">
      <div id="userslog"></div>
    </div>
  </section>

</body>

</html>