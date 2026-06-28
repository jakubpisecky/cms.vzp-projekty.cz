<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('articles');

$id  = intval($_POST['id'] ?? $_GET['id'] ?? 0);
$dir = $_POST['dir'] ?? $_GET['dir'] ?? '';

if ($id <= 0 || !in_array($dir, ['up', 'down'], true)) {
    header("Location: list.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT id, article_id, sort_order
    FROM article_blocks
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$current = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$current) {
    header("Location: list.php");
    exit;
}

$articleId = (int)$current['article_id'];
$currentOrder = (int)$current['sort_order'];

if ($dir === 'up') {
    $stmt = $conn->prepare("
        SELECT id, sort_order
        FROM article_blocks
        WHERE article_id = ?
          AND sort_order < ?
        ORDER BY sort_order DESC, id DESC
        LIMIT 1
    ");
} else {
    $stmt = $conn->prepare("
        SELECT id, sort_order
        FROM article_blocks
        WHERE article_id = ?
          AND sort_order > ?
        ORDER BY sort_order ASC, id ASC
        LIMIT 1
    ");
}

$stmt->bind_param("ii", $articleId, $currentOrder);
$stmt->execute();
$other = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($other) {
    $otherId = (int)$other['id'];
    $otherOrder = (int)$other['sort_order'];

    $stmt = $conn->prepare("
        UPDATE article_blocks
        SET sort_order = ?
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param("ii", $otherOrder, $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("
        UPDATE article_blocks
        SET sort_order = ?
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param("ii", $currentOrder, $otherId);
    $stmt->execute();
    $stmt->close();
}

header("Location: blocks.php?id=" . $articleId);
exit;