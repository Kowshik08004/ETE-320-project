<?php
session_start();
if (!isset($_SESSION['Admin-name'])) {
    header("location: login.php");
    exit();
}
require 'connectDB.php';

/* ---------- helpers ---------- */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function flash($msg, $type="info"){
    $_SESSION['flash'] = ["msg"=>$msg, "type"=>$type];
}
function get_flash(){
    if(!isset($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- fetch departments ---------- */
$departments = [];
$resDep = mysqli_query($conn, "SELECT department_id, department_name FROM departments ORDER BY department_name");
while ($d = mysqli_fetch_assoc($resDep)) $departments[] = $d;

/* ---------- edit target ---------- */
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_course = null;

if ($edit_id > 0) {
    $st = $conn->prepare("
        SELECT course_id, course_code, course_name, credit, department_id, level, term
        FROM courses
        WHERE course_id = ?
        LIMIT 1
    ");
    $st->bind_param("i", $edit_id);
    $st->execute();
    $edit_course = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$edit_course) {
        flash("Course not found.", "danger");
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    }
}

/* ---------- ADD COURSE ---------- */
if (isset($_POST['add_course'])) {

    $course_code   = trim($_POST['course_code'] ?? '');
    $course_name   = trim($_POST['course_name'] ?? '');
    $credit        = (float)($_POST['credit'] ?? 0);
    $department_id = (int)$_POST['department_id'] ?? 0;
    $level         = (int)$_POST['level'] ?? 0;
    $term          = (int)$_POST['term'] ?? 0;

    if ($course_code === '' || $course_name === '' || $credit <= 0 || $department_id<=0 || $level<=0 || $term<=0) {
        flash("Missing/invalid data.", "danger");
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    }

    $chk = $conn->prepare("SELECT course_id FROM courses WHERE course_code = ? LIMIT 1");
    $chk->bind_param("s", $course_code);
    $chk->execute();
    $exists = $chk->get_result()->num_rows > 0;
    $chk->close();

    if ($exists) {
        flash("Course code already exists.", "danger");
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    }

    $stmt = $conn->prepare("
        INSERT INTO courses (course_code, course_name, credit, department_id, level, term)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssdiii", $course_code, $course_name, $credit, $department_id, $level, $term);

    if ($stmt->execute()) flash("Course added successfully.", "success");
    else flash("Failed to add course.", "danger");
    $stmt->close();

    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

/* ---------- UPDATE COURSE ---------- */
if (isset($_POST['update_course'])) {

    $course_id     = (int)$_POST['course_id'] ?? 0;
    $course_code   = trim($_POST['course_code'] ?? '');
    $course_name   = trim($_POST['course_name'] ?? '');
    $credit        = (float)$_POST['credit'] ?? 0;
    $department_id = (int)$_POST['department_id'] ?? 0;
    $level         = (int)$_POST['level'] ?? 0;
    $term          = (int)$_POST['term'] ?? 0;

    if ($course_id<=0 || $course_code === '' || $course_name === '' || $credit <= 0 || $department_id<=0 || $level<=0 || $term<=0) {
        flash("Missing/invalid data.", "danger");
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    }

    $chk = $conn->prepare("
        SELECT course_id
        FROM courses
        WHERE course_code = ?
          AND course_id <> ?
        LIMIT 1
    ");
    $chk->bind_param("si", $course_code, $course_id);
    $chk->execute();
    $exists = $chk->get_result()->num_rows > 0;
    $chk->close();

    if ($exists) {
        flash("Course code already used by another course.", "danger");
        header("Location: ".$_SERVER['PHP_SELF']."?edit=".$course_id);
        exit();
    }

    $stmt = $conn->prepare("
        UPDATE courses
        SET course_code = ?, course_name = ?, credit = ?, department_id = ?, level = ?, term = ?
        WHERE course_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ssdiiii", $course_code, $course_name, $credit, $department_id, $level, $term, $course_id);

    if ($stmt->execute()) flash("Course updated successfully.", "success");
    else flash("Failed to update course.", "danger");
    $stmt->close();

    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

/* ---------- course list ---------- */
$sql = "
    SELECT
        c.course_id,
        c.course_code,
        c.course_name,
        c.credit,
        d.department_name,
        c.department_id,
        c.level,
        c.term
    FROM courses c
    JOIN departments d ON d.department_id = c.department_id
    ORDER BY d.department_name, c.level, c.term, c.course_code
";
$res = mysqli_query($conn, $sql);

$flash = get_flash();

/* ---------- form defaults ---------- */
$form_mode = ($edit_course ? "edit" : "add");
$val_department_id = $edit_course ? (int)$edit_course['department_id'] : '';
$val_level         = $edit_course ? (int)$edit_course['level'] : '';
$val_term          = $edit_course ? (int)$edit_course['term'] : '';
$val_course_code   = $edit_course ? $edit_course['course_code'] : '';
$val_course_name   = $edit_course ? $edit_course['course_name'] : '';
$val_credit        = $edit_course ? (string)$edit_course['credit'] : '';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Course Setup</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="images/favicon.png">
    <link rel="stylesheet" href="css/manageusers.css">
    <script src="js/jquery-2.2.3.min.js"></script>

    <style>
        /* Fix overlap: ensure main content has spacing + form has bottom margin */
        main{ padding-bottom: 30px; }
        .form-style-5{ margin-bottom: 26px; }
        .actions-row{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-top:14px; }
        .btn-secondary{
            background:#64748b; color:#fff; border:none; padding:10px 14px; border-radius:10px;
            cursor:pointer; font-weight:800; text-decoration:none; display:inline-block;
        }
        .btn-secondary:hover{ filter:brightness(.95); }
        .btn-link{
            display:inline-block; padding:6px 10px; border-radius:10px; text-decoration:none;
            border:1px solid #e5e7eb; font-weight:800;
        }
        .btn-link:hover{ background:#f8fafc; }
        .alert{ padding:10px 12px; border-radius:10px; margin:10px 0 14px; font-weight:700; }
        .alert-success{ background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; }
        .alert-danger{ background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }
        .alert-info{ background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; }
        .small-note{ font-size:12px; color:#64748b; margin-top:8px; }

        /* Add spacing before the "Existing Courses" section so buttons never touch it */
        .course-list{ margin-top: 18px; padding-top: 6px; clear: both; }
        .course-list h2{ margin-top: 0; }

        /* If manageusers.css uses floats anywhere, this prevents overlap */
        .clearfix{ clear: both; height:0; overflow:hidden; }

        @media (max-width: 600px){
            .actions-row{ flex-direction:column; align-items:stretch; }
            .btn-secondary{ text-align:center; width:100%; }
            .actions-row button{ width:100%; }
        }

        .course-flex-row {
            display: flex;
            align-items: stretch;
            justify-content: center;
            gap: 48px;
            margin: 48px 0 40px 0;
            flex-wrap: wrap;
            width: 100%;
            padding-left: 32px;
            padding-right: 32px;
            box-sizing: border-box;
        }

        /* .device-card styles */
        .device-card {
            background: #fff;
            border-radius: 18px;
            border: 1px solid rgba(0, 0, 0, .08);
            box-shadow: 0 14px 40px rgba(0, 0, 0, .12);
            overflow: hidden;
            margin-bottom: 0;
            width: 100%;
        }

        /* .device-table styles */
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
        .btn-link {
            display:inline-block; padding:6px 10px; border-radius:10px; text-decoration:none;
            border:1px solid #e5e7eb; font-weight:800;
            background: #fff;
            color: #0f172a;
            transition: background 0.15s;
        }
        .btn-link:hover{ background:#f8fafc; }
        @media (max-width: 900px) {
            .course-flex-row {
                flex-direction: column;
            }
            .device-card {
                max-width: 100%;
                margin-left: 0 !important;
            }
        }

        .course-btn-same {
          width: 160px !important;
          min-width: 160px !important;
          max-width: 160px !important;
          border-radius: 18px !important;
          text-align: center !important;
          box-sizing: border-box;
        }
        .btn-cancel-edit {
          background: #e53935 !important;
          color: #fff !important;
          border: none !important;
          transition: background 0.18s;
        }
        .btn-cancel-edit:hover {
          background: #b71c1c !important;
          color: #fff !important;
        }
    </style>
</head>

<body>
<?php include 'header.php'; ?>

<main>

        <div class="course-flex-row">
            <div class="device-card" style="max-width:420px;flex:1 1 320px; padding:24px 24px 18px 24px; display:flex; flex-direction:column; justify-content:center;">
                <form method="POST" class="form-style-5" style="display:flex; flex-direction:column; background:none; box-shadow:none; margin:0;">
      <?php if ($flash): ?>
        <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
      <?php endif; ?>
      <?php if ($form_mode === "edit"): ?>
        <input type="hidden" name="course_id" value="<?= (int)$edit_course['course_id'] ?>">
      <?php endif; ?>
      <label><b>Department</b></label>
      <select name="department_id" required>
        <option value="">Select Department</option>
        <?php foreach ($departments as $d): ?>
          <option value="<?= (int)$d['department_id'] ?>"
            <?= ((string)$val_department_id === (string)$d['department_id']) ? 'selected' : '' ?>>
            <?= h($d['department_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <label><b>Level</b></label>
      <select name="level" required>
        <option value="">Select Level</option>
        <?php for ($i=1; $i<=4; $i++): ?>
          <option value="<?= $i ?>" <?= ((string)$val_level === (string)$i) ? 'selected' : '' ?>>
            Level <?= $i ?>
          </option>
        <?php endfor; ?>
      </select>
      <label><b>Term</b></label>
      <select name="term" required>
        <option value="">Select Term</option>
        <option value="1" <?= ((string)$val_term === "1") ? 'selected' : '' ?>>Term 1</option>
        <option value="2" <?= ((string)$val_term === "2") ? 'selected' : '' ?>>Term 2</option>
      </select>
      <label><b>Course Code</b></label>
      <input type="text" name="course_code" placeholder="e.g. ETE-313" required value="<?= h($val_course_code) ?>">
      <label><b>Course Name</b></label>
      <input type="text" name="course_name" placeholder="Course Title" required value="<?= h($val_course_name) ?>">
      <label>Credit</label>
      <select name="credit" required>
        <option value="">Select Credit</option>
        <?php $credits = ["0.75","1.50","3.00","4.00"]; foreach($credits as $cr): ?>
          <option value="<?= h($cr) ?>" <?= ((string)$val_credit === (string)$cr) ? "selected" : "" ?>><?= h($cr) ?> Credit</option>
        <?php endforeach; ?>
      </select>
      <div class="actions-row" style="display:flex; gap:10px; align-items:center;">
        <?php if ($form_mode === "edit"): ?>
          <button type="submit" name="update_course" class="course-btn-same">Update Course</button>
          <button type="button" class="btn-secondary course-btn-same btn-cancel-edit" onclick="window.location.href='<?= h($_SERVER['PHP_SELF']) ?>'">Cancel Edit</button>
        <?php else: ?>
          <button type="submit" name="add_course" style="border-radius:18px;">Add Course</button>
        <?php endif; ?>
      </div>
      <?php if ($form_mode === "edit"): ?>
        <div class="small-note">Editing Course ID: <?= (int)$edit_course['course_id'] ?></div>
      <?php endif; ?>
    </form>
  </div>
      <div class="device-card" style="flex:2 1 0; margin-left:32px;">
        <div class="device-table-wrap">
          <h2 style="padding:24px 24px 0 24px; margin:0;">Existing Courses</h2>
          <table class="device-table">
              <thead>
                  <tr>
                      <th>#</th>
                      <th>Code</th>
                      <th>Name</th>
                      <th>Credit</th>
                      <th>Department</th>
                      <th>Level</th>
                      <th>Term</th>
                      <th>Edit</th>
                  </tr>
              </thead>
              <tbody>
                  <?php $i=1; while ($row = mysqli_fetch_assoc($res)): ?>
                      <tr>
                          <td><?= $i++ ?></td>
                          <td><?= h($row['course_code']) ?></td>
                          <td><?= h($row['course_name']) ?></td>
                          <td><?= h($row['credit']) ?></td>
                          <td><?= h($row['department_name']) ?></td>
                          <td><?= h($row['level']) ?></td>
                          <td><?= h($row['term']) ?></td>
                          <td>
                              <a class="btn-link" href="<?= h($_SERVER['PHP_SELF']) ?>?edit=<?= (int)$row['course_id'] ?>">
                                  Edit
                              </a>
                          </td>
                      </tr>
                  <?php endwhile; ?>
                  <?php if ($i===1): ?>
                      <tr><td colspan="8" style="text-align:center; padding:18px;">No courses found</td></tr>
                  <?php endif; ?>
              </tbody>
          </table>
        </div>
      </div>
    </div>
</main>
</main>
<style>
    .course-flex-row {
        display: flex;
        align-items: stretch;
        justify-content: center;
        gap: 48px;
        margin: 48px 0 40px 0;
        flex-wrap: wrap;
        width: 100%;
        padding-left: 32px;
        padding-right: 32px;
        box-sizing: border-box;
    }
    .device-card {
        background: #fff;
        border-radius: 18px;
        border: 1px solid rgba(0, 0, 0, .08);
        box-shadow: 0 14px 40px rgba(0, 0, 0, .12);
        overflow: hidden;
        margin-bottom: 0;
        width: 100%;
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
    .btn-link {
        display:inline-block; padding:6px 10px; border-radius:10px; text-decoration:none;
        border:1px solid #e5e7eb; font-weight:800;
        background: #fff;
        color: #0f172a;
        transition: background 0.15s;
    }
    .btn-link:hover{ background:#f8fafc; }
    @media (max-width: 900px) {
        .course-flex-row {
            flex-direction: column;
        }
        .device-card {
            max-width: 100%;
            margin-left: 0 !important;
        }
    }
</style>
</body>
</html>
</body>
</html>
