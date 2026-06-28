<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('pages');

$id = intval($_GET['id'] ?? 0);
$to = intval($_GET['to'] ?? 0);

$to = $to === 1 ? 1 : 0;

if ($id <= 0) {
    header("Location: list.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT pb.id, pb.page_id, pb.title, pb.type, p.title AS page_title
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

$stmt = $conn->prepare("
    UPDATE page_blocks
    SET is_active = ?
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $to, $id);
$stmt->execute();
$stmt->close();

logAction(
    ($to ? "Aktivován" : "Skryt") .
    " blok '" . ($block['title'] ?: $block['type']) .
    "' na stránce '{$block['page_title']}'"
);

header("Location: blocks.php?page_id=" . (int)$block['page_id'] . "&updated=1");
exit;