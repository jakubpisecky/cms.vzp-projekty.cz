<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('workplaces');

$id = intval($_GET['id'] ?? 0);
$workplaceId = intval($_GET['workplace_id'] ?? 0);
$dir = $_GET['dir'] ?? '';

if (
    $id <= 0 ||
    $workplaceId <= 0 ||
    !in_array($dir, ['up', 'down'], true)
) {
    header("Location: list.php");
    exit;
}

/*
 * Aktuální stránka
 */
$stmt = $conn->prepare("
    SELECT
        id,
        workplace_id,
        sort_order
    FROM workplace_pages
    WHERE id = ?
      AND workplace_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $id, $workplaceId);
$stmt->execute();
$current = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$current) {
    header("Location: pages.php?id=".$workplaceId);
    exit;
}

$currentOrder = (int)$current['sort_order'];

/*
 * Soused
 */
if ($dir === 'up') {

    $stmt = $conn->prepare("
        SELECT id, sort_order
        FROM workplace_pages
        WHERE workplace_id = ?
          AND sort_order < ?
        ORDER BY sort_order DESC,id DESC
        LIMIT 1
    ");

} else {

    $stmt = $conn->prepare("
        SELECT id, sort_order
        FROM workplace_pages
        WHERE workplace_id = ?
          AND sort_order > ?
        ORDER BY sort_order ASC,id ASC
        LIMIT 1
    ");

}

$stmt->bind_param(
    "ii",
    $workplaceId,
    $currentOrder
);

$stmt->execute();
$other = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($other) {

    $otherId = (int)$other['id'];
    $otherOrder = (int)$other['sort_order'];

    $conn->begin_transaction();

    try {

        $stmt = $conn->prepare("
            UPDATE workplace_pages
            SET sort_order = ?
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->bind_param(
            "ii",
            $otherOrder,
            $id
        );
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("
            UPDATE workplace_pages
            SET sort_order = ?
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->bind_param(
            "ii",
            $currentOrder,
            $otherId
        );
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        logAction("Změněno pořadí stránky pracoviště.");

    } catch (Throwable $e) {

        $conn->rollback();

        error_log($e->getMessage());

    }

}

header("Location: pages.php?id=".$workplaceId."&moved=1");
exit;