<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('menus');

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("DELETE FROM menu_days WHERE id=?");
$stmt->bind_param("i",$id); $stmt->execute(); $stmt->close();
header("Location: list.php?deleted=1");
