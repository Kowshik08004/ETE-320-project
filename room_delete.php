<?php
require 'admin_guard.php';


$id = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
} else {
	$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
}
if ($id <= 0) { header("Location: rooms.php"); exit(); }

// remove mapped devices first
$d = $conn->prepare("DELETE FROM room_devices WHERE room_id=?");
$d->bind_param("i", $id); $d->execute(); $d->close();

$del = $conn->prepare("DELETE FROM class_rooms WHERE room_id=?");
$del->bind_param("i", $id);
$ok = $del->execute();
$del->close();

flash($ok ? "Room deleted." : "Delete failed.", $ok ? "success" : "danger");
$redirect = $_POST['redirect'] ?? $_GET['redirect'] ?? 'rooms.php';
header("Location: $redirect");
