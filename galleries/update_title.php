<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('galleries');

$id = intval($_POST['id'] ?? 0);
$title = trim($_POST['title'] ?? '');

if ($id === 0 || $title === '') {
    http_response_code(400);
    echo "Neplatné vstupní údaje.";
    exit;
}

$stmt = $conn->prepare("UPDATE gallery_photos SET title=? WHERE id=?");
$stmt->bind_param("si", $title, $id);
$stmt->execute();

echo "OK";
