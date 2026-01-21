<?php
session_start();
if (!isset($_SESSION['Admin-name'])) {
    header("location: login.php");
    exit();
}
require 'connectDB.php';

/* -------- FILTER DEFAULTS -------- */
// Get default course (first one)
$first_course_res = $conn->query("SELECT course_id FROM courses LIMIT 1");
$first_course = $first_course_res->fetch_assoc();
$default_course_id = $first_course['course_id'] ?? '';

// Get department ID for ETE
$ete_res = $conn->query("SELECT department_id FROM departments WHERE department_name = 'ETE' LIMIT 1");
$ete_dept = $ete_res->fetch_assoc();
$default_dept_id = $ete_dept['department_id'] ?? '';

$department_id = $_GET['department_id'] ?? $default_dept_id;
$level          = $_GET['level'] ?? 3;
$term           = $_GET['term'] ?? 2;
$batch          = $_GET['batch'] ?? '';
$course_id      = $_GET['course_id'] ?? $default_course_id;
?>
<!DOCTYPE html>
<html>

<head>
    <title>Attendance Summary</title>
    <meta charset="utf-8">
    <link rel="stylesheet" href="css/Users.css">
    <link rel="stylesheet" href="css/userslog.css">
    <style>
      .device-card {
        background: #fff;
        border-radius: 18px;
        border: 1px solid rgba(0, 0, 0, .08);
        box-shadow: 0 14px 40px rgba(0, 0, 0, .12);
        overflow: hidden;
        margin-bottom: 24px;
      }
      .device-table-wrap {
        max-height: 520px;
        overflow-y: auto;
        overflow-x: auto;
      }
      .device-table {
        width: 100%;
        min-width: 900px;
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
      .filter-card {
        background: #fff;
        border: 1px solid rgba(0, 0, 0, .08);
        box-shadow: 0 10px 28px rgba(0, 0, 0, .10);
        border-radius: 12px;
        padding: 12px 16px;
        margin: 10px 0 24px;
        max-width: 900px;
        margin-left: auto;
        margin-right: auto;
      }
      .filter-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
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
      .filter-grid select {
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
        grid-column: 1/-1;
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
      .export-btn-row {
        display: flex;
        flex-direction: row;
        justify-content: center;
        align-items: center;
        gap: 18px;
        margin: 32px 0 24px 0;
      }
      .export-btn-row a.export-btn {
        background: #fff;
        color: #222;
        border: 1px solid #e5e7eb;
        font-weight: 800;
        border-radius: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        padding: 10px 22px;
        min-width: 120px;
        font-size: 15px;
        text-align: center;
        transition: background 0.2s, color 0.2s, border 0.2s;
      }
      .export-btn-row a.export-btn:hover {
        background: #f1f5f9;
        color: #0ea5a4;
        border: 1.5px solid #0ea5a4;
      }
      @media (max-width: 900px) {
        .filter-card { max-width: 100%; }
        .device-table { min-width: 600px; }
        .filter-grid { grid-template-columns: 1fr 1fr 1fr; }
      }
      @media (max-width: 600px) {
        .export-btn-row { flex-direction: column; gap: 10px; }
        .export-btn-row a.btn { width: 100%; min-width: unset; padding: 10px 0; }
      }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>
    <main>
        <div class="filter-card">
          <form method="GET">
            <div class="filter-grid">
              <div>
                <label>Department</label>
                <select name="department_id">
                  <option value="">All</option>
                  <?php
                  $res = $conn->query("SELECT * FROM departments");
                  while ($d = $res->fetch_assoc()) {
                      $sel = ($department_id == $d['department_id']) ? 'selected' : '';
                      echo "<option value='{$d['department_id']}' $sel>{$d['department_name']}</option>";
                  }
                  ?>
                </select>
              </div>
              <div>
                <label>Level</label>
                <select name="level">
                  <option value="">All</option>
                  <?php for ($i = 1; $i <= 4; $i++): ?>
                    <option value="<?= $i ?>" <?= ($level == $i ? 'selected' : '') ?>>Level <?= $i ?></option>
                  <?php endfor; ?>
                </select>
              </div>
              <div>
                <label>Term</label>
                <select name="term">
                  <option value="">All</option>
                  <option value="1" <?= ($term == 1 ? 'selected' : '') ?>>Term 1</option>
                  <option value="2" <?= ($term == 2 ? 'selected' : '') ?>>Term 2</option>
                </select>
              </div>
              <div>
                <label>Batch</label>
                <select name="batch">
                  <option value="">All</option>
                  <?php for ($i = 30; $i >= 12; $i--): ?>
                    <option value="<?= $i ?>" <?= ($batch == $i ? 'selected' : '') ?>><?= $i ?></option>
                  <?php endfor; ?>
                </select>
              </div>
              <div>
                <label>Course</label>
                <select name="course_id">
                  <option value="">All</option>
                  <?php
                  $res = $conn->query("SELECT course_id, course_code FROM courses");
                  while ($c = $res->fetch_assoc()) {
                      $sel = ($course_id == $c['course_id']) ? 'selected' : '';
                      echo "<option value='{$c['course_id']}' $sel>{$c['course_code']}</option>";
                  }
                  ?>
                </select>
              </div>
              <div class="filter-actions">
                <button class="btn-main" type="submit">Filter</button>
              </div>
            </div>
          </form>
        </div>
        <div class="device-card">
          <div class="device-table-wrap">
            <table class="device-table">
              <thead>
                <tr>
                  <th>Student</th>
                  <th>Course</th>
                  <th>Attended</th>
                  <th>Total</th>
                  <th>Percentage</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $sql = "
                        SELECT *
                        FROM attendance_summary v
                        JOIN students s ON s.student_id = v.student_id
                        JOIN courses c ON c.course_id = v.course_id
                        WHERE 1=1
                        ";

                $params = [];
                $types  = '';

                if ($department_id) {
                    $sql .= " AND s.department_id = ?";
                    $params[] = $department_id;
                    $types .= 'i';
                }
                if ($level) {
                    $sql .= " AND s.level = ?";
                    $params[] = $level;
                    $types .= 'i';
                }
                if ($term) {
                    $sql .= " AND s.term = ?";
                    $params[] = $term;
                    $types .= 'i';
                }
                if ($batch) {
                    $sql .= " AND s.batch = ?";
                    $params[] = $batch;
                    $types .= 's';
                }
                if ($course_id) {
                    $sql .= " AND c.course_id = ?";
                    $params[] = $course_id;
                    $types .= 'i';
                }

                $sql .= " ORDER BY s.roll_no, c.course_code";

                $stmt = $conn->prepare($sql);
                if ($params) {
                    $stmt->bind_param($types, ...$params);
                }
                $stmt->execute();
                $res = $stmt->get_result();

                if ($res->num_rows == 0) {
                    echo "<tr><td colspan='5'>No data found</td></tr>";
                }

                while ($row = $res->fetch_assoc()):
                ?>
                  <tr>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= $row['course_code'] ?></td>
                    <td><?= $row['attended'] ?></td>
                    <td><?= $row['total_sessions'] ?></td>
                    <td><?= $row['percentage'] ?>%</td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="export-btn-row">
          <?php
          $filter_params = [
              'department_id' => $department_id,
              'level' => $level,
              'term' => $term,
              'batch' => $batch,
              'course_id' => $course_id
          ];
          $qs = http_build_query($filter_params);
          ?>
          <a href="export_attendance_pdf.php?<?= $qs ?>" target="_blank" class="btn-ghost export-btn">Export PDF</a>
          <a href="export_attendance_excel.php?<?= $qs ?>" class="btn-ghost export-btn">Export Excel</a>
          <a href="export_attendance_csv.php?<?= $qs ?>" class="btn-ghost export-btn">Export CSV</a>
        </div>
    </main>
</body>

</html>