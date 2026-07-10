<?php

require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/settings_helpers.php";

if (function_exists('settings_load_all')) {
    settings_load_all($conn);
}

$msg = "";
$msgType = "info";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $msg = "Zadejte prosím svůj e-mail.";
        $msgType = "danger";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "Zadejte platnou e-mailovou adresu.";
        $msgType = "danger";

    } else {

        $userId = 0;

        $stmt = $conn->prepare("
            SELECT id
            FROM users
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        $stmt->close();

        if ($user) {
            $userId = (int)$user['id'];
        }

        /*
         * E-mail posíláme pouze tehdy, pokud účet existuje.
         * Uživateli ale vždy zobrazíme stejnou odpověď,
         * aby nebylo možné zjišťovat registrované adresy.
         */
        if ($userId > 0) {

            try {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $stmt = $conn->prepare("
                    UPDATE users
                    SET reset_token = ?,
                        reset_expires = ?
                    WHERE id = ?
                    LIMIT 1
                ");
                $stmt->bind_param(
                    "ssi",
                    $token,
                    $expires,
                    $userId
                );
                $stmt->execute();
                $stmt->close();

                $host = $_SERVER['HTTP_HOST'] ?? '';

                /*
                 * Ochrana proti podvržení HTTP_HOST.
                 * Povolíme pouze běžné znaky domény a případný port.
                 */
                if (!preg_match('/^[a-z0-9.-]+(?::[0-9]+)?$/i', $host)) {
                    throw new RuntimeException('Neplatný název serveru.');
                }

                $isHttps =
                    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443
                    || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

                $scheme = $isHttps ? 'https' : 'http';

                $resetLink = $scheme
                    . '://'
                    . $host
                    . '/reset.php?token='
                    . rawurlencode($token);

                if (!function_exists('mailer_from_settings')) {
                    throw new RuntimeException(
                        'Funkce mailer_from_settings() není dostupná.'
                    );
                }

                $mail = mailer_from_settings();

                if (!$mail) {
                    throw new RuntimeException(
                        'Nepodařilo se vytvořit e-mailového klienta.'
                    );
                }

                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'Obnova hesla';

                $safeResetLink = htmlspecialchars(
                    $resetLink,
                    ENT_QUOTES,
                    'UTF-8'
                );

                $mail->Body = '
                    <p>Dobrý den,</p>

                    <p>
                        obdrželi jsme žádost o obnovení hesla
                        k vašemu účtu v administraci.
                    </p>

                    <p>
                        <a href="' . $safeResetLink . '"
                           style="
                               display:inline-block;
                               padding:12px 20px;
                               background:#0d6efd;
                               color:#ffffff;
                               text-decoration:none;
                               border-radius:5px;
                           ">
                            Nastavit nové heslo
                        </a>
                    </p>

                    <p>
                        Případně můžete použít tento odkaz:
                    </p>

                    <p>
                        <a href="' . $safeResetLink . '">
                            ' . $safeResetLink . '
                        </a>
                    </p>

                    <p>
                        Odkaz je platný jednu hodinu.
                    </p>

                    <p>
                        Pokud jste o změnu hesla nežádali,
                        tento e-mail ignorujte.
                    </p>
                ';

                $mail->AltBody =
                    "Dobrý den,\n\n"
                    . "pro obnovení hesla otevřete následující odkaz:\n"
                    . $resetLink
                    . "\n\nOdkaz je platný jednu hodinu.\n\n"
                    . "Pokud jste o změnu hesla nežádali, "
                    . "tento e-mail ignorujte.";

                $mail->send();

                if (function_exists('logAction')) {
                    logAction(
                        "Odeslána žádost o obnovu hesla pro uživatele ID "
                        . $userId
                    );
                }

            } catch (Throwable $e) {

                /*
                 * Token zneplatníme, pokud se odeslání nepovedlo.
                 */
                if ($userId > 0) {
                    $stmt = $conn->prepare("
                        UPDATE users
                        SET reset_token = NULL,
                            reset_expires = NULL
                        WHERE id = ?
                        LIMIT 1
                    ");
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $stmt->close();
                }

                error_log(
                    'Forgot password error: ' . $e->getMessage()
                );

                $msg = "E-mail se nyní nepodařilo odeslat. "
                     . "Zkuste to prosím později.";
                $msgType = "danger";
            }
        }

        if ($msg === '') {
            $msg = "Pokud účet s touto e-mailovou adresou existuje, "
                 . "odeslali jsme na něj odkaz pro obnovu hesla.";
            $msgType = "success";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="cs">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Obnova hesla</title>

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body class="bg-light">

<div class="container min-vh-100 d-flex align-items-center py-4">

    <div class="row justify-content-center w-100">

        <div class="col-md-7 col-lg-5">

            <div class="card shadow-sm border-0">

                <div class="card-body p-4 p-md-5">

                    <h2 class="mb-4 text-center">
                        <i class="bi bi-envelope me-1"></i>
                        Obnova hesla
                    </h2>

                    <?php if ($msg): ?>
                        <div class="alert alert-<?= e($msgType) ?>">
                            <?= e($msg) ?>
                        </div>
                    <?php endif; ?>

                    <form method="post">

                        <div class="mb-3">

                            <label for="email" class="form-label">
                                Váš e-mail
                            </label>

                            <input type="email"
                                   name="email"
                                   id="email"
                                   class="form-control"
                                   autocomplete="email"
                                   required
                                   value="<?= e($_POST['email'] ?? '') ?>">

                        </div>

                        <div class="d-grid gap-2">

                            <button type="submit"
                                    class="btn btn-primary">

                                <i class="bi bi-send me-1"></i>
                                Odeslat odkaz

                            </button>

                            <a href="index.php"
                               class="btn btn-outline-secondary">

                                Zpět na přihlášení

                            </a>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

</body>
</html>