<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";

// odpověď vždy jako prostý text
header("Content-Type: text/plain; charset=UTF-8");

$id    = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$title = isset($_POST['title']) ? trim($_POST['title']) : '';

if ($id <= 0) {
    http_response_code(400);
    echo "Neplatné ID";
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE pictures SET title = ? WHERE id = ? LIMIT 1");
    if (!$stmt) {
        throw new Exception($conn->error);
    }
    $stmt->bind_param("si", $title, $id);
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }
    echo "OK";
} catch (Throwable $e) {
    http_response_code(500);
    echo "DB error: " . $e->getMessage();
}
