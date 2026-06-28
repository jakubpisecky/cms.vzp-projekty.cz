<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('mailing');

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $conn->prepare("SELECT title FROM mailing_templates WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $template = $res->fetch_assoc();
        $stmt->close();
    }

    $stmt = $conn->prepare("DELETE FROM mailing_templates WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }

    if (function_exists('logAction')) {
        $title = $template['title'] ?? '';
        logAction("Smazána mailing šablona ID {$id}: {$title}");
    }
}

header("Location: templates.php?deleted=1");
exit;