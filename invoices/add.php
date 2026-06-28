<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/settings_helpers.php";

requirePermission('invoices');

$defaultDueDays = (int)(function_exists('setting') ? setting('invoice_default_due_days') : 14);
if ($defaultDueDays <= 0) $defaultDueDays = 14;

$defaultCurrency = trim((string)(function_exists('setting') ? setting('invoice_default_currency') : 'CZK'));
if ($defaultCurrency === '') $defaultCurrency = 'CZK';

$isVatPayer = (int)(function_exists('setting') ? setting('invoice_is_vat_payer') : 1);

$allowedSeries = ['FV', 'ZAL', 'DOB'];
$invoiceNumbersBySeries = [];

foreach ($allowedSeries as $s) {
    $invoiceNumbersBySeries[$s] = generateInvoiceNumber($conn, $s);
}

$customers = [];
$res = $conn->query("SELECT id, name FROM invoice_customers ORDER BY name ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $customers[] = $row;
    }
}

$series = 'FV';
$invoice_number = $invoiceNumbersBySeries[$series] ?? generateInvoiceNumber($conn, $series);

$customer_id = '';
$issue_date = date('Y-m-d');
$due_date = date('Y-m-d', strtotime('+' . $defaultDueDays . ' days'));
$taxable_date = date('Y-m-d');
$status = 'issued';
$currency = $defaultCurrency;
$note = '';
$error = '';

$items = [
    [
        'title' => '',
        'quantity' => '1',
        'unit' => 'ks',
        'unit_price' => '0',
        'vat_rate' => $isVatPayer ? '21' : '0'
    ]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $series = trim((string)($_POST['series'] ?? 'FV'));
    if (!in_array($series, $allowedSeries, true)) {
        $series = 'FV';
    }

    $invoice_number = generateInvoiceNumber($conn, $series);

    $customer_id = (int)($_POST['customer_id'] ?? 0);
    $issue_date = trim($_POST['issue_date'] ?? '');
    $due_date = trim($_POST['due_date'] ?? '');
    $taxable_date = trim($_POST['taxable_date'] ?? '');
    $status = trim($_POST['status'] ?? 'issued');
    $currency = trim($_POST['currency'] ?? $defaultCurrency);
    $note = trim($_POST['note'] ?? '');

    $titles = $_POST['item_title'] ?? [];
    $quantities = $_POST['item_quantity'] ?? [];
    $units = $_POST['item_unit'] ?? [];
    $prices = $_POST['item_unit_price'] ?? [];
    $vats = $_POST['item_vat_rate'] ?? [];

    $items = [];

    foreach ($titles as $i => $title) {
        $title = trim((string)$title);
        $quantity = (float)str_replace(',', '.', (string)($quantities[$i] ?? 0));
        $unit = trim((string)($units[$i] ?? 'ks'));
        $unit_price = (float)str_replace(',', '.', (string)($prices[$i] ?? 0));
        $vat_rate = $isVatPayer
            ? (float)str_replace(',', '.', (string)($vats[$i] ?? 21))
            : 0.0;

        if ($title === '') {
            continue;
        }

        $items[] = [
            'title' => $title,
            'quantity' => $quantity,
            'unit' => $unit,
            'unit_price' => $unit_price,
            'vat_rate' => $vat_rate
        ];
    }

    if ($invoice_number === '') {
        $error = "Číslo faktury je povinné.";
    } elseif ($customer_id <= 0) {
        $error = "Vyber odběratele.";
    } elseif ($issue_date === '' || $due_date === '') {
        $error = "Vyplň datum vystavení a datum splatnosti.";
    } elseif (empty($items)) {
        $error = "Přidej alespoň jednu položku.";
    } elseif (!in_array($status, ['issued','paid','overdue'], true)) {
        $error = "Neplatný stav faktury.";
    } else {
        $total_without_vat = 0.0;
        $total_vat = 0.0;
        $total_with_vat = 0.0;

        foreach ($items as $item) {
            $line_without_vat = $item['quantity'] * $item['unit_price'];

            if ($isVatPayer) {
                $line_vat = $line_without_vat * ($item['vat_rate'] / 100);
                $line_with_vat = $line_without_vat + $line_vat;
            } else {
                $line_vat = 0.0;
                $line_with_vat = $line_without_vat;
            }

            $total_without_vat += $line_without_vat;
            $total_vat += $line_vat;
            $total_with_vat += $line_with_vat;
        }

        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("
                INSERT INTO invoices
                (invoice_number, series, customer_id, issue_date, due_date, taxable_date, status, currency, note, total_without_vat, total_vat, total_with_vat)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                throw new Exception("Nepodařilo se připravit SQL dotaz: " . $conn->error);
            }

            $stmt->bind_param(
                "ssissssssddd",
                $invoice_number,
                $series,
                $customer_id,
                $issue_date,
                $due_date,
                $taxable_date,
                $status,
                $currency,
                $note,
                $total_without_vat,
                $total_vat,
                $total_with_vat
            );

            $stmt->execute();
            $invoice_id = (int)$stmt->insert_id;
            $stmt->close();

            $stmt = $conn->prepare("
                INSERT INTO invoice_items
                (invoice_id, item_order, title, quantity, unit, unit_price, vat_rate, total_without_vat, total_vat, total_with_vat)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                throw new Exception("Nepodařilo se připravit SQL dotaz položek: " . $conn->error);
            }

            foreach ($items as $order => $item) {
                $line_without_vat = $item['quantity'] * $item['unit_price'];

                if ($isVatPayer) {
                    $line_vat = $line_without_vat * ($item['vat_rate'] / 100);
                    $line_with_vat = $line_without_vat + $line_vat;
                } else {
                    $line_vat = 0.0;
                    $line_with_vat = $line_without_vat;
                }

                $item_order = $order + 1;

                $stmt->bind_param(
                    "iisdsddddd",
                    $invoice_id,
                    $item_order,
                    $item['title'],
                    $item['quantity'],
                    $item['unit'],
                    $item['unit_price'],
                    $item['vat_rate'],
                    $line_without_vat,
                    $line_vat,
                    $line_with_vat
                );
                $stmt->execute();
            }

            $stmt->close();
            $conn->commit();

            if (function_exists('logAction')) {
                logAction("Vytvořena faktura ID {$invoice_id}: {$invoice_number}");
            }

            header("Location: list.php?created=1");
            exit;

        } catch (Throwable $e) {
            $conn->rollback();
            $error = "Nepodařilo se uložit fakturu: " . $e->getMessage();
        }
    }
}

