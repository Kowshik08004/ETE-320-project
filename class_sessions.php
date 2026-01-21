<?php
session_start();
if (!isset($_SESSION['Admin-name'])) {
    header("location: login.php");
}
require 'connectDB.php';
?>
<!DOCTYPE html>
<html>

<head>
    <title>Class Sessions</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="icon" type="image/png" href="images/favicon.png">
    <link rel="stylesheet" type="text/css" href="css/Users.css">

    <script src="js/jquery-2.2.3.min.js"></script>
    <script src="js/bootstrap.js"></script>
</head>

<body>
    <?php include 'header.php'; ?>

    <main>

        <!-- =====================================================
     CREATE CLASS SESSION
     ===================================================== -->
        <section>

            <div class="form-style-5">
                <form method="POST" action="create_session.php">

                    <!-- Select Class -->
                    <label><b>Class:</b></label>
                    <select name="class_id" required>
                        <option value="">Select Class</option>
                        <?php
                        $res = $conn->query("SELECT * FROM classes ORDER BY class_name, section");
                        while ($c = $res->fetch_assoc()) {
                            echo "<option value='{$c['class_id']}'>
                            {$c['class_name']} - {$c['section']}
                          </option>";
                        }
                        ?>
                    </select>

                    <!-- Select Date -->
                    <label><b>Date:</b></label>
                    <input type="date" name="session_date" required>

                    <!-- Start Time -->
                    <label><b>Start Time:</b></label>
                    <input type="time" name="start_time" value="09:00" required>

                    <!-- End Time -->
                    <label><b>End Time:</b></label>
                    <input type="time" name="end_time" value="10:00" required>

                    <button type="submit">Create Session</button>
                </form>
            </div>
        </section>

        <hr>

        <!-- =====================================================
     LIST CLASS SESSIONS
     ===================================================== -->
        <section>

            <div class="table-responsive slideInRight animated" style="max-height:400px;">
                <table class="table">
                    <thead class="table-primary">
                        <tr>
                            <th>Session ID</th>
                            <th>Class</th>
                            <th>Date</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Attendance</th>
                        </tr>
                    </thead>
                    <tbody class="table-secondary">

                        <?php
                        $sql = "
    SELECT 
    cs.session_id,
    cs.class_id,
    cs.session_date,
    cs.start_time,
    cs.end_time,
    c.class_name,
    c.section
    FROM class_sessions cs
    JOIN classes c ON c.class_id = cs.class_id
    ORDER BY cs.session_date DESC
";
                        $res = $conn->query($sql);

                        while ($row = $res->fetch_assoc()) {
                        ?>
                            <tr>
                                <td><?php echo $row['session_id']; ?></td>
                                <td><?php echo $row['class_name'] . " - " . $row['section']; ?></td>
                                <td><?php echo $row['session_date']; ?></td>
                                <td><?php echo $row['start_time']; ?></td>
                                <td><?php echo $row['end_time']; ?></td>
                                <td>
                                    <!-- Generate Attendance Button -->
                                    <form method="POST" action="generate_attendance.php" style="display:inline;">
                                        <input type="hidden" name="class_id"
                                            value="<?php echo $row['class_id']; ?>">
                                        <input type="hidden" name="session_date"
                                            value="<?php echo $row['session_date']; ?>">
                                        <button type="submit" class="btn btn-success btn-sm">
                                            Generate
                                        </button>
                                    </form>

                                </td>
                            </tr>
                        <?php } ?>

                    </tbody>
                </table>
            </div>
        </section>

    </main>
</body>

</html>