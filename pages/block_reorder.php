<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('pages');

header('Content-Type: application/json; charset=utf-8');

$id  = intval($_POST['id'] ?? 0);
$dir = $_POST['dir'] ?? '';

if ($id <= 0 || !in_array($dir, ['up', 'down'], true)) {
    echo json_encode(['ok' => false, 'msg' => 'Neplatný požadavek.']);
    exit;
}

$stmt = $conn->prepare("
    SELECT id, page_id, sort_order
    FROM page_blocks
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$current = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$current) {
    echo json_encode(['ok' => false, 'msg' => 'Blok nebyl nalezen.']);
    exit;
}

$pageId = (int)$current['page_id'];
$order  = (int)$current['sort_order'];

if ($dir === 'up') {
    $stmt = $conn->prepare("
        SELECT id, sort_order
        FROM page_blocks
        WHERE page_id = ?
          AND sort_order < ?
        ORDER BY sort_order DESC, id DESC
        LIMIT 1
    ");
} else {
    $stmt = $conn->prepare("
        SELECT id, sort_order
        FROM page_blocks
        WHERE page_id = ?
          AND sort_order > ?
        ORDER BY sort_order ASC, id ASC
        LIMIT 1
    ");
}

$stmt->bind_param("ii", $pageId, $order);
$stmt->execute();
$other = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$other) {
    echo json_encode(['ok' => true, 'msg' => 'Není kam posunout.']);
    exit;
}

$conn->begin_transaction();

try {
    $otherId    = (int)$other['id'];
    $otherOrder = (int)$other['sort_order'];

    $stmt = $conn->prepare("UPDATE page_blocks SET sort_order = ? WHERE id = ?");
    $stmt->bind_param("ii", $otherOrder, $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE page_blocks SET sort_order = ? WHERE id = ?");
    $stmt->bind_param("ii", $order, $otherId);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    echo json_encode(['ok' => true]);
    exit;

} catch (Throwable $e) {
    $conn->rollback();

    echo json_encode([
        'ok' => false,
        'msg' => 'Chyba při změně pořadí: ' . $e->getMessage()
    ]);
    exit;
}