if (empty($items)) {
    $items[] = [
        'title' => '',
        'quantity' => '1',
        'unit' => 'ks',
        'unit_price' => '0',
        'vat_rate' => $isVatPayer ? '21' : '0'
    ];
}

include "../includes/header.php";
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-12">

            <div class="admin-page-header-content mb-4">
                <div>
                    <h2 class="mb-1">Nová faktura</h2>
                    <p class="text-muted mb-0">Vystavení nové faktury</p>
                </div>
                    <a href="list.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Zpět
                    </a>
            </div>

            <?php include "_menu.php"; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" class="card shadow-sm border-0">
                <div class="card-body">

                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Číslo faktury</label>
                            <input type="text" id="invoice_number_preview" class="form-control" value="<?= htmlspecialchars($invoice_number) ?>" readonly>
                            <div class="form-text">Číslo se vytvoří automaticky podle typu faktury.</div>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Odběratel</label>
                            <select name="customer_id" class="form-select" required>
                                <option value="">-- Vyber odběratele --</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?= (int)$customer['id'] ?>" <?= (int)$customer_id === (int)$customer['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($customer['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Stav</label>
                            <select name="status" class="form-select">
                                <option value="issued" <?= $status === 'issued' ? 'selected' : '' ?>>Vystavená</option>
                                <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Uhrazená</option>
                                <option value="overdue" <?= $status === 'overdue' ? 'selected' : '' ?>>Po splatnosti</option>
                            </select>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Typ faktury</label>
                            <select name="series" id="series" class="form-select">
                                <option value="FV" <?= $series === 'FV' ? 'selected' : '' ?>>Faktura</option>
                                <option value="ZAL" <?= $series === 'ZAL' ? 'selected' : '' ?>>Zálohová faktura</option>
                                <option value="DOB" <?= $series === 'DOB' ? 'selected' : '' ?>>Dobropis</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Datum vystavení</label>
                            <input type="date" name="issue_date" class="form-control" value="<?= htmlspecialchars($issue_date) ?>" required>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Datum splatnosti</label>
                            <input type="date" name="due_date" class="form-control" value="<?= htmlspecialchars($due_date) ?>" required>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">DUZP</label>
                            <input type="date" name="taxable_date" class="form-control" value="<?= htmlspecialchars($taxable_date) ?>">
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Měna</label>
                            <input type="text" name="currency" class="form-control" value="<?= htmlspecialchars($currency) ?>">
                        </div>
                    </div>

                    <?php if (!$isVatPayer): ?>
                        <div class="alert alert-info">
                            Dodavatel je nastaven jako <strong>neplátce DPH</strong>. DPH se na této faktuře nepočítá.
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Položky faktury</label>

                        <div class="table-responsive invoice-items-responsive">
                            <table class="table table-bordered align-middle" id="invoice-items-table">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 40%">Název</th>
                                        <th>Množství</th>
                                        <th>Jednotka</th>
                                        <th>Cena za ks</th>
                                        <?php if ($isVatPayer): ?>
                                            <th>DPH %</th>
                                        <?php endif; ?>
                                        <th>Celkem<?= $isVatPayer ? ' s DPH' : '' ?></th>
                                        <th></th>
                                    </tr>
                                </thead>

                                <tbody id="invoice-items-body">
                                    <?php foreach ($items as $item): ?>
                                        <tr class="invoice-item-row">
                                            <td data-label="Název">
                                                <input type="text"
                                                       name="item_title[]"
                                                       class="form-control item-title"
                                                       value="<?= htmlspecialchars((string)($item['title'] ?? '')) ?>">
                                            </td>

                                            <td data-label="Množství">
                                                <input type="number"
                                                       step="0.01"
                                                       name="item_quantity[]"
                                                       class="form-control item-qty"
                                                       value="<?= htmlspecialchars((string)($item['quantity'] ?? '1')) ?>">
                                            </td>

                                            <td data-label="Jednotka">
                                                <input type="text"
                                                       name="item_unit[]"
                                                       class="form-control item-unit"
                                                       value="<?= htmlspecialchars((string)($item['unit'] ?? 'ks')) ?>">
                                            </td>

                                            <td data-label="Cena za ks">
                                                <input type="number"
                                                       step="0.01"
                                                       name="item_unit_price[]"
                                                       class="form-control item-price"
                                                       value="<?= htmlspecialchars((string)($item['unit_price'] ?? '0')) ?>">
                                            </td>

                                            <?php if ($isVatPayer): ?>
                                                <td data-label="DPH %">
                                                    <input type="number"
                                                           step="0.01"
                                                           name="item_vat_rate[]"
                                                           class="form-control item-vat"
                                                           value="<?= htmlspecialchars((string)($item['vat_rate'] ?? '21')) ?>">
                                                </td>
                                            <?php else: ?>
                                                <input type="hidden" name="item_vat_rate[]" value="0">
                                            <?php endif; ?>

                                            <td data-label="Celkem" class="item-total text-nowrap">
                                                0,00 <?= htmlspecialchars($currency) ?>
                                            </td>

                                            <td data-label="Akce" class="text-end">
                                                <button type="button" class="btn btn-sm btn-outline-danger remove-item">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <button type="button" class="btn btn-outline-primary btn-sm" id="add-item-btn">
                            <i class="bi bi-plus-lg"></i> Přidat položku
                        </button>
                    </div>

                    <div class="row justify-content-end">
                        <div class="col-md-4">
                            <table class="table">
                                <tr>
                                    <th><?= $isVatPayer ? 'Bez DPH' : 'Celkem' ?></th>
                                    <td class="text-end" id="sum-without-vat">0,00 <?= htmlspecialchars($currency) ?></td>
                                </tr>

                                <?php if ($isVatPayer): ?>
                                    <tr>
                                        <th>DPH</th>
                                        <td class="text-end" id="sum-vat">0,00 <?= htmlspecialchars($currency) ?></td>
                                    </tr>
                                <?php endif; ?>

                                <tr>
                                    <th>Celkem</th>
                                    <td class="text-end fw-bold" id="sum-with-vat">0,00 <?= htmlspecialchars($currency) ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Poznámka</label>
                        <textarea name="note" rows="4" class="form-control"><?= htmlspecialchars($note) ?></textarea>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="list.php" class="btn btn-outline-secondary">Zrušit</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Uložit fakturu
                        </button>
                    </div>

                </div>
            </form>

        </div>
    </div>
