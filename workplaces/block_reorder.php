<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('workplaces');

header('Content-Type: application/json; charset=utf-8');

$id = intval($_POST['id'] ?? 0);
$dir = $_POST['dir'] ?? '';

if (
    $id <= 0
    || !in_array($dir, ['up', 'down'], true)
) {
    echo json_encode([
        'ok' => false,
        'msg' => 'Neplatný požadavek.',
    ]);
    exit;
}

/*
 * Načtení aktuálního bloku.
 *
 * Současně ověřujeme, že:
 * - blok patří stránce pracoviště,
 * - stránka má owner_type workplace,
 * - owner_id odpovídá pracovišti.
 */
$stmt = $conn->prepare("
    SELECT
        pb.id,
        pb.page_id,
        pb.sort_order,

        p.title AS page_title,
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

$current = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$current) {
    echo json_encode([
        'ok' => false,
        'msg' => 'Blok nebyl nalezen nebo nepatří pracovišti.',
    ]);
    exit;
}

$pageId = (int)$current['page_id'];
$currentOrder = (int)$current['sort_order'];

/*
 * Vyhledání sousedního bloku na stejné stránce.
 */
if ($dir === 'up') {
    $stmt = $conn->prepare("
        SELECT
            pb.id,
            pb.sort_order

        FROM page_blocks pb

        INNER JOIN pages p
            ON p.id = pb.page_id

        INNER JOIN workplace_pages wp
            ON wp.page_id = p.id

        WHERE pb.page_id = ?
          AND pb.sort_order < ?
          AND p.owner_type = 'workplace'
          AND p.owner_id = wp.workplace_id

        ORDER BY
            pb.sort_order DESC,
            pb.id DESC

        LIMIT 1
    ");
} else {
    $stmt = $conn->prepare("
        SELECT
            pb.id,
            pb.sort_order

        FROM page_blocks pb

        INNER JOIN pages p
            ON p.id = pb.page_id

        INNER JOIN workplace_pages wp
            ON wp.page_id = p.id

        WHERE pb.page_id = ?
          AND pb.sort_order > ?
          AND p.owner_type = 'workplace'
          AND p.owner_id = wp.workplace_id

        ORDER BY
            pb.sort_order ASC,
            pb.id ASC

        LIMIT 1
    ");
}

$stmt->bind_param(
    "ii",
    $pageId,
    $currentOrder
);

$stmt->execute();

$other = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$other) {
    echo json_encode([
        'ok' => true,
        'msg' => 'Není kam posunout.',
    ]);
    exit;
}

$otherId = (int)$other['id'];
$otherOrder = (int)$other['sort_order'];

$conn->begin_transaction();

try {
    /*
     * Aktuální blok převezme pořadí sousedního bloku.
     */
    $stmt = $conn->prepare("
        UPDATE page_blocks
        SET sort_order = ?
        WHERE id = ?
          AND page_id = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "iii",
        $otherOrder,
        $id,
        $pageId
    );

    $stmt->execute();
    $stmt->close();

    /*
     * Sousední blok převezme původní pořadí.
     */
    $stmt = $conn->prepare("
        UPDATE page_blocks
        SET sort_order = ?
        WHERE id = ?
          AND page_id = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "iii",
        $currentOrder,
        $otherId,
        $pageId
    );

    $stmt->execute();
    $stmt->close();

    $conn->commit();

    logAction(
        "Změněno pořadí bloku na stránce "
        . "'{$current['page_title']}' "
        . "pracoviště '{$current['workplace_name']}'"
    );

    echo json_encode([
        'ok' => true,
    ]);
    exit;

} catch (Throwable $e) {
    $conn->rollback();

    error_log(
        'Workplace block reorder error: '
        . $e->getMessage()
    );

    echo json_encode([
        'ok' => false,
        'msg' => 'Chyba při změně pořadí.',
    ]);
    exit;
}