<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('articles');

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: list.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT ab.id, ab.article_id, ab.title, a.title AS article_title
    FROM article_blocks ab
    INNER JOIN articles a ON ab.article_id = a.id
    WHERE ab.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$block = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$block) {
    header("Location: list.php");
    exit;
}

$articleId = (int)$block['article_id'];

$stmt = $conn->prepare("
    DELETE FROM article_blocks
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

logAction("Smazán blok '{$block['title']}' u článku '{$block['article_title']}'");

header("Location: blocks.php?id=" . $articleId . "&deleted=1");
exit;