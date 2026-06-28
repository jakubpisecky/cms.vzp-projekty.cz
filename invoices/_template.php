<?php
$renderMode = $renderMode ?? 'preview';
$isVatPayer = isset($isVatPayer) ? (int)$isVatPayer : 1;

function money($v, $c = 'CZK') {
    return number_format((float)$v, 2, ',', ' ') . ' ' . htmlspecialchars($c);
}

function d($date) {
    if (!$date) return '';
    $ts = strtotime((string)$date);
    return $ts ? date('d. m. Y', $ts) : htmlspecialchars((string)$date);
}

function getContrastColor($hex) {
    $hex = str_replace('#', '', $hex);

    if (strlen($hex) !== 6) return '#000000';

    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    // výpočet jasu
    $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;

    // světlé pozadí → černý text
    // tmavé pozadí → bílý text
    return ($brightness > 150) ? '#000000' : '#ffffff';
}

$supplierName    = (string)setting('invoice_supplier_name');
$supplierIco     = (string)setting('invoice_supplier_ico');
$supplierDic     = (string)setting('invoice_supplier_dic');
$supplierStreet  = (string)setting('invoice_supplier_street');
$supplierCity    = (string)setting('invoice_supplier_city');
$supplierZip     = (string)setting('invoice_supplier_zip');
$supplierCountry = (string)setting('invoice_supplier_country');
$supplierLogo    = (string)setting('invoice_supplier_logo');

$accountNumber  = (string)setting('invoice_supplier_account_number');
$bankCode       = (string)setting('invoice_supplier_bank_code');
$accountDisplay = trim($accountNumber . ($bankCode !== '' ? '/' . $bankCode : ''));

$footerNote = (string)setting('invoice_footer_note');

$invoiceColorPrimaryText = (string)setting('invoice_color_primary_text', '');
$invoiceColorSecondaryText = (string)setting('invoice_color_secondary_text', '');

// validace nebo fallback kontrastu
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $invoiceColorPrimaryText)) {
    $invoiceColorPrimaryText = getContrastColor($invoiceColorPrimary);
}

if (!preg_match('/^#[0-9a-fA-F]{6}$/', $invoiceColorSecondaryText)) {
    $invoiceColorSecondaryText = getContrastColor($invoiceColorSecondary);
}

$invoiceColorPrimary = (string)setting('invoice_color_primary', '#eebc00');
$invoiceColorSecondary = (string)setting('invoice_color_secondary', '#eeeeee');

if (!preg_match('/^#[0-9a-fA-F]{6}$/', $invoiceColorPrimary)) {
    $invoiceColorPrimary = '#eebc00';
}

if (!preg_match('/^#[0-9a-fA-F]{6}$/', $invoiceColorSecondary)) {
    $invoiceColorSecondary = '#eeeeee';
}

$variableSymbol = preg_replace('/\D+/', '', (string)$invoice['invoice_number']);
if ($variableSymbol === '') {
    $variableSymbol = (string)$invoice['id'];
}

$qrImageUrl = '';
if (function_exists('generateCzQrPaymentUrl')) {
    $qrImageUrl = generateCzQrPaymentUrl(
        $accountNumber,
        $bankCode,
        (float)$invoice['total_with_vat'],
        (string)$invoice['currency'],
        $variableSymbol,
        'Faktura ' . $invoice['invoice_number']
    );
}

$logoSrc = $supplierLogo;

if (($renderMode === 'pdf' || $renderMode === 'print') && $supplierLogo !== '' && function_exists('imageFileToBase64')) {
    $logoPath = $_SERVER['DOCUMENT_ROOT'] . parse_url($supplierLogo, PHP_URL_PATH);
    $logoBase64 = imageFileToBase64($logoPath);

    if ($logoBase64 !== '') {
        $logoSrc = $logoBase64;
    }
}
?>

<style>
@page {
    size: A4 portrait;
    margin: 12mm;
}

html,
body {
    background: #fff;
}

.invoice {
    width: 650px;
    margin: 0 auto;
    padding: 26px 30px 36px;
    box-sizing: border-box;
    background: #fff;
    color: #111;
    font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
    font-size: 12px;
    line-height: 1.45;
}

.invoice-mode-print {
    margin-top: 30px;
    margin-bottom: 30px;
}

.invoice .top {
    display: table;
    width: 100%;
    margin-bottom: 26px;
}

.invoice .top-left,
.invoice .top-right {
    display: table-cell;
    vertical-align: top;
    width: 50%;
}

.invoice .top-right {
    text-align: right;
}

.invoice .logo {
    max-width: 220px;
    max-height: 70px;
    display: block;
}

