<?php
session_start();
if (!isset($_SESSION['Admin-name'])) {
    header("location: login.php");
    exit();
}
require 'connectDB.php';

// Handle edit form display
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_class = null;
if ($edit_id > 0) {
    $st = $conn->prepare("SELECT * FROM classes WHERE class_id=? LIMIT 1");
    $st->bind_param("i", $edit_id);
    $st->execute();
    $edit_class = $st->get_result()->fetch_assoc();
    $st->close();
}
// Handle update
if (isset($_POST['update_class'])) {
    $class_id = (int)$_POST['class_id'];
    $class_name = trim($_POST['class_name']);
    $section = trim($_POST['section']);
    if ($class_id && $class_name && $section) {
        $st = $conn->prepare("UPDATE classes SET class_name=?, section=? WHERE class_id=?");
        $st->bind_param("ssi", $class_name, $section, $class_id);
        $st->execute();
        header("Location: classes.php");
        exit();
    }
}
// Handle delete
if (isset($_POST['delete_class_id'])) {
    $class_id = (int)$_POST['delete_class_id'];
    if ($class_id) {
        $st = $conn->prepare("DELETE FROM classes WHERE class_id=?");
        $st->bind_param("i", $class_id);
        $st->execute();
        header("Location: classes.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Manage Classes</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="images/favicon.png">
    <link rel="stylesheet" href="css/manageusers.css">
    <style>
        body {
            background: #f8fafc;
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
            min-width: 600px;
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

        .form-style-5 button {
            border-radius: 18px;
        }
    </style>
    <script src="js/jquery-2.2.3.min.js"></script>
</head>

<body>
    <?php include 'header.php'; ?>
    <main>
        <div class="course-flex-row">
            <div class="device-card"
                style="max-width:420px;flex:1 1 320px; padding:24px 24px 18px 24px; display:flex; flex-direction:column; justify-content:center;">
                <?php if ($edit_class): ?>
                <form method="POST" class="form-style-5"
                    style="display:flex; flex-direction:column; gap:14px; background:none; box-shadow:none; margin:0;">
                    <h3 style="margin-bottom:18px;">Edit Class</h3>
                    <input type="hidden" name="class_id" value="<?= (int)$edit_class['class_id'] ?>">
                    <label><b>Class Name</b></label>
                    <input type="text" name="class_name" required
                        value="<?= htmlspecialchars($edit_class['class_name']) ?>">
                    <label><b>Section</b></label>
                    <input type="text" name="section" required value="<?= htmlspecialchars($edit_class['section']) ?>">
                    <div style="display:flex; gap:10px;">
                        <button type="submit" name="update_class" style="border-radius:18px; flex:1;">Update</button>
                        <button type="button" onclick="window.location.href='classes.php'"
                            style="border-radius:18px; flex:1; background:#e53935; color:#fff; border:none;">Cancel
                        </button>
                    </div>
                </form>
                <?php else: ?>
                <form method="POST" class="form-style-5"
                    style="display:flex; flex-direction:column; gap:14px; background:none; box-shadow:none; margin:0;">
                    <h3 style="margin-bottom:18px;">Add New Class</h3>
                    <label><b>Class Name</b></label>
                    <input type="text" name="class_name" required>
                    <label><b>Section</b></label>
                    <input type="text" name="section" required>
                    <button type="submit" name="add_class">Add Class</button>
                </form>
                <?php
                if (isset($_POST['add_class'])) {
                    $class_name = trim($_POST['class_name']);
                    $section    = trim($_POST['section']);
                    if ($class_name && $section) {
                        $chk = $conn->prepare("SELECT class_id FROM classes WHERE class_name=? AND section=?");
                        $chk->bind_param("ss", $class_name, $section);
                        $chk->execute();
                        $chk->store_result();
                        if ($chk->num_rows > 0) {
                            echo "<p class='alert alert-danger' style='margin-top:10px;color:#000;'>Class already exists</p>";
                        } else {
                            $stmt = $conn->prepare("INSERT INTO classes (class_name, section) VALUES (?, ?)");
                            $stmt->bind_param("ss", $class_name, $section);
                            $stmt->execute();
                            echo "<p class='alert alert-success' style='margin-top:10px;color:#000;'>Class added successfully</p>";
                            echo "<script>setTimeout(function(){ var el = document.querySelector('.alert-success'); if(el){ el.style.display = 'none'; } }, 3000);</script>";
                        }
                    }
                }
                ?>
                <?php endif; ?>
            </div>
            <div class="device-card" style="flex:2 1 0; margin-left:32px;">
                <div class="device-table-wrap">
                    <h2 style="padding:24px 24px 0 24px; margin:0;">Existing Classes</h2>
                    <table class="device-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Class Name</th>
                                <th>Section</th>
                                <th>Edit</th>
                                <th>Delete</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $res = $conn->query("SELECT * FROM classes ORDER BY class_name, section");
                            $i = 1;
                            if ($res->num_rows > 0) {
                                while ($c = $res->fetch_assoc()) {
                                    echo "<tr>
                                        <td>" . ($i++) . "</td>
                                        <td>{$c['class_name']}</td>
                                        <td>{$c['section']}</td>
                                        <td><a href='classes.php?edit={$c['class_id']}' style='display:inline-block;padding:6px 14px;border-radius:8px;background:#2563eb;color:#fff;text-decoration:none;font-weight:900;'>Edit</a></td>
                                        <td>
                                          <form method='POST' action='classes.php' onsubmit='return confirm(\"Delete this class?\");' style='display:inline;'>
                                            <input type='hidden' name='delete_class_id' value='{$c['class_id']}'>
                                            <button type='submit' style='display:inline-block;padding:6px 14px;border-radius:8px;background:#e53935;color:#fff;font-weight:900;border:none;'>Delete</button>
                                          </form>
                                        </td>
                                      </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='5'>No classes added yet</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>

</html>