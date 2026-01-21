<?php
// ======================================================
// DELETE STUDENT (FULL CLEAN DELETE)
// ======================================================

require 'connectDB.php';

if (empty($_POST['student_id'])) {
    exit("Student ID missing");
}

$student_id = intval($_POST['student_id']);

// ---------------- START TRANSACTION ----------------
$conn->begin_transaction();

try {

    // 1️⃣ Delete attendance records (if exists)
    $stmt = $conn->prepare("
        DELETE FROM attendance
        WHERE student_id = ?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();

    // 2️⃣ Remove course enrollments
    $stmt = $conn->prepare("
        DELETE FROM student_courses
        WHERE student_id = ?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();

    // 3️⃣ Remove class assignments
    $stmt = $conn->prepare("
        DELETE FROM student_classes
        WHERE student_id = ?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();

    // 4️⃣ Unassign RFID card (VERY IMPORTANT)
    $stmt = $conn->prepare("
        UPDATE rfid_cards
        SET student_id = NULL
        WHERE student_id = ?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();

    // 5️⃣ Delete student
    $stmt = $conn->prepare("
        DELETE FROM students
        WHERE student_id = ?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();

    // ---------------- COMMIT ----------------
    $conn->commit();

    exit("success");

} catch (Exception $e) {

    // ---------------- ROLLBACK ----------------
    $conn->rollback();
    exit("Delete failed");
}
