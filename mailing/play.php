<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('mailing');

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $conn->prepare("
        UPDATE mailing_campaigns
        SET status = 'queued'
        WHERE id = ?
          AND status = 'stopped'
    ");

    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }

    if (function_exists('logAction')) {
        logAction("Obnovena mailing kampaň ID {$id}");
    }
}

header("Location: list.php?played=1");
exit;