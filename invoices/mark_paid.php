<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('invoices');

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $conn->prepare("
        UPDATE invoices
        SET status = 'paid'
        WHERE id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    if (function_exists('logAction')) {
        logAction("Faktura ID {$id} označena jako uhrazená");
    }
}

header("Location: list.php?paid=1");
exit;