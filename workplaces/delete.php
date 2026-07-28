<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('workplaces');

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: list.php");
    exit;
}

/*
 * Načtení pracoviště
 */
$stmt = $conn->prepare("
    SELECT
        id,
        name,
        main_page_id
    FROM workplaces
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$workplace = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$workplace) {
    header("Location: list.php");
    exit;
}

$workplaceName = (string)$workplace['name'];

$conn->begin_transaction();

try {
    /*
     * 1. Smazání všech bloků stránek daného pracoviště.
     *
     * Stránky ověřujeme jak přes owner_type/owner_id,
     * tak přes vazbu workplace_pages.
     */
    $stmt = $conn->prepare("
        DELETE pb
        FROM page_blocks pb

        INNER JOIN pages p
            ON p.id = pb.page_id

        LEFT JOIN workplace_pages wp
            ON wp.page_id = p.id
            AND wp.workplace_id = ?

        WHERE p.owner_type = 'workplace'
          AND p.owner_id = ?
          AND (
              wp.id IS NOT NULL
              OR p.owner_id = ?
          )
    ");

    $stmt->bind_param(
        "iii",
        $id,
        $id,
        $id
    );

    $stmt->execute();
    $stmt->close();

    /*
     * 2. Smazání vazeb stránek na pracoviště.
     */
    $stmt = $conn->prepare("
        DELETE FROM workplace_pages
        WHERE workplace_id = ?
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    /*
     * 3. Smazání všech stránek vlastněných pracovištěm.
     *
     * Tím se smaže hlavní stránka i všechny podstránky.
     */
    $stmt = $conn->prepare("
        DELETE FROM pages
        WHERE owner_type = 'workplace'
          AND owner_id = ?
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    /*
     * 4. Smazání vazeb na kategorie.
     */
    $stmt = $conn->prepare("
        DELETE FROM workplace_category
        WHERE workplace_id = ?
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    /*
     * 5. Smazání vazeb na odbornosti.
     */
    $stmt = $conn->prepare("
        DELETE FROM workplace_specialization
        WHERE workplace_id = ?
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    /*
     * 6. Smazání samotného pracoviště.
     */
    $stmt = $conn->prepare("
        DELETE FROM workplaces
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();

    if ($stmt->affected_rows !== 1) {
        throw new RuntimeException(
            'Pracoviště se nepodařilo odstranit.'
        );
    }

    $stmt->close();

    $conn->commit();

    logAction(
        "Smazáno pracoviště '$workplaceName' "
        . "včetně jeho stránek a bloků"
    );

    header("Location: list.php?deleted=1");
    exit;

} catch (Throwable $e) {
    $conn->rollback();

    error_log(
        'Workplace delete error: '
        . $e->getMessage()
    );

    header("Location: list.php?delete_error=1");
    exit;
}