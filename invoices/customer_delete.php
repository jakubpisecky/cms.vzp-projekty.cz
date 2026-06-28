<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('invoices');

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $conn->prepare("SELECT name FROM invoice_customers WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $customer = $res->fetch_assoc();
        $stmt->close();
    }

    $stmt = $conn->prepare("DELETE FROM invoice_customers WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }

    if (function_exists('logAction')) {
        $name = $customer['name'] ?? '';
        logAction("Smazán odběratel ID {$id}: {$name}");
    }
}

header("Location: customers.php?deleted=1");
exit;