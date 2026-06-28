<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('mailing');

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $conn->prepare("
        UPDATE mailing_recipients
        SET unsubscribed = 0
        WHERE id = ?
    ");

    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }

    if (function_exists('logAction')) {
        logAction("Příjemce ID {$id} byl znovu přihlášen k odběru");
    }
}

header("Location: recipients.php?resubscribed=1");
exit;