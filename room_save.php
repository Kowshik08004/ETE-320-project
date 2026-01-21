<?php
require 'admin_guard.php';

$name = trim($_POST['room_name'] ?? '');
if ($name === '') { flash("Room name required.", "danger"); header("Location: rooms.php"); exit(); }

$name = substr($name, 0, 50);

$ins = $conn->prepare("INSERT INTO class_rooms (room_name) VALUES (?)");
$ins->bind_param("s", $name);
$ok = $ins->execute();
$ins->close();

	flash($ok ? "Room added." : "Insert failed: ".$conn->error, $ok ? "success" : "danger");
	$redirect = $_POST['redirect'] ?? 'devices.php?tab=rooms';
	header("Location: $redirect");
	exit();
