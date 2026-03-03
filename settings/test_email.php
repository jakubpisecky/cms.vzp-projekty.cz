<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/settings_helpers.php";
requirePermission('settings');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once(__DIR__."/../includes/php-mailer/src/Exception.php");
require_once(__DIR__."/../includes/php-mailer/src/PHPMailer.php");
require_once(__DIR__."/../includes/php-mailer/src/SMTP.php");

// === Načtení hodnot ze SETTINGS ===
$to             = setting('contact_email')       ?: 'postmaster@localhost';
$fromEmail      = setting('smtp_from_email')     ?: (setting('smtp_username') ?: 'no-reply@example.com');
$fromName       = setting('smtp_from_name')      ?: 'Administrace';
$smtpEnabled    = (bool) setting('smtp_enabled');
$host           = setting('smtp_host')           ?: 'localhost';
$port           = (int) (setting('smtp_port')    ?: 25);
$secure         = strtolower(trim(setting('smtp_secure') ?: 'none')); // none | tls | ssl | starttls
$username       = setting('smtp_username')       ?: '';
$password       = setting('smtp_password')       ?: '';
$authType       = strtolower(trim(setting('smtp_auth_type') ?: ''));   // '', login, plain, cram-md5
$timeout        = (int) (setting('smtp_timeout') ?: 15);
$allowSelfSigned= (bool) setting('smtp_allow_self_signed');            // 1/0
$forceFrom      = (bool) setting('smtp_force_from');                   // když potřebuješ From==SMTP účet
$replyTo        = setting('smtp_reply_to')        ?: '';
$keepAlive      = (bool) setting('smtp_keep_alive');                   // většinou false

// DKIM (pokud máš v DB)
$dkimDomain     = setting('dkim_domain')         ?: '';
$dkimSelector   = setting('dkim_selector')       ?: '';
$dkimPrivateKey = setting('dkim_private_key')    ?: ''; // cesta nebo samotný klíč
$dkimIdentity   = setting('dkim_identity')       ?: ''; // obvykle stejné jako From

// Debug: ?debug=1 → SMTP log
$debug = isset($_GET['debug']) && $_GET['debug'] == '1';

$mail = new PHPMailer(true);
try {
    // Základní nastavení
    $mail->CharSet  = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->isHTML(false);

    if ($smtpEnabled) {
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->Port       = $port;
        $mail->Timeout    = max(5, $timeout);
        $mail->SMTPKeepAlive = $keepAlive;
        $mail->SMTPDebug  = $debug ? 2 : 0;              // 2 = client+server; zapínat jen při ladění
        $mail->Debugoutput = 'html';

        // Řízení šifrování
        // - 'ssl' = SMTPS na 465
        // - 'tls' nebo 'starttls' = STARTTLS na 587/25
        // - 'none' = bez TLS (explicitně vypneme autoTLS)
        if ($secure === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($secure === 'tls' || $secure === 'starttls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPAutoTLS = false; // jinak by se PHPMailer pokusil o STARTTLS automaticky
        }

        // AUTH
        $mail->SMTPAuth = ($username !== '' || $password !== '');
        if ($mail->SMTPAuth) {
            $mail->Username = $username;
            $mail->Password = $password;
        }
        // Volitelný typ autentizace
        // '', 'login', 'plain', 'cram-md5'
        if (in_array($authType, ['login','plain','cram-md5'], true)) {
            $mail->AuthType = $authType;
        }

        // Povolit self-signed cert (pokud je to v labu / testu potřeba)
        if ($allowSelfSigned) {
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ];
        }
    }

    // FROM (případně vynutit shodu s účtem)
    if ($forceFrom && $smtpEnabled && filter_var($username, FILTER_VALIDATE_EMAIL)) {
        $fromEmail = $username;
    }
    $mail->setFrom($fromEmail, $fromName);

    // Reply-To (volitelné)
    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $mail->addReplyTo($replyTo, $fromName);
    }

    // Příjemce testu
    $mail->addAddress($to);

    // DKIM (pokud je kompletní konfigurace)
    if ($dkimDomain && $dkimSelector && $dkimPrivateKey) {
        $mail->DKIM_domain   = $dkimDomain;
        $mail->DKIM_selector = $dkimSelector;
        $mail->DKIM_identity = $dkimIdentity ?: $fromEmail;
        // dodej buď cestu ke klíči, nebo samotný klíč
        if (strpos($dkimPrivateKey, '-----BEGIN') === 0) {
            $mail->DKIM_private_string = $dkimPrivateKey;
        } else {
            $mail->DKIM_private = $dkimPrivateKey; // cesta k souboru
        }
    }

    // Zpráva
    $mail->Subject = 'Test e-mail z administrace';
    $mail->Body    = "Ahoj,\n\nToto je testovací zpráva z administrace nastavení.\n\n— CMS";

    // Odeslání
    $mail->send();

    // přehledné OK + shrnutí použitých parametrů (bez citlivých údajů)
    echo "OK – zpráva odeslána na: " . e($to) . "<br>";
    echo "<small>Režim: " . ($smtpEnabled ? 'SMTP' : 'PHP mail()') .
         ($smtpEnabled ? (" | Host: " . e($host) . ":" . e((string)$port) .
         " | Secure: " . e($secure ?: 'none') .
         " | Auth: " . ($username !== '' ? 'ano' : 'ne')) : '') .
         "</small>";

} catch (Exception $e) {
    // Přehledná chyba
    $why = $mail->ErrorInfo ?: $e->getMessage();
    echo "Chyba při odesílání: " . e($why);

    // Tipy při ladění
    if (!$debug) {
        echo "<br><small>Tip: přidej do URL <code>?debug=1</code> pro detailní SMTP log (nezapínej na produkci dlouhodobě).</small>";
    }
}
