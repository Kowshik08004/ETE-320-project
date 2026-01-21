<?php
// -------- SESSION CHECK --------
session_start();
if (!isset($_SESSION['Admin-name'])) {
  header("location: login.php");
  exit();
}

// -------- DATABASE CONNECTION --------
require 'connectDB.php';
?>
<!DOCTYPE html>
<html>

<head>
  <title>Students</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="icon" type="image/png" href="images/favicon.png">

  <!-- JS & CSS -->
  <script src="js/jquery-2.2.3.min.js"></script>
  <script src="js/bootstrap.js"></script>

  <link rel="stylesheet" type="text/css" href="css/Users.css">
  <style>
    .device-card {
      background: #fff;
      border-radius: 18px;
      border: 1px solid rgba(0, 0, 0, .08);
      box-shadow: 0 14px 40px rgba(0, 0, 0, .12);
      overflow: hidden;
      margin-bottom: 32px;
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
    @media (max-width: 1100px) {
      .device-table {
        min-width: 980px;
      }
    }
  </style>

  <!-- UI scroll fix -->
  <script>
    $(window).on("load resize", function() {
      var scrollWidth = $('.tbl-content').width() - $('.tbl-content table').width();
      $('.tbl-header').css({
        'padding-right': scrollWidth
      });
    }).resize();
  </script>
</head>

<body>

  <?php include 'header.php'; ?>

  <main>
    <section>

      <!-- ===================== FILTER CARD (Userlog Style) ===================== -->
      <div class="filter-card">
        <form method="GET">
          <div class="filter-grid">
            <div>
              <label>Department</label>
              <select name="department_id">
                <?php
                $selected_dept = $_GET['department_id'] ?? null;
                $deptRes = mysqli_query($conn, "SELECT * FROM departments ORDER BY department_name");
                while ($d = mysqli_fetch_assoc($deptRes)) {
                  $selected = '';
                  if ($selected_dept == $d['department_id']) {
                    $selected = 'selected';
                  } elseif (!$selected_dept && $d['department_name'] === 'ETE') {
                    $selected = 'selected';
                    $selected_dept = $d['department_id'];
                  }
                  echo "<option value='{$d['department_id']}' $selected>{$d['department_name']}</option>";
                }
                ?>
              </select>
            </div>
            <div>
              <label>Batch</label>
              <select name="batch">
                <?php
                $selected_batch = $_GET['batch'] ?? 21;
                for ($i = 12; $i <= 30; $i++) {
                  $sel = ($i == $selected_batch) ? 'selected' : '';
                  echo "<option value='$i' $sel>$i</option>";
                }
                ?>
              </select>
            </div>
            <div class="filter-actions" style="grid-column: 1 / -1;">
              <button class="btn-ghost" type="reset">Reset</button>
              <button class="btn-main" type="submit">Apply</button>
            </div>
          </div>
        </form>
      </div>
  <style>
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
      grid-template-columns: repeat(2, 1fr);
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
        grid-template-columns: 1fr;
      }
      .filter-actions {
        grid-column: 1/-1;
        justify-content: stretch;
      }
      .btn-main,
      .btn-ghost {
        width: 100%;
      }
    }
  </style>

      <!-- =====================================================
     STUDENT TABLE
     ===================================================== -->
      <div class="device-card">
        <div class="device-table-wrap">
          <table class="device-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Name</th>
                <th>Student ID</th>
                <th>Mobile</th>
                <th>Gender</th>
                <th>Card UID</th>
                <th>Department</th>
                <th>Batch</th>
                <th>L-T</th>
                <th>Registered</th>
              </tr>
            </thead>
            <tbody>

            <?php
            // =====================================================
            // DATA QUERY (FILTERED)
            // =====================================================

            // Safety fallback (should never be null)
            if (!$selected_dept) {
              echo "<tr><td colspan='8'>Department not found</td></tr>";
              exit();
            }

            $sql = "
                    SELECT
                        s.student_id,
                        s.name,
                        s.mobile,
                        s.roll_no,
                        s.gender,
                        s.created_at,
                        s.batch,
                        s.level,
                        s.term,
                        r.card_uid,
                        d.department_name,
                        s.department_id
                    FROM students s
                    JOIN departments d ON d.department_id = s.department_id
                    LEFT JOIN rfid_cards r ON r.student_id = s.student_id
                    WHERE s.department_id = ?
                      AND s.batch = ?
                    ORDER BY s.roll_no ASC
                ";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $selected_dept, $selected_batch);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows > 0) {
              $i = 1;
              while ($row = $res->fetch_assoc()) {
                $lt = "L" . (int)$row['level'] . "-T" . (int)$row['term'];
            ?>
                <tr class="student-row"
                  data-id="<?php echo $row['student_id']; ?>"
                  data-roll="<?php echo $row['roll_no']; ?>"
                  data-name="<?php echo htmlspecialchars($row['name']); ?>"
                  data-mobile="<?php echo htmlspecialchars($row['mobile']); ?>"
                  data-gender="<?php echo $row['gender']; ?>"
                  data-card="<?php echo $row['card_uid']; ?>"
                  data-department="<?php echo $row['department_name']; ?>"
                  data-deptid="<?php echo $row['department_id']; ?>"
                  data-batch="<?php echo $row['batch']; ?>"
                  data-level="<?php echo $row['level']; ?>"
                  data-term="<?php echo $row['term']; ?>"
                  data-date="<?php echo $row['created_at']; ?>">
                  <td><?php echo $i++; ?></td>
                  <td><?php echo htmlspecialchars($row['name']); ?></td>
                  <td><?php echo $row['roll_no']; ?></td>
                  <td><?php echo htmlspecialchars($row['mobile']); ?></td>
                  <td><?php echo $row['gender']; ?></td>
                  <td><?php echo $row['card_uid'] ?? '—'; ?></td>
                  <td><?php echo $row['department_name']; ?></td>
                  <td><?php echo $row['batch']; ?></td>
                  <td><?php echo $lt; ?></td>
                  <td><?php echo $row['created_at']; ?></td>
                </tr>
            <?php
              }
            } else {
              echo "<tr><td colspan='10' style='text-align:center; padding:18px;'>No students found</td></tr>";
            }
            ?>

          </tbody>
        </table>
      </div>

    </section>
  </main>

  <!-- ================= STUDENT INFO MODAL ================= -->
  <div class="modal fade" id="studentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">

        <div class="modal-header">
          <h4 class="modal-title">Student Information</h4>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>

        <div class="modal-body">
          <p><b>Student ID:</b> <span id="m_roll"></span></p>
          <p><b>Name:</b> <span id="m_name"></span></p>
          <p><b>Mobile:</b> <span id="m_mobile"></span></p>
          <p><b>Gender:</b> <span id="m_gender"></span></p>
          <p><b>Card UID:</b> <span id="m_card"></span></p>
          <p><b>Department:</b> <span id="m_department"></span></p>
          <p><b>Batch:</b> <span id="m_batch"></span></p>
          <p><b>Level / Term:</b> <span id="m_level_term"></span></p>
          <p><b>Registered:</b> <span id="m_date"></span></p>
          <hr>
          <h4>Enrolled Courses</h4>

          <table class="table table-bordered">
            <thead class="table-primary">
              <tr>
                <th>Course Code</th>
                <th>Course Title</th>
              </tr>
            </thead>
            <tbody id="course_table">
              <tr>
                <td colspan="2">Loading...</td>
              </tr>
            </tbody>
          </table>

        </div>

        <div class="modal-footer">
          <button id="editStudent" class="btn btn-warning">Edit</button>
          <button id="deleteStudent" class="btn btn-danger">Delete</button>
          <button class="btn btn-secondary" data-dismiss="modal">Close</button>
        </div>

      </div>
    </div>
  </div>

  <!-- ================= DELETE CONFIRM MODAL ================= -->
  <div class="modal fade" id="deleteConfirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">

        <div class="modal-header bg-danger text-white">
          <h4 class="modal-title">Confirm Deletion</h4>
          <button type="button" class="close text-white" data-dismiss="modal">
            &times;
          </button>
        </div>

        <div class="modal-body">
          <p style="font-size:16px;">
            This will <b>permanently delete</b> the student and:
          </p>
          <ul>
            <li>Remove student record</li>
            <li>Unassign RFID card</li>
            <li>Remove course enrollments</li>
            <li>Delete attendance data</li>
          </ul>

          <p class="text-danger">
            This action <b>cannot be undone</b>.
          </p>
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary" data-dismiss="modal">
            Cancel
          </button>
          <button class="btn btn-danger" id="confirmDeleteStudent">
            Yes, Delete
          </button>
        </div>

      </div>
    </div>
  </div>

  <script src="js/students_popup.js"></script>
  <script>
    // Show mobile in modal
    $(document).on('click', '.student-row', function() {
      $('#m_mobile').text($(this).data('mobile'));
    });
  </script>

</body>

</html>