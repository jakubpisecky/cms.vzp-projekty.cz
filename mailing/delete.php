<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('mailing');

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {

    // pro log
    $stmt = $conn->prepare("SELECT title FROM mailing_campaigns WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $campaign = $res->fetch_assoc();
        $stmt->close();
    }

    // smažeme jen kampaň
    $stmt = $conn->prepare("DELETE FROM mailing_campaigns WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }

    if (function_exists('logAction')) {
        $title = $campaign['title'] ?? '';
        logAction("Smazána mailing kampaň ID {$id}: {$title}");
    }
}

header("Location: list.php?deleted=1");
exit;