<?php
require 'admin_guard.php';

$batch = trim($_POST['batch'] ?? '');
$uid   = trim($_POST['device_uid'] ?? '');

if ($batch === '' || $uid === '') {
  flash("Missing batch or device.", "danger");
  header("Location: batch_rooms.php"); exit();
}

$uid = substr($uid, 0, 20); // batch_rooms.device_uid is varchar(20)

// validate device exists (devices stores device_uid as TEXT, so compare exact)
$vd = $conn->prepare("SELECT 1 FROM devices WHERE device_uid=? LIMIT 1");
$vd->bind_param("s", $uid);
$vd->execute();
$rd = $vd->get_result();
$vd->close();
if (!$rd || $rd->num_rows === 0) {
  flash("Device UID not found in devices table.", "danger");
  header("Location: batch_rooms.php"); exit();
}

// UNIQUE(device_uid) constraint: device can belong to only one batch
$chk = $conn->prepare("SELECT batch FROM batch_rooms WHERE device_uid=? LIMIT 1");
$chk->bind_param("s", $uid);
$chk->execute();
$res = $chk->get_result();
$chk->close();

if ($res && $res->num_rows > 0) {
  $row = $res->fetch_assoc();
  if ((string)$row['batch'] !== (string)$batch) {
    flash("This device is already assigned to batch ".$row['batch'].". Unassign it first.", "danger");
    header("Location: batch_rooms.php"); exit();
  }
}

// Upsert by batch (PK=batch)
$st = $conn->prepare("
  INSERT INTO batch_rooms (batch, device_uid)
  VALUES (?, ?)
  ON DUPLICATE KEY UPDATE device_uid = VALUES(device_uid)
");
$st->bind_param("is", $batch, $uid);
$ok = $st->execute();
$st->close();

flash($ok ? "Batch assigned to device." : "Assign failed: ".$conn->error, $ok ? "success" : "danger");
header("Location: batch_rooms.php");
