<?php
// ---------------- DATABASE CONNECTION ----------------
// This file handles ADD / UPDATE / DELETE of students
require 'connectDB.php';


/* =====================================================
   ADD STUDENT
   ===================================================== */
if (isset($_POST['Add'])) {

    // ----------- Data received from AJAX (manage_users.js) -----------
    $user_id   = $_POST['user_id'];   // hidden field (used during update)
    $Uname     = $_POST['name'];      // student name
    $Number    = $_POST['number'];    // roll / student ID
    $Email     = $_POST['email'];     // email (optional)
    $Gender    = $_POST['gender'];    // Male / Female
    $class_id  = $_POST['class_id'];  // NEW: class assignment

    // ----------- Basic validation -----------
    if (empty($Uname) || empty($Number) || empty($class_id)) {
        echo "Empty Fields";
        exit();
    }

    // ----------- Check if roll number already exists -----------
    $sql = "SELECT student_id FROM students WHERE roll_no=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $Number);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        echo "This Roll Number already exists!";
        exit();
    }

    // ----------- Insert student into STUDENTS table -----------
    $sql = "
        INSERT INTO students (name, roll_no, gender, email, created_at)
        VALUES (?, ?, ?, ?, CURDATE())
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $Uname, $Number, $Gender, $Email);
    $stmt->execute();

    // Get newly created student ID
    $student_id = $stmt->insert_id;

    /* -----------------------------------------------------
       CLASS ASSIGNMENT (CORE PART OF CLASS-WISE REGISTRATION)
       ----------------------------------------------------- */

    $academic_year = "2025";

    // Safety: make sure no other active class exists
    $sql = "
        UPDATE student_classes
        SET status='promoted'
        WHERE student_id=? AND status='active'
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();

    // Assign student to selected class
    $sql = "
        INSERT INTO student_classes (student_id, class_id, academic_year, status)
        VALUES (?, ?, ?, 'active')
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $student_id, $class_id, $academic_year);
    $stmt->execute();

    // Success response
    echo 1;
    exit();
}


/* =====================================================
   UPDATE STUDENT
   ===================================================== */
if (isset($_POST['Update'])) {

    $user_id  = $_POST['user_id'];
    $Uname    = $_POST['name'];
    $Number   = $_POST['number'];
    $Email    = $_POST['email'];
    $Gender   = $_POST['gender'];
    $class_id = $_POST['class_id'];

    if (empty($user_id)) {
        echo "No student selected";
        exit();
    }

    // ----------- Update STUDENTS table -----------
    $sql = "
        UPDATE students
        SET name=?, roll_no=?, gender=?, email=?
        WHERE student_id=?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssi", $Uname, $Number, $Gender, $Email, $user_id);
    $stmt->execute();

    // ----------- Update class assignment -----------
    // Close old active class
    $sql = "
        UPDATE student_classes
        SET status='promoted'
        WHERE student_id=? AND status='active'
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    // Insert new active class
    $sql = "
        INSERT INTO student_classes (student_id, class_id, academic_year, status)
        VALUES (?, ?, '2025', 'active')
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $class_id);
    $stmt->execute();

    echo 1;
    exit();
}


/* =====================================================
   SELECT STUDENT (USED WHEN CLICKING A ROW)
   ===================================================== */
if (isset($_GET['select'])) {

    $student_id = $_GET['student_id'];

    $sql = "
        SELECT s.*, sc.class_id
        FROM students s
        LEFT JOIN student_classes sc
            ON sc.student_id = s.student_id
           AND sc.status='active'
        WHERE s.student_id=?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();

    header('Content-Type: application/json');
    echo json_encode($res->fetch_assoc());
    exit();
}


/* =====================================================
   DELETE STUDENT
   ===================================================== */
if (isset($_POST['delete'])) {

    $student_id = $_POST['user_id'];

    if (empty($student_id)) {
        echo "No student selected";
        exit();
    }

    // Remove student (attendance logs remain as history)
    $sql = "DELETE FROM students WHERE student_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();

    echo 1;
    exit();
}
?>
