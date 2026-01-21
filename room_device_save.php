<?php
require 'admin_guard.php';

$uid = trim($_POST['device_uid'] ?? '');
$room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;

if ($uid === '' || $room_id <= 0) {
  flash("Missing device or room.", "danger");
  header("Location: room_devices.php"); exit();
}
$uid = substr($uid, 0, 50);

// validate device exists
$vd = $conn->prepare("SELECT 1 FROM devices WHERE device_uid=? LIMIT 1");
$vd->bind_param("s", $uid);
$vd->execute();
$rd = $vd->get_result();
$vd->close();
if (!$rd || $rd->num_rows === 0) {
  flash("Device UID not found in devices table.", "danger");
  header("Location: room_devices.php"); exit();
}

// validate room exists
$vr = $conn->prepare("SELECT 1 FROM class_rooms WHERE room_id=? LIMIT 1");
$vr->bind_param("i", $room_id);
$vr->execute();
$rr = $vr->get_result();
$vr->close();
if (!$rr || $rr->num_rows === 0) {
  flash("Room not found.", "danger");
  header("Location: room_devices.php"); exit();
}

// room_devices.device_uid is PK -> use upsert
$st = $conn->prepare("
  INSERT INTO room_devices (device_uid, room_id)
  VALUES (?, ?)
  ON DUPLICATE KEY UPDATE room_id = VALUES(room_id)
");
$st->bind_param("si", $uid, $room_id);
$ok = $st->execute();
$st->close();

flash($ok ? "Device assigned to room." : "Assign failed: ".$conn->error, $ok ? "success" : "danger");
header("Location: room_devices.php");
