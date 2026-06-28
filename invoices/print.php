<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/settings_helpers.php";

requirePermission('invoices');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Neplatné ID faktury.");
}

$isVatPayer = (int)(function_exists('setting') ? setting('invoice_is_vat_payer') : 1);

$stmt = $conn->prepare("
    SELECT i.*, c.name AS customer_name, c.ico AS customer_ico, c.dic AS customer_dic,
           c.street AS customer_street, c.city AS customer_city, c.zip AS customer_zip,
           c.country AS customer_country, c.email AS customer_email, c.phone AS customer_phone,
           c.note AS customer_note
    FROM invoices i
    LEFT JOIN invoice_customers c ON c.id = i.customer_id
    WHERE i.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$invoice) {
    die("Faktura nebyla nalezena.");
}

$invoiceItems = [];
$stmt = $conn->prepare("
    SELECT *
    FROM invoice_items
    WHERE invoice_id = ?
    ORDER BY item_order ASC, id ASC
");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $invoiceItems[] = $row;
}
$stmt->close();

$renderMode = 'print';

include __DIR__ . "/_template.php";