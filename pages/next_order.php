<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('pages');

header('Content-Type: application/json; charset=UTF-8');

$parentId = intval($_GET['parent_id'] ?? 0);

$stmt = $conn->prepare("SELECT COALESCE(MAX(menu_order), 0) + 10 AS next_ord FROM pages WHERE parent_id=?");
$stmt->bind_param("i", $parentId);
$stmt->execute();
$next = (int)($stmt->get_result()->fetch_assoc()['next_ord'] ?? 10);
$stmt->close();

echo json_encode(['ok' => true, 'next' => $next ?: 10]);
