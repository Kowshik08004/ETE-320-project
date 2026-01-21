<?php
require 'admin_guard.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header("Location: devices.php"); exit(); }

// also clean mapping tables to avoid orphan mappings
// get uid first
$g = $conn->prepare("SELECT device_uid FROM devices WHERE id=? LIMIT 1");
$g->bind_param("i", $id);
$g->execute();
$r = $g->get_result();
$uid = ($r && $r->num_rows) ? $r->fetch_assoc()['device_uid'] : null;
$g->close();

if ($uid) {
  $d1 = $conn->prepare("DELETE FROM room_devices WHERE device_uid=?");
  $d1->bind_param("s", $uid); $d1->execute(); $d1->close();

  $d2 = $conn->prepare("DELETE FROM batch_rooms WHERE device_uid=?");
  $d2->bind_param("s", $uid); $d2->execute(); $d2->close();
}

$del = $conn->prepare("DELETE FROM devices WHERE id=?");
$del->bind_param("i", $id);
$ok = $del->execute();
$del->close();

flash($ok ? "Device deleted." : "Delete failed.", $ok ? "success" : "danger");
header("Location: devices.php");
