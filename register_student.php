<?php
// ======================================================
// REGISTER / UPDATE STUDENT + ASSIGN RFID CARD
// ======================================================

require 'connectDB.php';
date_default_timezone_set('Asia/Dhaka');

// ---------------- INPUT VALIDATION ----------------
if (
    empty($_POST['card_uid']) ||
    empty($_POST['name']) ||
    empty($_POST['mobile']) ||
    empty($_POST['roll_no']) ||
    empty($_POST['department_id']) ||
    empty($_POST['batch']) ||
    empty($_POST['level']) ||
    empty($_POST['term']) ||
    empty($_POST['gender'])
) {
    exit("All fields are required");
}

// ---------------- INPUT ASSIGNMENT ----------------
$student_id    = $_POST['student_id'] ?? null;
$card_uid      = trim($_POST['card_uid']);
$name          = trim($_POST['name']);
$mobile        = trim($_POST['mobile']);
$roll_no       = trim($_POST['roll_no']);
$gender        = trim($_POST['gender']);
$department_id = (int)$_POST['department_id'];
$batch         = (int)$_POST['batch'];
$level         = (int)$_POST['level'];
$term          = (int)$_POST['term'];


// ======================================================
// ================= UPDATE MODE ========================
// ======================================================
if (!empty($student_id)) {

    $student_id = (int)$student_id;

    $stmt = $conn->prepare("
        UPDATE students
        SET 
            name = ?,
            mobile = ?,
            roll_no = ?,
            gender = ?,
            department_id = ?,
            batch = ?,
            level = ?,
            term = ?
        WHERE student_id = ?
    ");
    $stmt->bind_param(
        "ssssiiiii",
        $name,
        $mobile,
        $roll_no,
        $gender,
        $department_id,
        $batch,
        $level,
        $term,
        $student_id
    );

    if (!$stmt->execute()) {
        exit("Failed to update student");
    }

    // ======================================================
    // RESET & RE-ENROLL COURSES (ON EDIT)
    // ======================================================

    // Remove old course enrollments
    $stmt = $conn->prepare("
    DELETE FROM student_courses
    WHERE student_id = ?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();

    // Fetch new matching courses
    $stmt = $conn->prepare("
    SELECT course_id
    FROM courses
    WHERE department_id = ?
      AND level = ?
      AND term = ?
    ");
    $stmt->bind_param("iii", $department_id, $level, $term);
    $stmt->execute();
    $courses = $stmt->get_result();

    // Enroll again
    $enroll = $conn->prepare("
    INSERT INTO student_courses (student_id, course_id)
    VALUES (?, ?)
    ");

    while ($c = $courses->fetch_assoc()) {
        $enroll->bind_param("ii", $student_id, $c['course_id']);
        $enroll->execute();
    }


    header("Location: index.php");
    exit();
}


// ======================================================
// ================= INSERT MODE ========================
// ======================================================

// ---------------- CHECK RFID CARD ----------------
$stmt = $conn->prepare("
    SELECT card_uid
    FROM rfid_cards
    WHERE card_uid = ?
      AND student_id IS NULL
      AND status = 'active'
");
$stmt->bind_param("s", $card_uid);
$stmt->execute();

if ($stmt->get_result()->num_rows === 0) {
    exit("Invalid or already assigned card");
}

// ---------------- DUPLICATE ROLL CHECK ----------------
$stmt = $conn->prepare("
    SELECT student_id
    FROM students
    WHERE roll_no = ?
");
$stmt->bind_param("s", $roll_no);
$stmt->execute();

if ($stmt->get_result()->num_rows > 0) {
    exit("Roll number already exists");
}

// ---------------- INSERT STUDENT ----------------
$stmt = $conn->prepare("
    INSERT INTO students
        (name, mobile, roll_no, gender, department_id, batch, level, term, created_at)
    VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, NOW())
");
$stmt->bind_param(
    "ssssiiii",
    $name,
    $mobile,
    $roll_no,
    $gender,
    $department_id,
    $batch,
    $level,
    $term
);

if (!$stmt->execute()) {
    exit("Failed to create student");
}

$student_id = $stmt->insert_id;


// ======================================================
// ============ AUTO COURSE ENROLLMENT ==================
// ======================================================
$stmt = $conn->prepare("
    SELECT course_id
    FROM courses
    WHERE department_id = ?
      AND level = ?
      AND term = ?
");
$stmt->bind_param("iii", $department_id, $level, $term);
$stmt->execute();
$courses = $stmt->get_result();

$enroll = $conn->prepare("
    INSERT INTO student_courses (student_id, course_id)
    VALUES (?, ?)
");

while ($c = $courses->fetch_assoc()) {
    $enroll->bind_param("ii", $student_id, $c['course_id']);
    $enroll->execute();
}


// ======================================================
// ================= ASSIGN RFID ========================
// ======================================================
$stmt = $conn->prepare("
    UPDATE rfid_cards
    SET student_id = ?
    WHERE card_uid = ?
");
$stmt->bind_param("is", $student_id, $card_uid);

if (!$stmt->execute()) {
    exit("Failed to assign RFID card");
}


// ---------------- DONE ----------------
header("Location: index.php");
exit();
