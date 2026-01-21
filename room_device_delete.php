<?php
require 'admin_guard.php';

$uid = trim($_GET['uid'] ?? '');
if ($uid === '') { header("Location: room_devices.php"); exit(); }
$uid = substr($uid, 0, 50);

$del = $conn->prepare("DELETE FROM room_devices WHERE device_uid=?");
$del->bind_param("s", $uid);
$ok = $del->execute();
$del->close();

flash($ok ? "Mapping removed." : "Unassign failed.", $ok ? "success" : "danger");
header("Location: room_devices.php");
