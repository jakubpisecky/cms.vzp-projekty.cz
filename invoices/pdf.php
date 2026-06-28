<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/settings_helpers.php";

require_once __DIR__ . '/../libs/dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

requirePermission('invoices');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Neplatné ID faktury.");
}

// TOHLE TAM CHYBĚLO
$isVatPayer = (int)(function_exists('setting') ? setting('invoice_is_vat_payer') : 1);

// načtení faktury + odběratele
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

// položky faktury
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

// režim pro šablonu
$renderMode = 'pdf';

// vyrenderování HTML
ob_start();
include __DIR__ . "/_template.php";
$html = ob_get_clean();

// Dompdf
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'faktura-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)$invoice['invoice_number']) . '.pdf';

$dompdf->stream($filename, [
    'Attachment' => true
]);
exit;