.invoice .invoice-title {
    font-size: 20px;
    font-weight: 800;
    letter-spacing: 0.01em;
    margin: 0;
    line-height: 1.1;
}

.invoice .invoice-title span {
    color: #475467;
}

.invoice .invoice-subtitle {
    font-size: 15px;
    color: #667085;
    margin-top: 2px;
    line-height: 1.2;
}

.invoice .highlight {
    background: <?= htmlspecialchars($invoiceColorPrimary) ?>;
    color: <?= $invoiceColorPrimaryText ?>;
    padding: 12px 22px;
}

.invoice .highlight-wrap {
    display: table;
    width: 100%;
}

.invoice .highlight-left,
.invoice .highlight-right {
    display: table-cell;
    vertical-align: top;
}

.invoice .highlight-left {
    width: 38%;
}

.invoice .highlight-right {
    width: 62%;
}

.invoice .pay-label {
    font-size: 12px;
    margin-bottom: 6px;
    line-height: 1.2;
}

.invoice .pay-total {
    font-size: 15px;
    line-height: 1.05;
    font-weight: 800;
    margin: 0;
    white-space: nowrap;
}

.invoice .pay-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
}

.invoice .pay-table td {
    padding: 4px 0 4px 18px;
    vertical-align: top;
    line-height: 1.2;
}

.invoice .pay-table td:first-child {
    width: 170px;
    color: inherit;
    padding-left: 0;
}
.invoice .highlight .pay-table td,
.invoice .highlight .pay-label,
.invoice .highlight .pay-total {
    color: inherit;
}
.invoice .pay-table td:last-child {
    font-weight: 700;
}

.invoice .dates {
    display: table;
    width: 100%;
    margin-bottom: 28px;
    border-collapse: collapse;
}

.invoice .date-box {
    display: table-cell;
    width: 33.333%;
    background: <?= htmlspecialchars($invoiceColorSecondary) ?>;
    color: <?= $invoiceColorSecondaryText ?>;
    padding: 16px 20px 18px;
    border-right: 1px solid rgba(255,255,255,0.85);
    box-sizing: border-box;

}

.invoice .date-box:last-child {
    border-right: 0;
}

.invoice .date-label {
    font-size: 11px;
    margin-bottom: 3px;
    line-height: 1.2;
}

.invoice .date-value {
    font-size: 12px;
    font-weight: 700;
    line-height: 1.2;
}

.invoice .party-section {
    margin-bottom: 30px;
}

.invoice .party-grid {
    display: table;
    width: 100%;
    table-layout: fixed;
}

.invoice .party-col {
    display: table-cell;
    width: 50%;
    vertical-align: top;
    padding-right: 28px;
    box-sizing: border-box;
    font-size: 12px;
    line-height: 1.25;
}

.invoice .party-col:last-child {
    padding-right: 0;
    padding-left: 28px;
}

.invoice .party-label {
    font-size: 12px;
    color: #000;
    margin: 0 0 8px 0;
    font-weight: bold;
    text-transform: uppercase;
    line-height: 1.2;
}

.invoice .party-name {
    font-size: 12px;
    font-weight: 700;
    margin: 0 0 6px 0;
    line-height: 1.2;
}

.invoice .party-line {
    margin: 0 0 2px 0;
    line-height: 1.25;
}

.invoice .party-meta {
    margin-top: 10px;
}

.invoice .party-meta div {
    margin: 0 0 2px 0;
    line-height: 1.25;
}

.invoice .items-title {
    font-size: 11px;
    color: #000;
    margin-bottom: 8px;
    line-height: 1.2;
}

.invoice .items {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 26px;
}

.invoice .items th {
    text-align: left;
    font-size: 11px;
    color: #000;
    padding: 10px 8px;
    border-bottom: 1px solid #bfc5cc;
    line-height: 1.2;
}

.invoice .items td {
    padding: 5px 1px;
    border-bottom: 1px solid #e7eaee;
    font-size: 11px;
    line-height: 1.2;
}

.invoice .right {
    text-align: right;
}

.invoice .summary-table {
    width: 100%;
    border-collapse: collapse;
}

.invoice .summary-table th,
.invoice .summary-table td {
    padding: 8px 0;
    border-bottom: 1px solid #d7dbe0;
    font-size: 15px;
    line-height: 1.2;
}

.invoice .summary-table th {
    text-align: left;
    color: #475467;
    font-weight: 600;
}

.invoice .summary-table td {
    text-align: right;
}

.invoice .summary-total th,
.invoice .summary-total td {
    border-top: 2px solid #111;
    border-bottom: 0;
    font-size: 20px;
    font-weight: 800;
    padding-top: 14px;
    line-height: 1.2;
}

