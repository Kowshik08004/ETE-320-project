<?php
require 'admin_guard.php';

$batch = trim($_GET['batch'] ?? '');
if ($batch === '') { header("Location: batch_rooms.php"); exit(); }

$del = $conn->prepare("DELETE FROM batch_rooms WHERE batch=?");
$del->bind_param("i", $batch);
$ok = $del->execute();
$del->close();

flash($ok ? "Mapping deleted." : "Delete failed.", $ok ? "success" : "danger");
header("Location: batch_rooms.php");
