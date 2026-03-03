<?php
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/functions.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/includes/php-mailer/src/Exception.php';
require __DIR__ . '/includes/php-mailer/src/PHPMailer.php';
require __DIR__ . '/includes/php-mailer/src/SMTP.php';

$msg = "";
$msgType = "info";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $msg = "Zadejte prosím svůj e-mail.";
        $msgType = "danger";
    } else {
        // Ověření, zda existuje uživatel
        $stmt = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($userId);
        $stmt->fetch();
        $stmt->close();

        if ($userId) {
            // Vygenerujeme reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Uložíme token do tabulky users (přidej si tam sloupce reset_token, reset_expires)
            $stmt = $conn->prepare("UPDATE users SET reset_token=?, reset_expires=? WHERE id=?");
            $stmt->bind_param("ssi", $token, $expires, $userId);
            $stmt->execute();

            // Odeslání e-mailu
            $mail = new PHPMailer(true);
            try {
                // Server
                $mail->isSMTP();
                $mail->Host       = 'email.active24.com'; // tvůj SMTP
                $mail->SMTPAuth   = true;
                $mail->Username   = 'info@vzp-projekty.cz'; 
                $mail->Password   = 'Ascoli1522';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                // Příjemce
                $mail->setFrom('info@vzp-projekty.cz', 'Administrace');
                $mail->addAddress($email);

                // Obsah e-mailu
                $resetLink = "https://".$_SERVER['HTTP_HOST']."/reset.php?token=$token";
                $mail->isHTML(true);
                $mail->Subject = 'Obnova hesla';
                $mail->Body    = "<p>Dobrý den,<br>klikněte na následující odkaz pro obnovu hesla:</p>
                                  <p><a href='$resetLink'>$resetLink</a></p>
                                  <p>Odkaz je platný 1 hodinu.</p>";

                $mail->send();
                $msg = "Na váš e-mail byl odeslán odkaz pro obnovu hesla.";
                $msgType = "success";
            } catch (Exception $e) {
                $msg = "Nepodařilo se odeslat e-mail: {$mail->ErrorInfo}";
                $msgType = "danger";
            }
        } else {
            $msg = "Tento e-mail nebyl nalezen.";
            $msgType = "warning";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Obnova hesla</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head><body class="bg-light d-flex align-items-center" style="height: 100vh;">
<div class="container">
    <div class="row justify-content-center w-100">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h2 class="mb-4 text-center"><i class="bi bi-envelope"></i> Obnova hesla</h2>

                    <?php if ($msg): ?>
                        <div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
                    <?php endif; ?>

                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Váš e-mail</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary"><i class="bi bi-send"></i> Odeslat odkaz</button>
                            <a href="index.php" class="btn btn-outline-secondary">Zpět na přihlášení</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
