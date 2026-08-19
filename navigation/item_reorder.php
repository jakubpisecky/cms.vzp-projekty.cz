<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('pages');

header('Content-Type: application/json; charset=utf-8');

$id = (int)($_POST['id'] ?? 0);
$dir = $_POST['dir'] ?? '';

if (
    $id <= 0
    || !in_array($dir, ['up', 'down'], true)
) {
    echo json_encode([
        'ok' => false,
        'msg' => 'Neplatný požadavek.'
    ]);
    exit;
}

/*
 * Aktuální položka.
 */
$stmt = $conn->prepare("
    SELECT
        id,
        navigation_id,
        parent_id,
        sort_order
    FROM navigation_items
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$current = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$current) {
    echo json_encode([
        'ok' => false,
        'msg' => 'Položka nebyla nalezena.'
    ]);
    exit;
}

$navigationId = (int)$current['navigation_id'];
$order = (int)$current['sort_order'];

$parentId = $current['parent_id'] !== null
    ? (int)$current['parent_id']
    : null;

/*
 * Hledáme sousední položku pouze na stejné úrovni.
 */
if ($parentId === null) {

    if ($dir === 'up') {

        $stmt = $conn->prepare("
            SELECT id, sort_order
            FROM navigation_items
            WHERE navigation_id = ?
              AND parent_id IS NULL
              AND sort_order < ?
            ORDER BY sort_order DESC, id DESC
            LIMIT 1
        ");

    } else {

        $stmt = $conn->prepare("
            SELECT id, sort_order
            FROM navigation_items
            WHERE navigation_id = ?
              AND parent_id IS NULL
              AND sort_order > ?
            ORDER BY sort_order ASC, id ASC
            LIMIT 1
        ");
    }

    $stmt->bind_param(
        "ii",
        $navigationId,
        $order
    );

} else {

    if ($dir === 'up') {

        $stmt = $conn->prepare("
            SELECT id, sort_order
            FROM navigation_items
            WHERE navigation_id = ?
              AND parent_id = ?
              AND sort_order < ?
            ORDER BY sort_order DESC, id DESC
            LIMIT 1
        ");

    } else {

        $stmt = $conn->prepare("
            SELECT id, sort_order
            FROM navigation_items
            WHERE navigation_id = ?
              AND parent_id = ?
              AND sort_order > ?
            ORDER BY sort_order ASC, id ASC
            LIMIT 1
        ");
    }

    $stmt->bind_param(
        "iii",
        $navigationId,
        $parentId,
        $order
    );
}

$stmt->execute();

$other = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$other) {
    echo json_encode([
        'ok' => true,
        'msg' => 'Není kam posunout.'
    ]);
    exit;
}

$otherId = (int)$other['id'];
$otherOrder = (int)$other['sort_order'];

$conn->begin_transaction();

try {

    $stmt = $conn->prepare("
        UPDATE navigation_items
        SET sort_order = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "ii",
        $otherOrder,
        $id
    );

    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("
        UPDATE navigation_items
        SET sort_order = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "ii",
        $order,
        $otherId
    );

    $stmt->execute();
    $stmt->close();

    $conn->commit();

    echo json_encode([
        'ok' => true
    ]);

    exit;

} catch (Throwable $e) {

    $conn->rollback();

    echo json_encode([
        'ok' => false,
        'msg' => 'Chyba při změně pořadí: '
            . $e->getMessage()
    ]);

    exit;
}