.invoice .note {
    margin-top: 28px;
    padding-top: 16px;
    border-top: 1px solid #ddd;
}

.invoice .note-title {
    font-weight: 700;
    margin-bottom: 6px;
    line-height: 1.2;
}

.invoice .bottom-wrap {
    display: table;
    width: 100%;
    margin-top: 20px;
}

.invoice .bottom-left,
.invoice .bottom-right {
    display: table-cell;
    vertical-align: bottom;
}

.invoice .bottom-left {
    width: 40%;
}

.invoice .bottom-right {
    width: 60%;
    text-align: right;
}

.invoice .qr-title {
    font-size: 13px;
    color: #667085;
    margin-bottom: 6px;
    line-height: 1.2;
}

.invoice .qr-box {
    width: 120px;
    height: 120px;
    border: 1px solid #e5e7eb;
    background: #fff;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.invoice .highlight,
.invoice .highlight * {
    color: <?= htmlspecialchars($invoiceColorPrimaryText) ?> !important;
}

.invoice .dates,
.invoice .dates * {
    color: <?= htmlspecialchars($invoiceColorSecondaryText) ?> !important;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 20mm;
    }

    html,
    body {
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .invoice {
        width: 700px !important;
        margin: 0 auto !important;

        /* Tohle je důležité – NE 0 */
        padding: 50px 30px 36px !important;

        background: #fff !important;
        box-shadow: none !important;
        border: 0 !important;
    }

    .invoice-mode-print {
        margin-top: 0 !important;
        margin-bottom: 0 !important;
    }
    .invoice .highlight,
.invoice .highlight * {
    color: <?= htmlspecialchars($invoiceColorPrimaryText) ?> !important;
}

.invoice .dates,
.invoice .dates * {
    color: <?= htmlspecialchars($invoiceColorSecondaryText) ?> !important;
}
}
.invoice-mode-print {
    margin-top: 30px;
    margin-bottom: 30px;
}
</style>

<div class="invoice invoice-mode-<?= htmlspecialchars($renderMode) ?>">

    <div class="top">
        <div class="top-left">
            <?php if (!empty($logoSrc)): ?>
                <img src="<?= htmlspecialchars($logoSrc) ?>" alt="Logo" class="logo">
            <?php endif; ?>
        </div>

        <div class="top-right">
            <h1 class="invoice-title">FAKTURA <span><?= htmlspecialchars($invoice['invoice_number']) ?></span></h1>
            <div class="invoice-subtitle">Daňový doklad</div>
        </div>
    </div>

    <div class="highlight">
        <div class="highlight-wrap">
            <div class="highlight-left">
                <div class="pay-label">Prosím o zaplacení částky</div>
                <div class="pay-total"><?= money($invoice['total_with_vat'], $invoice['currency']) ?></div>
            </div>

            <div class="highlight-right">
                <table class="pay-table">
                    <?php if ($accountDisplay !== ''): ?>
                        <tr>
                            <td>Bankovní účet</td>
                            <td><?= htmlspecialchars($accountDisplay) ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td>Variabilní symbol</td>
                        <td><?= htmlspecialchars($variableSymbol) ?></td>
                    </tr>
                    <tr>
                        <td>Způsob platby</td>
                        <td>Převodem</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="dates">
        <div class="date-box">
            <div class="date-label">Datum vystavení</div>
            <div class="date-value"><?= d($invoice['issue_date']) ?></div>
        </div>
        <div class="date-box">
            <div class="date-label">Datum splatnosti</div>
            <div class="date-value"><?= d($invoice['due_date']) ?></div>
        </div>
        <div class="date-box">
            <div class="date-label">Datum zdan. plnění</div>
            <div class="date-value"><?= d($invoice['taxable_date']) ?></div>
        </div>
    </div>

    <div class="party-section">
        <div class="party-grid">
            <div class="party-col">
                <div class="party-label">Dodavatel</div>
                <div class="party-name"><?= htmlspecialchars($supplierName) ?></div>

                <?php if ($supplierStreet !== ''): ?>
                    <div class="party-line"><?= htmlspecialchars($supplierStreet) ?></div>
                <?php endif; ?>

                <div class="party-line"><?= htmlspecialchars(trim($supplierZip . ' ' . $supplierCity)) ?></div>

                <?php if ($supplierCountry !== ''): ?>
                    <div class="party-line"><?= htmlspecialchars($supplierCountry) ?></div>
                <?php endif; ?>

                <div class="party-meta">
                    <?php if ($supplierIco !== ''): ?>
                        <div><strong>IČO</strong> <?= htmlspecialchars($supplierIco) ?></div>
                    <?php endif; ?>
                    <?php if ($supplierDic !== ''): ?>
                        <div><strong>DIČ</strong> <?= htmlspecialchars($supplierDic) ?></div>
                    <?php endif; ?>
                    <?php if (!$isVatPayer): ?>
                        <div><strong>Dodavatel není plátce DPH</strong></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="party-col">
                <div class="party-label">Odběratel</div>
                <div class="party-name"><?= htmlspecialchars($invoice['customer_name'] ?? '') ?></div>

                <?php if (!empty($invoice['customer_street'])): ?>
                    <div class="party-line"><?= htmlspecialchars($invoice['customer_street']) ?></div>
                <?php endif; ?>

                <div class="party-line">
                    <?= htmlspecialchars(trim(($invoice['customer_zip'] ?? '') . ' ' . ($invoice['customer_city'] ?? ''))) ?>
                </div>

                <?php if (!empty($invoice['customer_country'])): ?>
                    <div class="party-line"><?= htmlspecialchars($invoice['customer_country']) ?></div>
                <?php endif; ?>

                <div class="party-meta">
                    <?php if (!empty($invoice['customer_ico'])): ?>
                        <div><strong>IČO</strong> <?= htmlspecialchars($invoice['customer_ico']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($invoice['customer_dic'])): ?>
                        <div><strong>DIČ</strong> <?= htmlspecialchars($invoice['customer_dic']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="items-title"><strong>Fakturujeme Vám následující položky:</strong></div>

    <table class="items">
        <thead>
            <tr>
                <th>Množství</th>
                <th>Jedn.</th>
                <th>Položka</th>
                <?php if ($isVatPayer): ?>
                    <th class="right" style="width:90px;">DPH</th>
                <?php endif; ?>
                <th class="right" style="width:150px;">Cena za MJ</th>
                <th class="right" style="width:180px;"><?= $isVatPayer ? 'Celkem bez DPH' : 'Celkem' ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($invoiceItems as $item): ?>
                <tr>
                    <td><?= number_format((float)$item['quantity'], 2, ',', ' ') ?></td>
                    <td><?= htmlspecialchars($item['unit'] ?? '') ?></td>
                    <td><?= htmlspecialchars($item['title']) ?></td>
                    <?php if ($isVatPayer): ?>
                        <td class="right"><?= number_format((float)$item['vat_rate'], 0, ',', ' ') ?> %</td>
                    <?php endif; ?>
                    <td class="right"><?= money($item['unit_price'], $invoice['currency']) ?></td>
                    <td class="right"><?= money($isVatPayer ? $item['total_without_vat'] : $item['total_with_vat'], $invoice['currency']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="bottom-wrap">
        <div class="bottom-left">
            <div class="qr-title">QR platba</div>
            <div class="qr-box">
                <?php if (!empty($qrImageUrl)): ?>
                    <img src="<?= htmlspecialchars($qrImageUrl) ?>"
                         alt="QR platba"
                         style="width:100%; height:100%; object-fit:contain;">
                <?php else: ?>
                    <div style="font-size:12px; color:#999;">
                        QR nelze vygenerovat<br>
                        (zkontrolujte číslo účtu)
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="bottom-right">
            <table class="summary-table">
                <tr>
                    <th><?= $isVatPayer ? 'Celkem bez DPH' : 'Celkem' ?></th>
                    <td><?= money($invoice['total_without_vat'], $invoice['currency']) ?></td>
                </tr>

                <?php if ($isVatPayer): ?>
                    <tr>
                        <th>DPH</th>
                        <td><?= money($invoice['total_vat'], $invoice['currency']) ?></td>
                    </tr>
                <?php endif; ?>

                <tr class="summary-total">
                    <th>Celkem k úhradě</th>
                    <td><?= money($invoice['total_with_vat'], $invoice['currency']) ?></td>
                </tr>
            </table>
        </div>
    </div>

    <?php if (!empty($invoice['note']) || $footerNote !== ''): ?>
        <div class="note">
            <?php if (!empty($invoice['note'])): ?>
                <div class="note-title">Poznámka k faktuře</div>
                <div style="margin-bottom:12px;"><?= nl2br(htmlspecialchars($invoice['note'])) ?></div>
            <?php endif; ?>

            <?php if ($footerNote !== ''): ?>
                <div class="note-title">Doplňující informace</div>
                <div><?= nl2br(e($footerNote)) ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<?php if ($renderMode === 'print'): ?>
<script>
window.addEventListener('load', function () {
    window.print();
});
</script>
<?php endif; ?>