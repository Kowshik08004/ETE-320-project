<?php
require 'connectDB.php';
date_default_timezone_set('Asia/Dhaka');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$today = date("Y-m-d");
$now   = date("H:i:s");

function respond(array $arr): void {
    foreach ($arr as $k => $v) {
        echo $k . ":" . $v . "\n";
    }
    exit;
}

// ================== INPUT ==================
if (!isset($_GET['card_uid'], $_GET['device_token'])) {
    respond(["RESULT" => "INVALID_REQUEST"]);
}

$card_uid   = trim($_GET['card_uid']);
$device_uid = trim($_GET['device_token']);

if ($card_uid === "" || $device_uid === "") {
    respond(["RESULT" => "INVALID_REQUEST"]);
}

// ================== DEVICE CHECK ==================
$stmt = $conn->prepare("
    SELECT device_mode
    FROM devices
    WHERE device_uid = ?
    LIMIT 1
");
$stmt->bind_param("s", $device_uid);
$stmt->execute();
$device = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$device) {
    respond(["RESULT" => "INVALID_DEVICE"]);
}

// IMPORTANT: MySQL returns strings, so cast to int
$device_mode = (int)$device['device_mode'];

// ==================================================
// ================= ENROLLMENT MODE =================
// ==================================================
if ($device_mode === 0) {

    // Check if card exists already
    $stmt = $conn->prepare("
        SELECT card_uid, student_id, status
        FROM rfid_cards
        WHERE card_uid = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $card_uid);
    $stmt->execute();
    $card = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($card) {
        // If card exists but was inactive, reactivate it
        if (isset($card['status']) && $card['status'] !== 'active') {
            $up = $conn->prepare("
                UPDATE rfid_cards
                SET status='active'
                WHERE card_uid = ?
                LIMIT 1
            ");
            $up->bind_param("s", $card_uid);
            $up->execute();
            $up->close();
        }

        // If already linked to a student -> don't enroll again
        if (!empty($card['student_id'])) {
            respond([
                "UID"    => $card_uid,
                "RESULT" => "already_registered"
            ]);
        }

        // Exists but not linked to any student -> available for linking
        respond([
            "UID"    => $card_uid,
            "RESULT" => "available"
        ]);
    }

    // Insert new card (student_id NULL)
    $stmt = $conn->prepare("
        INSERT INTO rfid_cards (card_uid, student_id, status)
        VALUES (?, NULL, 'active')
    ");
    $stmt->bind_param("s", $card_uid);
    $stmt->execute();
    $stmt->close();

    respond(["UID" => $card_uid, "RESULT" => "successful"]);
}

// ==================================================
// ================= ATTENDANCE MODE =================
// ==================================================

// -------- CARD → STUDENT (+ department name) --------
$stmt = $conn->prepare("
    SELECT
        s.student_id,
        s.name,
        s.roll_no,
        d.department_name
    FROM rfid_cards r
    JOIN students s      ON s.student_id = r.student_id
    JOIN departments d   ON d.department_id = s.department_id
    WHERE r.card_uid = ?
      AND r.status = 'active'
      AND s.status = 'active'
    LIMIT 1
");
$stmt->bind_param("s", $card_uid);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    respond([
        "UID"    => $card_uid,
        "NAME"   => "Unknown",
        "ID"     => "Unknown",
        "DEPT"   => "Unknown",
        "RESULT" => "NOT_REGISTERED"
    ]);
}

$student_id = (int)$student['student_id'];

$name = trim((string)$student['name']);
$name = ($name !== "") ? $name : "Unknown";

$roll = isset($student['roll_no']) ? trim((string)$student['roll_no']) : "";
$id_display = ($roll !== "") ? $roll : (string)$student_id;

$dept = trim((string)$student['department_name']);
$dept = ($dept !== "") ? $dept : "Unknown";

// -------- DEVICE → ROOM --------
$stmt = $conn->prepare("
    SELECT room_id
    FROM room_devices
    WHERE device_uid = ?
    LIMIT 1
");
$stmt->bind_param("s", $device_uid);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$room) {
    respond([
        "UID"    => $card_uid,
        "NAME"   => $name,
        "ID"     => $id_display,
        "DEPT"   => $dept,
        "RESULT" => "DEVICE_NOT_ASSIGNED_TO_ROOM"
    ]);
}

$room_id = (int)$room['room_id'];

// -------- FIND ACTIVE SESSION --------
// Allow attendance 10 minutes BEFORE session starts (pre-session buffer)
$stmt = $conn->prepare("
    SELECT 
        cs.session_id,
        cs.start_time,
        COALESCE(cs.grace_minutes, 10) AS grace_minutes
    FROM course_sessions cs
    WHERE cs.session_date = ?
      AND cs.room_id = ?
      AND cs.status = 'scheduled'
      AND ? BETWEEN SUBTIME(cs.start_time, '00:10:00') AND cs.end_time
    LIMIT 1
");
$stmt->bind_param("sis", $today, $room_id, $now);
$stmt->execute();
$session = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$session) {
    respond([
        "UID"    => $card_uid,
        "NAME"   => $name,
        "ID"     => $id_display,
        "DEPT"   => $dept,
        "RESULT" => "NO_ACTIVE_SESSION"
    ]);
}

$session_id = (int)$session['session_id'];
$start_time = $session['start_time'];
$grace_min  = (int)$session['grace_minutes'];

// -------- PREVENT DOUBLE SCAN --------
$stmt = $conn->prepare("
    SELECT 1
    FROM attendance
    WHERE session_id = ?
      AND student_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $session_id, $student_id);
$stmt->execute();
$already = $stmt->get_result()->num_rows > 0;
$stmt->close();

if ($already) {
    respond([
        "UID"    => $card_uid,
        "NAME"   => $name,
        "ID"     => $id_display,
        "DEPT"   => $dept,
        "RESULT" => "ALREADY_MARKED"
    ]);
}

// -------- DETERMINE STATUS --------
$late_threshold = date("H:i:s", strtotime($start_time . " +{$grace_min} minutes"));
$status = ($now <= $late_threshold) ? 'present' : 'late';

// -------- INSERT ATTENDANCE --------
$marked_at = date("Y-m-d H:i:s");

$stmt = $conn->prepare("
    INSERT INTO attendance (session_id, student_id, status, marked_at)
    VALUES (?, ?, ?, ?)
");
$stmt->bind_param("iiss", $session_id, $student_id, $status, $marked_at);
$stmt->execute();
$stmt->close();

respond([
    "UID"    => $card_uid,
    "NAME"   => $name,
    "ID"     => $id_display,
    "DEPT"   => $dept,
    "RESULT" => "ATTENDANCE_OK_" . strtoupper($status)
]);
