<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/settings_helpers.php";

require_once __DIR__ . '/../libs/dompdf/autoload.inc.php';

require_once "../includes/php-mailer/src/Exception.php";
require_once "../includes/php-mailer/src/PHPMailer.php";
require_once "../includes/php-mailer/src/SMTP.php";

use Dompdf\Dompdf;
use Dompdf\Options;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

requirePermission('invoices');

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    die("Neplatné ID faktury.");
}

$isVatPayer = (int)(function_exists('setting') ? setting('invoice_is_vat_payer') : 1);

// faktura + odběratel
$stmt = $conn->prepare("
    SELECT i.*, c.name AS customer_name, c.email AS customer_email,
           c.ico AS customer_ico, c.dic AS customer_dic,
           c.street AS customer_street, c.city AS customer_city,
           c.zip AS customer_zip, c.country AS customer_country,
           c.phone AS customer_phone
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

// položky
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

$to = trim((string)($_POST['to'] ?? ($invoice['customer_email'] ?? '')));
$subject = trim((string)($_POST['subject'] ?? ('Faktura ' . $invoice['invoice_number'])));
$message = trim((string)($_POST['message'] ?? ''));

if ($message === '') {
    $message = "Dobrý den,\n\nv příloze zasíláme fakturu " . $invoice['invoice_number'] . ".\n\nDěkujeme.\n";
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($to === '') {
        $error = "Chybí e-mail příjemce.";
    } else {
        try {
            // PDF do stringu
            $renderMode = 'pdf';

            ob_start();
            include __DIR__ . "/_template.php";
            $html = ob_get_clean();

            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $pdfOutput = $dompdf->output();

            $filename = 'faktura-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)$invoice['invoice_number']) . '.pdf';

            // odeslání mailu
            $mail = new PHPMailer(true);
            $mail->CharSet = 'UTF-8';

            if ((int)setting('smtp_enabled')) {
                $mail->isSMTP();
                $mail->Host = setting('smtp_host');
                $mail->Port = (int)setting('smtp_port');

                $secure = setting('smtp_secure');
                if ($secure === 'ssl') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                } elseif ($secure === 'tls') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                }

                $mail->SMTPAuth = true;
                $mail->Username = setting('smtp_username');
                $mail->Password = setting('smtp_password');
            }

            $fromEmail = setting('smtp_from_email') ?: setting('contact_email');
            $fromName  = setting('smtp_from_name') ?: setting('invoice_supplier_name');

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to, $invoice['customer_name'] ?? '');

            $mail->Subject = $subject;
            $mail->Body = nl2br(htmlspecialchars($message));
            $mail->AltBody = $message;
            $mail->isHTML(true);

            $mail->addStringAttachment($pdfOutput, $filename, 'base64', 'application/pdf');

            $mail->send();

            // pokud byla faktura koncept, posuň ji na vystavenou
            if (($invoice['status'] ?? '') === 'draft') {
                $stmt = $conn->prepare("UPDATE invoices SET status = 'issued' WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();
            }

            if (function_exists('logAction')) {
                logAction("Odeslána faktura ID {$id}: {$invoice['invoice_number']} na {$to}");
            }

            header("Location: detail.php?id=" . $id . "&sent=1");
            exit;

        } catch (Throwable $e) {
            $error = "E-mail se nepodařilo odeslat: " . $e->getMessage();
        }
    }
}

include "../includes/header.php";
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8 offset-lg-2">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1">Odeslat fakturu e-mailem</h1>
                    <p class="text-muted mb-0"><?= htmlspecialchars($invoice['invoice_number']) ?></p>
                </div>
                <a href="detail.php?id=<?= (int)$id ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Zpět
                </a>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" class="card shadow-sm border-0">
                <div class="card-body">
                    <input type="hidden" name="id" value="<?= (int)$id ?>">

                    <div class="mb-3">
                        <label class="form-label">Příjemce</label>
                        <input type="email" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Předmět</label>
                        <input type="text" name="subject" class="form-control" value="<?= htmlspecialchars($subject) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Text e-mailu</label>
                        <textarea name="message" rows="7" class="form-control"><?= htmlspecialchars($message) ?></textarea>
                    </div>

                    <div class="alert alert-info">
                        K e-mailu bude automaticky přiložena faktura ve formátu PDF.
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="detail.php?id=<?= (int)$id ?>" class="btn btn-outline-secondary">Zrušit</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-envelope"></i> Odeslat fakturu
                        </button>
                    </div>
                </div>
            </form>

        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>