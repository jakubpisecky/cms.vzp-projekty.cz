<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('pages');

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: list.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT
        ni.id,
        ni.navigation_id,
        ni.title,
        n.name AS navigation_name
    FROM navigation_items ni
    INNER JOIN navigation n
        ON n.id = ni.navigation_id
    WHERE ni.id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item) {
    header("Location: list.php");
    exit;
}

$navigationId = (int)$item['navigation_id'];

/*
 * Pokud má položka podřízené položky,
 * přesuneme je na první úroveň.
 */
$stmt = $conn->prepare("
    UPDATE navigation_items
    SET parent_id = NULL
    WHERE parent_id = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

/*
 * Smazání položky.
 */
$stmt = $conn->prepare("
    DELETE FROM navigation_items
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

logAction(
    "Odstraněna položka '{$item['title']}' "
    . "z navigace '{$item['navigation_name']}'"
);

header(
    "Location: items.php?id="
    . $navigationId
    . "&deleted=1"
);

exit;