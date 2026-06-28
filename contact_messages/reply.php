<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/settings_helpers.php";

require_once "../includes/php-mailer/src/PHPMailer.php";
require_once "../includes/php-mailer/src/SMTP.php";
require_once "../includes/php-mailer/src/Exception.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

requirePermission('contact_messages');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

$csrf = $_SESSION['csrf'];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: index.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT id, created_at, name, email, phone, subject, message, page_slug, replied_at, replied_by
    FROM contact_messages
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$msg = $res->fetch_assoc();
$stmt->close();

if (!$msg) {
    $_SESSION['flash_contact_msg'] = [
        'type' => 'danger',
        'text' => 'Zpráva nebyla nalezena.'
    ];

    header("Location: index.php");
    exit;
}

$to = $msg['email'] ?? '';

if ($to === '') {
    $_SESSION['flash_contact_msg'] = [
        'type' => 'danger',
        'text' => 'U zprávy není uveden e-mail.'
    ];

    header("Location: view.php?id=" . $id);
    exit;
}

$subjectDefault = 'Re: ' . ($msg['subject'] ?: 'Vaše zpráva');

$subject = $_POST['subject'] ?? $subjectDefault;
$message = $_POST['message'] ?? '';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        $error = 'Neplatný CSRF token.';
    } elseif (trim($subject) === '') {
        $error = 'Vyplňte předmět.';
    } elseif (trim($message) === '') {
        $error = 'Vyplňte zprávu.';
    } else {

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

            $fromEmail = setting('smtp_from_email') ?: setting('contact_email');
            $fromName  = setting('smtp_from_name') ?: setting('site_title');

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to, $msg['name'] ?? '');
            $mail->addReplyTo($fromEmail, $fromName);

            $original = "\n\n\n----------------------------------------\n";
            $original .= "Původní zpráva:\n";
            $original .= "Jméno: " . ($msg['name'] ?? '-') . "\n";
            $original .= "E-mail: " . ($msg['email'] ?? '-') . "\n";
            $original .= "Datum: " . date('j.n.Y H:i', strtotime($msg['created_at'])) . "\n\n";
            $original .= $msg['message'];

            $plainBody = trim($message) . $original;

            $mail->Subject = $subject;
            $mail->Body = nl2br(htmlspecialchars($plainBody));
            $mail->AltBody = $plainBody;
            $mail->isHTML(true);

            $mail->send();

            $adminId = (int)($_SESSION['admin_id'] ?? 0);

            $stmt = $conn->prepare("
                UPDATE contact_messages
                SET replied_at = NOW(), replied_by = ?
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->bind_param("ii", $adminId, $id);
            $stmt->execute();
            $stmt->close();

            $_SESSION['flash_contact_msg'] = [
                'type' => 'success',
                'text' => 'Odpověď byla odeslána.'
            ];

            header("Location: view.php?id=" . $id);
            exit;

        } catch (Exception $e) {
            $error = 'E-mail se nepodařilo odeslat: ' . $mail->ErrorInfo;
        }
    }
}

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Odpověď na zprávu</h2>
            <p class="text-muted mb-0">
                <?= e($msg['name']) ?> &lt;<?= e($msg['email']) ?>&gt;
            </p>
        </div>

        <a href="view.php?id=<?= (int)$id ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Zpět
        </a>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <div class="col-lg-8">

            <div class="card shadow-sm border-0">
                <div class="card-body">

                    <form method="post">

                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

                        <div class="mb-3">
                            <label class="form-label">Komu</label>
                            <input type="text" class="form-control" value="<?= e($to) ?>" disabled>
                        </div>

                        <div class="mb-3">
                            <label for="subject" class="form-label">Předmět</label>
                            <input type="text"
                                   name="subject"
                                   id="subject"
                                   class="form-control"
                                   value="<?= e($subject) ?>"
                                   required>
                        </div>

                        <div class="mb-3">
                            <label for="message" class="form-label">Zpráva</label>
                            <textarea name="message"
                                      id="message"
                                      rows="10"
                                      class="form-control"
                                      required><?= e($message) ?></textarea>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send me-1"></i> Odeslat odpověď
                            </button>

                            <a href="view.php?id=<?= (int)$id ?>" class="btn btn-outline-secondary">
                                Zrušit
                            </a>
                        </div>

                    </form>

                </div>
            </div>

        </div>

        <div class="col-lg-4">

            <div class="card shadow-sm border-0">
                <div class="card-body">

                    <h5 class="mb-3">Původní zpráva</h5>

                    <table class="table table-bordered align-middle bg-white mb-3">
                        <tbody>
                            <tr>
                                <th class="table-light" width="120">Datum</th>
                                <td><?= e(date('j.n.Y H:i', strtotime($msg['created_at']))) ?></td>
                            </tr>
                            <tr>
                                <th class="table-light">Předmět</th>
                                <td><?= e($msg['subject'] ?: '—') ?></td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="border rounded bg-light p-3 small" style="white-space: pre-line;">
                        <?= e($msg['message']) ?>
                    </div>

                </div>
            </div>

        </div>

    </div>

</div>

<?php include "../includes/footer.php"; ?>