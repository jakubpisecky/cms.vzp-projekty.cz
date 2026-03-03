<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('pages');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: list.php?error=not_found");
    exit;
}

$conn->begin_transaction();

try {
    // 1) Lock aktuální stránku a zjisti parent_id
    $stmt = $conn->prepare("SELECT id, parent_id, title FROM pages WHERE id=? FOR UPDATE");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $page = $stmt->get_result()->fetch_assoc();
    $title = $page['title'] ?? '';
    $stmt->close();

    if (!$page) {
        throw new Exception('Stránka nebyla nalezena.');
    }

    $parentId = (int)$page['parent_id'];

    // 2) Lock děti mazané stránky (v deterministickém pořadí)
    $stmt = $conn->prepare("
        SELECT id
        FROM pages
        WHERE parent_id=?
        ORDER BY menu_order ASC, id ASC
        FOR UPDATE
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $children = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $children[] = (int)$row['id'];
    }
    $stmt->close();

    // 3) Lock sourozence cílového rodiče a zjisti max(menu_order)
    $stmt = $conn->prepare("SELECT COALESCE(MAX(menu_order), 0) AS mx FROM pages WHERE parent_id=? FOR UPDATE");
    $stmt->bind_param("i", $parentId);
    $stmt->execute();
    $mx = (int)($stmt->get_result()->fetch_assoc()['mx'] ?? 0);
    $stmt->close();

    // 4) Přesuň děti k rodiči a dej jim pořadí za konec (zachováme relativní pořadí)
    if (!empty($children)) {
        $upd = $conn->prepare("UPDATE pages SET parent_id=?, menu_order=? WHERE id=?");
        foreach ($children as $cid) {
            $mx += 10;
            $upd->bind_param("iii", $parentId, $mx, $cid);
            $upd->execute();
        }
        $upd->close();
    }

    // 5) Smaž samotnou stránku
    $del = $conn->prepare("DELETE FROM pages WHERE id=? LIMIT 1");
    $del->bind_param("i", $id);
    $del->execute();
    $del->close();

    // 6) Znormalizuj pořadí sourozenců u cílového rodiče (10,20,30…)
    $stmt = $conn->prepare("
        SELECT id
        FROM pages
        WHERE parent_id=?
        ORDER BY menu_order ASC, id ASC
        FOR UPDATE
    ");
    $stmt->bind_param("i", $parentId);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    $upd = $conn->prepare("UPDATE pages SET menu_order=? WHERE id=?");
    $order = 10;
    while ($row = $res->fetch_assoc()) {
        $pid = (int)$row['id'];
        $upd->bind_param("ii", $order, $pid);
        $upd->execute();
        $order += 10;
    }
    $upd->close();
    logAction("Smazána stránka '{$title}' (ID {$id})");

    $conn->commit();
    header("Location: list.php?deleted=1");
    exit;

} catch (Exception $e) {
    $conn->rollback();
    // případně zalogovat: $e->getMessage()
    header("Location: list.php?error=delete_failed");
    exit;
}
