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
        id,
        name
    FROM navigation
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$navigation = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$navigation) {
    header("Location: list.php");
    exit;
}

$conn->begin_transaction();

try {

    /*
     * Zrušení přiřazení navigace ze stránek.
     */
    $stmt = $conn->prepare("
        UPDATE pages
        SET sidebar_navigation_id = NULL
        WHERE sidebar_navigation_id = ?
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();


    /*
     * Smazání položek navigace.
     */
    $stmt = $conn->prepare("
        DELETE FROM navigation_items
        WHERE navigation_id = ?
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();


    /*
     * Smazání navigace.
     */
    $stmt = $conn->prepare("
        DELETE FROM navigation
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();


    $conn->commit();

    logAction(
        "Smazána navigace '{$navigation['name']}'"
    );

    header("Location: list.php?deleted=1");
    exit;

} catch (Throwable $e) {

    $conn->rollback();

    http_response_code(500);

    exit(
        'Navigaci se nepodařilo smazat: '
        . e($e->getMessage())
    );
}