</div>

<style>
@media (max-width: 767.98px) {
    #invoice-items-table {
        border: 0;
        margin-bottom: 0;
    }

    #invoice-items-table thead {
        display: none;
    }

    #invoice-items-table,
    #invoice-items-table tbody,
    #invoice-items-table tr,
    #invoice-items-table td {
        display: block;
        width: 100%;
    }

    #invoice-items-table tr.invoice-item-row {
        border: 1px solid #dee2e6;
        border-radius: 0.75rem;
        padding: 0.75rem;
        margin-bottom: 1rem;
        background: #fff;
    }

    #invoice-items-table td {
        border: 0;
        padding: 0 0 0.75rem 0;
    }

    #invoice-items-table td:last-child {
        padding-bottom: 0;
    }

    #invoice-items-table td::before {
        content: attr(data-label);
        display: block;
        font-size: 0.8rem;
        font-weight: 600;
        color: #6c757d;
        margin-bottom: 0.25rem;
    }

    #invoice-items-table .item-title {
        min-height: 42px;
    }

    #invoice-items-table .item-total {
        font-weight: 700;
        font-size: 1rem;
        text-align: left !important;
        padding-top: 0.25rem;
    }

    #invoice-items-table td[data-label="Akce"] {
        text-align: left !important;
    }

    .invoice-items-responsive {
        overflow-x: visible;
    }
}
</style>

