<?php
// admin_guard.php
session_start();
if (!isset($_SESSION['Admin-name'])) {
  header("location: login.php");
  exit();
}
require 'connectDB.php';

function flash($msg, $type="info") {
  $_SESSION['flash'] = ["msg"=>$msg, "type"=>$type];
}
function read_flash() {
  if (!isset($_SESSION['flash'])) return null;
  $f = $_SESSION['flash'];
  unset($_SESSION['flash']);
  return $f;
}
