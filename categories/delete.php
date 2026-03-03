<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('categories');
$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $conn->prepare("SELECT name FROM categories WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($name);
    $stmt->fetch();
    $stmt->close();

    if ($name) {
        // smažeme vazby na články
        $conn->query("DELETE FROM article_category WHERE category_id=$id");

        // smažeme samotnou kategorii
        $stmt = $conn->prepare("DELETE FROM categories WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        logAction("Smazána kategorie '$name'");
    }
}

header("Location: list.php?deleted=1");
exit;
