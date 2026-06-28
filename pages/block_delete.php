<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('pages');

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: list.php");
    exit;
}

// načtení bloku
$stmt = $conn->prepare("
    SELECT 
        pb.id,
        pb.title,
        pb.type,
        pb.page_id,
        p.title AS page_title
    FROM page_blocks pb
    INNER JOIN pages p ON pb.page_id = p.id
    WHERE pb.id = ?
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

$pageId = (int)$block['page_id'];

// smazání
$stmt = $conn->prepare("
    DELETE FROM page_blocks
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

logAction(
    "Smazán blok '" .
    ($block['title'] ?: $block['type']) .
    "' ze stránky '{$block['page_title']}'"
);

header("Location: blocks.php?page_id=" . $pageId . "&deleted=1");
exit;