<script>
(function () {
    const body = document.getElementById('invoice-items-body');
    const addBtn = document.getElementById('add-item-btn');
    const isVatPayer = <?= $isVatPayer ? 'true' : 'false' ?>;
    const currency = <?= json_encode($currency, JSON_UNESCAPED_UNICODE) ?>;

    function formatMoney(value) {
        return new Intl.NumberFormat('cs-CZ', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(value) + ' ' + currency;
    }

    function recalc() {
        let sumWithoutVat = 0;
        let sumVat = 0;
        let sumWithVat = 0;

        body.querySelectorAll('tr').forEach(row => {
            const qty = parseFloat(row.querySelector('.item-qty')?.value || 0);
            const price = parseFloat(row.querySelector('.item-price')?.value || 0);
            const vat = isVatPayer ? parseFloat(row.querySelector('.item-vat')?.value || 0) : 0;

            const lineWithoutVat = qty * price;
            const lineVat = isVatPayer ? lineWithoutVat * (vat / 100) : 0;
            const lineWithVat = lineWithoutVat + lineVat;

            const totalCell = row.querySelector('.item-total');
            if (totalCell) {
                totalCell.textContent = formatMoney(lineWithVat);
            }

            sumWithoutVat += lineWithoutVat;
            sumVat += lineVat;
            sumWithVat += lineWithVat;
        });

        document.getElementById('sum-without-vat').textContent = formatMoney(sumWithoutVat);

        if (isVatPayer && document.getElementById('sum-vat')) {
            document.getElementById('sum-vat').textContent = formatMoney(sumVat);
        }

        document.getElementById('sum-with-vat').textContent = formatMoney(sumWithVat);
    }

    addBtn.addEventListener('click', function () {
        const tr = document.createElement('tr');
        tr.className = 'invoice-item-row';

        tr.innerHTML = `
            <td data-label="Název">
                <input type="text" name="item_title[]" class="form-control item-title">
            </td>

            <td data-label="Množství">
                <input type="number" step="0.01" name="item_quantity[]" class="form-control item-qty" value="1">
            </td>

            <td data-label="Jednotka">
                <input type="text" name="item_unit[]" class="form-control item-unit" value="ks">
            </td>

            <td data-label="Cena za ks">
                <input type="number" step="0.01" name="item_unit_price[]" class="form-control item-price" value="0">
            </td>

            ${isVatPayer
                ? '<td data-label="DPH %"><input type="number" step="0.01" name="item_vat_rate[]" class="form-control item-vat" value="21"></td>'
                : '<input type="hidden" name="item_vat_rate[]" value="0">'}

            <td data-label="Celkem" class="item-total text-nowrap">0,00 ${currency}</td>

            <td data-label="Akce" class="text-end">
                <button type="button" class="btn btn-sm btn-outline-danger remove-item">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;

        body.appendChild(tr);
        recalc();
    });

    body.addEventListener('input', function (e) {
        if (
            e.target.classList.contains('item-qty') ||
            e.target.classList.contains('item-price') ||
            e.target.classList.contains('item-vat')
        ) {
            recalc();
        }
    });

    body.addEventListener('click', function (e) {
        const btn = e.target.closest('.remove-item');
        if (!btn) return;

        const rows = body.querySelectorAll('tr');
        if (rows.length <= 1) {
            return;
        }

        btn.closest('tr').remove();
        recalc();
    });

    recalc();
})();
</script>
<script>
(function () {
    const seriesSelect = document.getElementById('series');
    const numberInput = document.getElementById('invoice_number_preview');

    const invoiceNumbers = <?= json_encode($invoiceNumbersBySeries, JSON_UNESCAPED_UNICODE) ?>;

    if (!seriesSelect || !numberInput) return;

    seriesSelect.addEventListener('change', function () {
        const selectedSeries = this.value;

        if (invoiceNumbers[selectedSeries]) {
            numberInput.value = invoiceNumbers[selectedSeries];
        }
    });
})();
</script>

<?php include "../includes/footer.php"; ?>