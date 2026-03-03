<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('visits');
$id = intval($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

if ($id > 0 && in_array($action, ['pay','confirm'])) {
    if ($action === 'pay') {
        $stmt = $conn->prepare("UPDATE visits SET paid=1 WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }

    if ($action === 'confirm') {
        $stmt = $conn->prepare("UPDATE visits SET confirmed_by=?, confirmed_at=NOW() WHERE id=?");
        $stmt->bind_param("si", $_SESSION['admin_id'], $id);
        $stmt->execute();
    }
}

header("Location: list.php");
exit;
