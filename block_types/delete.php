<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('settings');

$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $conn->prepare("
        SELECT name, type
        FROM block_types
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($name, $type);
    $stmt->fetch();
    $stmt->close();

    if ($name) {
        $stmt = $conn->prepare("
            DELETE FROM block_types
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        logAction("Smazán typ bloku '$name' ($type)");
    }
}

header("Location: list.php?deleted=1");
exit;