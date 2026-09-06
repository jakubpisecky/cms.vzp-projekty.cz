<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('workplaces');

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: list.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT
        id,
        name
    FROM workplace_categories
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$category = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$category) {
    header("Location: list.php");
    exit;
}

$conn->begin_transaction();

try {

    // Nejprve odstranit vazby na pracoviště
    $stmt = $conn->prepare("
        DELETE FROM workplace_category
        WHERE category_id = ?
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // Potom samotnou kategorii
    $stmt = $conn->prepare("
        DELETE FROM workplace_categories
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    logAction(
        "Smazána kategorie pracovišť '{$category['name']}'"
    );

    header("Location: list.php?deleted=1");
    exit;

} catch (Throwable $e) {

    $conn->rollback();

    error_log(
        'Workplace category delete error: '
        . $e->getMessage()
    );

    http_response_code(500);

    exit("Kategorie se nepodařila smazat.");
}