<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('pages');

header('Content-Type: application/json; charset=UTF-8');

$id  = intval($_POST['id'] ?? 0);
$dir = $_POST['dir'] ?? '';

if (!$id || !in_array($dir, ['up','down'], true)) {
    echo json_encode(['ok' => false, 'msg' => 'Neplatný požadavek']); exit;
}

$conn->begin_transaction();

try {
    // 1) Načti aktuální stránku (kvůli parent_id) + lock
    $stmt = $conn->prepare("SELECT id, parent_id FROM pages WHERE id=? FOR UPDATE");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $cur = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$cur) {
        throw new Exception('Stránka nenalezena.');
    }

    $parentId = (int)$cur['parent_id'];

    // 2) Natáhni a ZAMKNI sourozence v pořadí, ve kterém je chceme zobrazovat
    $stmt = $conn->prepare("
        SELECT id, menu_order
        FROM pages
        WHERE parent_id=?
        ORDER BY menu_order ASC, id ASC
        FOR UPDATE
    ");
    $stmt->bind_param("i", $parentId);
    $stmt->execute();
    $siblings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!$siblings) {
        $conn->commit();
        echo json_encode(['ok' => true]); exit;
    }

    // 3) NORMALIZACE: 10,20,30… (jen pokud je potřeba)
    $needsNormalize = false;
    $expect = 10;
    foreach ($siblings as $s) {
        if ((int)$s['menu_order'] !== $expect) { $needsNormalize = true; break; }
        $expect += 10;
    }

    if ($needsNormalize) {
        $expect = 10;
        $upd = $conn->prepare("UPDATE pages SET menu_order=? WHERE id=?");
        foreach ($siblings as &$s) {
            if ((int)$s['menu_order'] !== $expect) {
                $upd->bind_param("ii", $expect, $s['id']);
                $upd->execute();
                $s['menu_order'] = $expect;
            }
            $expect += 10;
        }
        $upd->close();
        unset($s);
    }

    // 4) Najdi index aktuální stránky a souseda
    $idx = -1;
    foreach ($siblings as $i => $s) {
        if ((int)$s['id'] === $id) { $idx = $i; break; }
    }
    if ($idx === -1) throw new Exception('Interní chyba: prvek není mezi sourozenci.');

    $targetIdx = ($dir === 'up') ? $idx - 1 : $idx + 1;
    if ($targetIdx < 0 || $targetIdx >= count($siblings)) {
        // okraj – nic neměníme
        $conn->commit();
        echo json_encode(['ok' => true, 'edge' => true]); exit;
    }

    $curOrder    = (int)$siblings[$idx]['menu_order'];
    $targetId    = (int)$siblings[$targetIdx]['id'];
    $targetOrder = (int)$siblings[$targetIdx]['menu_order'];

    // 5) SWAP pořadí
    $u = $conn->prepare("UPDATE pages SET menu_order=? WHERE id=?");
    $u->bind_param("ii", $targetOrder, $id);     $u->execute();
    $u->bind_param("ii", $curOrder,   $targetId); $u->execute();
    $u->close();

    $conn->commit();
    echo json_encode(['ok' => true]); exit;

} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]); exit;
}
