<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('invoices');

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {

    $conn->begin_transaction();

    try {
        // smazat položky
        $stmt = $conn->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        // smazat fakturu
        $stmt = $conn->prepare("DELETE FROM invoices WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        if (function_exists('logAction')) {
            logAction("Smazána faktura ID {$id}");
        }

    } catch (Throwable $e) {
        $conn->rollback();
    }
}

header("Location: list.php?deleted=1");
exit;