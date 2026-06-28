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

include "../includes/header.php";
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12 col-xl-10 offset-xl-1">

            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3 mb-4 print-hide">
                <div>
                    <h2 class="mb-1">Detail faktury</h1>
                    <p class="text-muted mb-0"><?= htmlspecialchars($invoice['invoice_number']) ?></p>
                </div>
                <div class="d-flex flex-wrap gap-2 invoice-actions">
                    <a href="list.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Zpět
                    </a>
                    <a href="edit.php?id=<?= (int)$invoice['id'] ?>" class="btn btn-outline-primary">
                        <i class="bi bi-pencil"></i> Upravit
                    </a>
                    <a href="pdf.php?id=<?= (int)$invoice['id'] ?>" class="btn btn-outline-dark">
                        <i class="bi bi-file-earmark-pdf"></i> PDF
                    </a>
                    <a href="send_email.php?id=<?= (int)$invoice['id'] ?>" class="btn btn-outline-success">
                        <i class="bi bi-envelope"></i> Odeslat e-mailem
                    </a>
                    <a href="print.php?id=<?= (int)$invoice['id'] ?>"
                    class="btn btn-primary"
                    target="_blank">
                        <i class="bi bi-printer"></i> Tisk
                    </a>
                </div>
            </div>

            <?php include "_menu.php"; ?>
            <?php if (isset($_GET['sent'])): ?>
                <div class="alert alert-success print-hide">
                    Faktura byla odeslána e-mailem.
                </div>
            <?php endif; ?>
            <div class="card shadow-sm border-0 invoice-card">
                <div class="card-body p-2 p-lg-4">
                    <div class="invoice-wrapper">
                        <?php include "_template.php"; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .print-hide,
    .navbar,
    .sidebar,
    footer,
    .nav,
    .btn,
    .admin-wrap > aside {
        display: none !important;
    }

    body {
        background: #fff !important;
    }

    .card {
        border: 0 !important;
        box-shadow: none !important;
    }

    .container-fluid,
    .row,
    .col-lg-10,
    .offset-lg-1 {
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        max-width: none !important;
    }
}
.invoice-actions .btn {
    white-space: nowrap;
}

@media (max-width: 991.98px) {

    .invoice-actions {
        width: 100%;
    }

    .invoice-actions .btn {
        flex: 1 1 calc(50% - .5rem);
        justify-content: center;
    }
}

@media (max-width: 575.98px) {

    .invoice-actions .btn {
        flex: 1 1 100%;
    }
}
.invoice-card {
    overflow: hidden;
}

.invoice-wrapper {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

/* zabrání přetečení obrázků */
.invoice-wrapper img {
    max-width: 100%;
    height: auto;
}

/* tabulky */
.invoice-wrapper table {
    width: 100%;
}

/* mobil */
@media (max-width: 767.98px) {

    .invoice-wrapper {
        font-size: .92rem;
    }

    .invoice-wrapper table td,
    .invoice-wrapper table th {
        padding: .45rem;
    }

    .invoice-wrapper h1,
    .invoice-wrapper h2,
    .invoice-wrapper h3 {
        word-break: break-word;
    }
}
</style>

<?php include "../includes/footer.php"; ?>