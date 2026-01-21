<?php
session_start();
if (!isset($_SESSION['Admin-name'])) {
    header("location: login.php");
    exit();
}
require 'connectDB.php';
?>
<!DOCTYPE html>
<html>

<head>
    <title>Course Sessions</title>
    <link rel="stylesheet" href="css/manageusers.css">
</head>

<body>

    <?php include 'header.php'; ?>

    <main>

        <h1>Create Course Session</h1>

        <div class="form-style-5">
            <form method="POST">

                <label>Course</label>
                <select name="course_id" required>
                    <option value="">Select Course</option>
                    <?php
                    $res = $conn->query("
    SELECT course_id, course_code, course_name
    FROM courses
    ORDER BY course_code
");
                    while ($c = $res->fetch_assoc()) {
                        echo "<option value='{$c['course_id']}'>
        {$c['course_code']} - {$c['course_name']}
    </option>";
                    }
                    ?>
                </select>

                <label>Date</label>
                <input type="date" name="session_date" required>

                <label>Start Time</label>
                <input type="time" name="start_time" required>

                <label>End Time</label>
                <input type="time" name="end_time" required>

                <label>Grace Minutes</label>
                <input type="number" name="grace_minutes" value="10" min="0">

                <button type="submit" name="create_session">Create Session</button>
            </form>
        </div>

        <?php
        // ================= BACKEND =================
        if (isset($_POST['create_session'])) {

            $stmt = $conn->prepare("
        INSERT INTO course_sessions
        (course_id, session_date, start_time, end_time, grace_minutes)
        VALUES (?, ?, ?, ?, ?)
    ");

            $stmt->bind_param(
                "isssi",
                $_POST['course_id'],
                $_POST['session_date'],
                $_POST['start_time'],
                $_POST['end_time'],
                $_POST['grace_minutes']
            );

            if ($stmt->execute()) {
                echo "<p class='alert alert-success'>Session created</p>";
            } else {
                echo "<p class='alert alert-danger'>Duplicate / Invalid session</p>";
            }
        }
        ?>

        <hr>

        <h2>Existing Sessions</h2>

        <table class="table">
            <thead>
                <tr>
                    <th>Course</th>
                    <th>Date</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>

                <?php
                $res = $conn->query("
    SELECT cs.*, c.course_code
    FROM course_sessions cs
    JOIN courses c ON c.course_id = cs.course_id
    ORDER BY cs.session_date DESC
");
                while ($r = $res->fetch_assoc()) {
                    echo "<tr>
        <td>{$r['course_code']}</td>
        <td>{$r['session_date']}</td>
        <td>{$r['start_time']}</td>
        <td>{$r['end_time']}</td>
        <td>{$r['status']}</td>
    </tr>";
                }
                ?>

            </tbody>
        </table>

    </main>
</body>

</html>