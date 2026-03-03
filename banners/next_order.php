<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('banners');
header('Content-Type: application/json; charset=utf-8');

$row = $conn->query("SELECT COALESCE(MAX(sort_order),0)+10 AS n FROM banners")->fetch_assoc();
echo json_encode(['ok'=>true,'next'=>(int)($row['n'] ?? 10)]);
