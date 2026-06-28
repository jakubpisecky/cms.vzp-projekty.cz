<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('pictures');

$id = (int)($_POST['id'] ?? 0);
$categoryId = (int)($_POST['category_id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo "Neplatné ID obrázku.";
    exit;
}

if ($categoryId <= 0) {
    $stmt = $conn->prepare("UPDATE pictures SET category_id = NULL WHERE id = ?");
    $stmt->bind_param("i", $id);
} else {
    $stmt = $conn->prepare("UPDATE pictures SET category_id = ? WHERE id = ?");
    $stmt->bind_param("ii", $categoryId, $id);
}

if (!$stmt->execute()) {
    http_response_code(500);
    echo "Chyba při ukládání: " . $stmt->error;
    exit;
}

echo "OK";