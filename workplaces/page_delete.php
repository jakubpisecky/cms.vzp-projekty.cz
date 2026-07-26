<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('workplaces');

$workplacePageId = intval($_GET['id'] ?? 0);

if ($workplacePageId <= 0) {
    header("Location: list.php");
    exit;
}

/*
 * Načtení stránky a pracoviště
 */
$stmt = $conn->prepare("
    SELECT
        wp.id AS workplace_page_id,
        wp.workplace_id,
        wp.page_id,

        p.title AS page_title,

        w.name AS workplace_name,
        w.main_page_id

    FROM workplace_pages wp

    INNER JOIN pages p
        ON p.id = wp.page_id

    INNER JOIN workplaces w
        ON w.id = wp.workplace_id

    WHERE wp.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $workplacePageId);
$stmt->execute();
$page = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$page) {
    header("Location: list.php");
    exit;
}

$workplaceId = (int)$page['workplace_id'];
$pageId = (int)$page['page_id'];
$mainPageId = (int)($page['main_page_id'] ?? 0);

/*
 * Hlavní stránku nelze smazat samostatně.
 */
if ($pageId === $mainPageId) {
    header(
        "Location: pages.php?id="
        . $workplaceId
        . "&delete_error=main_page"
    );
    exit;
}

$conn->begin_transaction();

try {
    /*
     * 1. Pokud mají jiné položky tuto stránku jako parent,
     * odpojíme je a přesuneme na nejvyšší úroveň.
     */
    $stmt = $conn->prepare("
        UPDATE workplace_pages
        SET parent_id = NULL
        WHERE workplace_id = ?
          AND parent_id = ?
    ");
    $stmt->bind_param(
        "ii",
        $workplaceId,
        $workplacePageId
    );
    $stmt->execute();
    $stmt->close();

    /*
     * 2. Smažeme bloky stránky.
     *
     * Smažou se jen vazby bloků. Galerie, formuláře,
     * články ani další navázané entity zůstanou zachované.
     */
    $stmt = $conn->prepare("
        DELETE FROM page_blocks
        WHERE page_id = ?
    ");
    $stmt->bind_param("i", $pageId);
    $stmt->execute();
    $stmt->close();

    /*
     * 3. Smažeme vazbu stránky na pracoviště.
     */
    $stmt = $conn->prepare("
        DELETE FROM workplace_pages
        WHERE id = ?
          AND workplace_id = ?
        LIMIT 1
    ");
    $stmt->bind_param(
        "ii",
        $workplacePageId,
        $workplaceId
    );
    $stmt->execute();
    $stmt->close();

    /*
     * 4. Smažeme samotnou stránku.
     */
    $stmt = $conn->prepare("
        DELETE FROM pages
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $pageId);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    logAction(
        "Smazána stránka '{$page['page_title']}' "
        . "pracoviště '{$page['workplace_name']}'"
    );

    header(
        "Location: pages.php?id="
        . $workplaceId
        . "&deleted=1"
    );
    exit;

} catch (Throwable $e) {
    $conn->rollback();

    error_log(
        'Workplace page delete error: ' . $e->getMessage()
    );

    header(
        "Location: pages.php?id="
        . $workplaceId
        . "&delete_error=1"
    );
    exit;
}