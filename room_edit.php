<?php
require 'admin_guard.php';
$room_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$room = null;
if ($room_id) {
    $q = $conn->query("SELECT room_id, room_name FROM class_rooms WHERE room_id = $room_id");
    $room = $q ? $q->fetch_assoc() : null;
}
if (!$room) {
    header('Location: rooms.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['room_name'] ?? '');
    $redirect = $_GET['redirect'] ?? 'devices.php?tab=rooms';
    if ($name !== '') {
        $stmt = $conn->prepare("UPDATE class_rooms SET room_name=? WHERE room_id=?");
        $stmt->bind_param('si', $name, $room_id);
        $stmt->execute();
        flash('Room updated successfully.', 'success');
        header('Location: ' . $redirect);
        exit;
    } else {
        $error = 'Room name cannot be empty.';
    }
}
?><!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Edit Room</title>
    <link rel="stylesheet" href="css/manageusers.css">
    <style>
        .edit-form { max-width: 400px; margin: 40px auto; background: #fff; border-radius: 10px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); }
        .edit-form label { font-weight: 800; margin-bottom: 8px; display: block; }
        .edit-form input { width: 100%; padding: 10px 12px; border-radius: 10px; border: 1px solid #e5e7eb; margin-bottom: 16px; }
        .edit-form button { padding: 10px 24px; border-radius: 10px; background: #0ea5a4; color: #fff; font-weight: 900; border: none; cursor: pointer; }
        .msg { margin-bottom: 16px; padding: 10px 12px; border-radius: 10px; background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <form class="edit-form" method="POST">
        <h2>Edit Room</h2>
        <?php if (!empty($error)): ?>
            <div class="msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <label>Room Name</label>
        <input name="room_name" value="<?= htmlspecialchars($room['room_name']) ?>" required>
        <button type="submit">Save Changes</button>
        <a href="rooms.php" style="margin-left:18px; color:#0ea5a4; text-decoration:underline;">Cancel</a>
    </form>
</body>
</html>
