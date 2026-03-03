<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('articles');
$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $conn->prepare("SELECT title FROM articles WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($title);
    $stmt->fetch();
    $stmt->close();

    if ($title) {
        // smažeme článek
        $stmt = $conn->prepare("DELETE FROM articles WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        logAction("Smazán článek '$title'");
    }
}

header("Location: list.php?deleted=1");
exit;
