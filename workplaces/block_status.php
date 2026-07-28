<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('workplaces');

$id = intval($_GET['id'] ?? 0);
$to = intval($_GET['to'] ?? 0);

$to = $to === 1 ? 1 : 0;

if ($id <= 0) {
    header("Location: list.php");
    exit;
}

/*
 * Načtení bloku, stránky a pracoviště.
 * Současně ověřujeme, že blok patří stránce pracoviště.
 */
$stmt = $conn->prepare("
    SELECT
        pb.id,
        pb.page_id,
        pb.title,
        pb.type,

        p.title AS page_title,
        p.owner_type,
        p.owner_id,

        wp.workplace_id,

        w.name AS workplace_name

    FROM page_blocks pb

    INNER JOIN pages p
        ON p.id = pb.page_id

    INNER JOIN workplace_pages wp
        ON wp.page_id = p.id

    INNER JOIN workplaces w
        ON w.id = wp.workplace_id

    WHERE pb.id = ?
      AND p.owner_type = 'workplace'
      AND p.owner_id = wp.workplace_id

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

/*
 * Změna stavu bloku.
 */
$stmt = $conn->prepare("
    UPDATE page_blocks
    SET is_active = ?
    WHERE id = ?
      AND page_id = ?
    LIMIT 1
");

$stmt->bind_param(
    "iii",
    $to,
    $id,
    $pageId
);

$stmt->execute();
$stmt->close();

logAction(
    ($to ? "Aktivován" : "Skryt")
    . " blok '"
    . ($block['title'] ?: $block['type'])
    . "' na stránce '{$block['page_title']}'"
    . " pracoviště '{$block['workplace_name']}'"
);

header(
    "Location: page_blocks.php?page_id="
    . $pageId
    . "&updated=1"
);
exit;