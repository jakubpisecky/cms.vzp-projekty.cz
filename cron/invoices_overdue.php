<?php
require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/settings_helpers.php";

require_once "../includes/php-mailer/src/Exception.php";
require_once "../includes/php-mailer/src/PHPMailer.php";
require_once "../includes/php-mailer/src/SMTP.php";

use PHPMailer\PHPMailer\PHPMailer;

// email kam to přijde (tvůj)
$notifyEmail = setting('invoice_supplier_email') ?: setting('contact_email');


// najdi faktury, které se právě staly po splatnosti
$sql = "
    SELECT i.*, c.name AS customer_name
    FROM invoices i
    LEFT JOIN invoice_customers c ON c.id = i.customer_id
    WHERE i.status = 'issued'
      AND i.due_date IS NOT NULL
      AND i.due_date < CURDATE()
";

$res = $conn->query($sql);

$count = 0;

while ($invoice = $res->fetch_assoc()) {

    try {
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

        $mail->setFrom(
            setting('smtp_from_email') ?: setting('contact_email'),
            setting('smtp_from_name') ?: 'Fakturace'
        );

        $mail->addAddress($notifyEmail);

        $invoiceNumber = $invoice['invoice_number'];
        $customer = $invoice['customer_name'] ?? '';
        $amount = number_format($invoice['total_with_vat'], 2, ',', ' ') . ' ' . $invoice['currency'];
        $dueDate = function_exists('formatDateCz') ? formatDateCz($invoice['due_date']) : $invoice['due_date'];

        $mail->Subject = "Faktura po splatnosti: {$invoiceNumber}";

        $mail->Body = "
            <p><strong>Faktura je po splatnosti</strong></p>

            <p>
                <strong>Číslo:</strong> {$invoiceNumber}<br>
                <strong>Odběratel:</strong> {$customer}<br>
                <strong>Částka:</strong> {$amount}<br>
                <strong>Splatnost:</strong> {$dueDate}
            </p>
        ";

        $mail->isHTML(true);
        $mail->send();

        // přepnout status až po odeslání
        $stmt = $conn->prepare("UPDATE invoices SET status = 'overdue' WHERE id = ?");
        $id = (int)$invoice['id'];
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        $count++;

    } catch (Throwable $e) {
        continue;
    }
}

echo "OK, odesláno upozornění: " . (int)$count;