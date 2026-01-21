<?php
// Connect to database
require 'connectDB.php';
session_start();

$output = '';

if (isset($_POST["To_Excel"])) {

    // ---------------- FILTER SETUP (UNCHANGED) ----------------
    if ($_POST['date_sel_start'] != 0) {
        $Start_date = $_POST['date_sel_start'];
        $_SESSION['searchQuery'] = "ul.checkindate='".$Start_date."'";
    } else {
        $Start_date = date("Y-m-d");
        $_SESSION['searchQuery'] = "ul.checkindate='".$Start_date."'";
    }

    if ($_POST['date_sel_end'] != 0) {
        $_SESSION['searchQuery'] =
            "ul.checkindate BETWEEN '".$Start_date."' AND '".$_POST['date_sel_end']."'";
    }

    if ($_POST['time_sel'] == "Time_in") {
        if ($_POST['time_sel_start'] != 0 && $_POST['time_sel_end'] != 0) {
            $_SESSION['searchQuery'] .=
                " AND ul.timein BETWEEN '".$_POST['time_sel_start']."' AND '".$_POST['time_sel_end']."'";
        }
    }

    if ($_POST['time_sel'] == "Time_out") {
        if ($_POST['time_sel_start'] != 0 && $_POST['time_sel_end'] != 0) {
            $_SESSION['searchQuery'] .=
                " AND ul.timeout BETWEEN '".$_POST['time_sel_start']."' AND '".$_POST['time_sel_end']."'";
        }
    }

    if ($_POST['card_sel'] != 0) {
        $_SESSION['searchQuery'] .= " AND ul.card_uid='".$_POST['card_sel']."'";
    }

    if ($_POST['dev_sel'] != 0) {
        $_SESSION['searchQuery'] .= " AND ul.device_uid='".$_POST['dev_sel']."'";
    }

    // ---------------- UPDATED QUERY (JOIN students) ----------------
    $sql = "
        SELECT 
            ul.id,
            ul.card_uid,
            ul.device_uid,
            ul.device_dep,
            ul.checkindate,
            ul.timein,
            ul.timeout,
            ul.serialnumber AS student_id,
            s.name AS student_name
        FROM users_logs ul
        LEFT JOIN students s 
            ON s.student_id = ul.serialnumber
        WHERE ".$_SESSION['searchQuery']."
        ORDER BY ul.id DESC
    ";

    $result = mysqli_query($conn, $sql);

    if ($result && $result->num_rows > 0) {

        $output .= '
            <table border="1">
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Student ID</th>
                <th>Card UID</th>
                <th>Device ID</th>
                <th>Device</th>
                <th>Date</th>
                <th>Time In</th>
                <th>Time Out</th>
              </tr>
        ';

        while ($row = $result->fetch_assoc()) {
            $output .= '
              <tr>
                <td>'.$row['id'].'</td>
                <td>'.($row['student_name'] ?? 'Unknown').'</td>
                <td>'.$row['student_id'].'</td>
                <td>'.$row['card_uid'].'</td>
                <td>'.$row['device_uid'].'</td>
                <td>'.$row['device_dep'].'</td>
                <td>'.$row['checkindate'].'</td>
                <td>'.$row['timein'].'</td>
                <td>'.$row['timeout'].'</td>
              </tr>
            ';
        }

        $output .= '</table>';

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename=User_Log_'.$Start_date.'.xls');

        echo $output;
        exit();
    } else {
        header("Location: UsersLog.php");
        exit();
    }
}
?>
