<?php
require 'admin_guard.php';

$uid  = trim($_POST['device_uid'] ?? '');
$name = trim($_POST['device_name'] ?? '');
$dep  = trim($_POST['device_dep'] ?? '');

if ($uid === '' || $name === '' || $dep === '') {
  flash("Missing fields.", "danger");
  header("Location: devices.php"); exit();
}

// devices.device_uid is TEXT; still normalize
$uid = substr($uid, 0, 50);
$name = substr($name, 0, 50);
$dep = substr($dep, 0, 20);

// if exists -> update else insert
$chk = $conn->prepare("SELECT id FROM devices WHERE device_uid = ? LIMIT 1");
$chk->bind_param("s", $uid);
$chk->execute();
$res = $chk->get_result();

if ($res && $res->num_rows > 0) {
  $row = $res->fetch_assoc();
  $id = (int)$row['id'];
  $up = $conn->prepare("UPDATE devices SET device_name=?, device_dep=? WHERE id=?");
  $up->bind_param("ssi", $name, $dep, $id);
  $ok = $up->execute();
  $up->close();
  flash($ok ? "Device updated." : "Update failed: ".$conn->error, $ok ? "success" : "danger");
} else {
  $ins = $conn->prepare("INSERT INTO devices (device_name, device_dep, device_uid, device_date, device_mode) VALUES (?, ?, ?, CURDATE(), 1)");
  $ins->bind_param("sss", $name, $dep, $uid);
  $ok = $ins->execute();
  $ins->close();
  flash($ok ? "Device created." : "Insert failed: ".$conn->error, $ok ? "success" : "danger");
}

$chk->close();
header("Location: devices.php");
