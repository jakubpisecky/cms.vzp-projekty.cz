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

/*
 * Načtení odbornosti.
 */
$stmt = $conn->prepare("
    SELECT
        id,
        name
    FROM workplace_specializations
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$specialization = $stmt
    ->get_result()
    ->fetch_assoc();

$stmt->close();

if (!$specialization) {
    header("Location: list.php");
    exit;
}

$conn->begin_transaction();

try {

    /*
     * 1. Odstranění vazeb na pracoviště.
     */
    $stmt = $conn->prepare("
        DELETE FROM workplace_specialization
        WHERE specialization_id = ?
    ");

    $stmt->bind_param(
        "i",
        $id
    );

    $stmt->execute();
    $stmt->close();


    /*
     * 2. Smazání samotné odbornosti.
     */
    $stmt = $conn->prepare("
        DELETE FROM workplace_specializations
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "i",
        $id
    );

    $stmt->execute();
    $stmt->close();


    $conn->commit();

    logAction(
        "Smazána odbornost pracovišť '{$specialization['name']}'"
    );

    header(
        "Location: list.php?deleted=1"
    );

    exit;

} catch (Throwable $e) {

    $conn->rollback();

    error_log(
        'Workplace specialization delete error: '
        . $e->getMessage()
    );

    http_response_code(500);

    exit(
        "Odbornost se nepodařilo smazat."
    );
}