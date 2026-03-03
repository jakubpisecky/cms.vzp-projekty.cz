<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('banners');

$id = (int)($_GET['id'] ?? 0);
$to = ($_GET['to'] ?? '') === 'published' ? 'published' : 'draft';

$stmt = $conn->prepare("UPDATE banners SET status=? WHERE id=?");
$stmt->bind_param("si", $to, $id);
$stmt->execute(); $stmt->close();

header("Location: list.php?toggled=".$to);
