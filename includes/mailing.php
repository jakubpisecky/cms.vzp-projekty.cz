<?php

require_once __DIR__ . '/php-mailer/src/Exception.php';
require_once __DIR__ . '/php-mailer/src/PHPMailer.php';
require_once __DIR__ . '/php-mailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function mailingStatusBadge(string $status): string
{
    switch ($status) {
        case 'draft':
            return '<span class="badge bg-secondary">Koncept</span>';
        case 'queued':
            return '<span class="badge bg-info text-dark">Ve frontě</span>';
        case 'sending':
            return '<span class="badge bg-primary">Odesílá se</span>';
        case 'finished':
            return '<span class="badge bg-success">Hotovo</span>';
        case 'finished_with_errors':
            return '<span class="badge bg-warning text-dark">Hotovo s chybami</span>';
        case 'stopped':
            return '<span class="badge bg-danger">Zastaveno</span>';
        default:
            return '<span class="badge bg-light text-dark">' . htmlspecialchars($status) . '</span>';
    }
}

function mailingQueueStatusBadge(string $status): string
{
    switch ($status) {
        case 'pending':
            return '<span class="badge bg-secondary">Čeká</span>';
        case 'processing':
            return '<span class="badge bg-primary">Zpracování</span>';
        case 'sent':
            return '<span class="badge bg-success">Odesláno</span>';
        case 'failed':
            return '<span class="badge bg-danger">Chyba</span>';
        case 'skipped':
            return '<span class="badge bg-warning text-dark">Přeskočeno</span>';
        default:
            return '<span class="badge bg-light text-dark">' . htmlspecialchars($status) . '</span>';
    }
}

function mailingLog($campaignId = null, $recipientId = null, $queueId = null, $email = null, string $status = 'info', string $message = ''): void
{
    global $conn;

    $stmt = $conn->prepare("
        INSERT INTO mailing_logs (campaign_id, recipient_id, queue_id, email, status, message)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        return;
    }

    $stmt->bind_param("iiisss", $campaignId, $recipientId, $queueId, $email, $status, $message);
    $stmt->execute();
    $stmt->close();
}

function mailingReplaceVariables(string $content, array $recipient): string
{
    $firstName = trim((string)($recipient['first_name'] ?? ''));
    $lastName  = trim((string)($recipient['last_name'] ?? ''));
    $company   = trim((string)($recipient['company'] ?? ''));
    $email     = trim((string)($recipient['email'] ?? ''));
    $token     = trim((string)($recipient['unsubscribe_token'] ?? ''));

    $unsubscribeUrl = buildUnsubscribeUrl($token);
    $unsubscribeLink = $unsubscribeUrl !== ''
        ? '<a href="' . htmlspecialchars($unsubscribeUrl, ENT_QUOTES, 'UTF-8') . '">Odhlásit odběr</a>'
        : '';

    $replacements = [
        '{email}'            => $email,
        '{first_name}'       => $firstName,
        '{last_name}'        => $lastName,
        '{company}'          => $company,
        '{name}'             => trim($firstName . ' ' . $lastName),
        '{unsubscribe_url}'  => $unsubscribeUrl,
        '{unsubscribe_link}' => $unsubscribeLink,
    ];

    return strtr($content, $replacements);
}

function buildMailingMailer(): PHPMailer
{
    if (!function_exists('setting')) {
        throw new Exception('Funkce setting() není načtena.');
    }

    $host     = trim((string) setting('mailing_smtp_host'));
    $port     = (int) setting('mailing_smtp_port');
    $username = trim((string) setting('mailing_smtp_username'));
    $password = (string) setting('mailing_smtp_password');
    $secure   = trim((string) setting('mailing_smtp_secure'));

    if ($host === '') {
        throw new Exception('Není nastaven mailing SMTP host.');
    }

    if ($port <= 0) {
        throw new Exception('Není nastaven mailing SMTP port.');
    }

    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = $host;
    $mail->Port = $port;
    $mail->SMTPAuth = true;
    $mail->Username = $username;
    $mail->Password = $password;

    if ($secure === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($secure === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }

    $fromEmail = trim((string) setting('mailing_from_email'));
    $fromName  = trim((string) setting('mailing_from_name'));
    $replyTo   = trim((string) setting('mailing_reply_to'));

    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);

    if ($fromEmail !== '') {
        $mail->setFrom($fromEmail, $fromName !== '' ? $fromName : $fromEmail);
    }

    if ($replyTo !== '') {
        $mail->addReplyTo($replyTo);
    }

    return $mail;
}

function sendMailingMessage(array $mailData): array
{
    try {
        $mail = buildMailingMailer();

        $toEmail = trim((string)($mailData['to_email'] ?? ''));
        $toName  = trim((string)($mailData['to_name'] ?? ''));
        $subject = trim((string)($mailData['subject'] ?? ''));
        $html    = (string)($mailData['html'] ?? '');

        if ($toEmail === '' || $subject === '' || $html === '') {
            throw new Exception('Chybí e-mail, předmět nebo obsah zprávy.');
        }

        $mail->addAddress($toEmail, $toName);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)));

        $mail->send();

        return [
            'success' => true,
            'error'   => null,
        ];
    } catch (\Throwable $e) {
        return [
            'success' => false,
            'error'   => $e->getMessage(),
        ];
    }
}

function updateMailingCampaignStatus(int $campaignId): void
{
    global $conn;

    $stats = [
        'pending' => 0,
        'processing' => 0,
        'sent' => 0,
        'failed' => 0,
        'skipped' => 0,
    ];

    $stmt = $conn->prepare("
        SELECT status, COUNT(*) AS cnt
        FROM mailing_queue
        WHERE campaign_id = ?
        GROUP BY status
    ");
    if (!$stmt) {
        return;
    }

    $stmt->bind_param("i", $campaignId);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        if (isset($stats[$row['status']])) {
            $stats[$row['status']] = (int)$row['cnt'];
        }
    }
    $stmt->close();

    $newStatus = 'queued';

    if ($stats['processing'] > 0) {
        $newStatus = 'sending';
    } elseif ($stats['pending'] > 0) {
        $newStatus = 'queued';
    } else {
        if ($stats['failed'] > 0) {
            $newStatus = 'finished_with_errors';
        } else {
            $newStatus = 'finished';
        }
    }

    $stmt = $conn->prepare("
        UPDATE mailing_campaigns
        SET status = ?,
            finished_at = CASE
                WHEN ? IN ('finished', 'finished_with_errors') THEN NOW()
                ELSE finished_at
            END
        WHERE id = ?
    ");
    if (!$stmt) {
        return;
    }

    $stmt->bind_param("ssi", $newStatus, $newStatus, $campaignId);
    $stmt->execute();
    $stmt->close();
}
function generateUnsubscribeToken(): string
{
    return bin2hex(random_bytes(32));
}

function buildUnsubscribeUrl(string $token): string
{
    $baseUrl = '';

    if (function_exists('setting')) {
        $baseUrl = rtrim((string) setting('site_url'), '/');
    }

    if ($baseUrl === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? '';
        if ($host !== '') {
            $baseUrl = $scheme . '://' . $host;
        }
    }

    if ($baseUrl === '' || $token === '') {
        return '';
    }
    return $baseUrl . '/unsubscribe.php?token=' . urlencode($token);
}function mailingCampaignStats(int $campaignId): array
{
    global $conn;

    $stats = [
        'total_recipients' => 0,
        'pending' => 0,
        'processing' => 0,
        'sent' => 0,
        'failed' => 0,
        'skipped' => 0,
    ];

    $stmt = $conn->prepare("
        SELECT status, COUNT(*) AS cnt
        FROM mailing_queue
        WHERE campaign_id = ?
        GROUP BY status
    ");
    if (!$stmt) {
        return $stats;
    }

    $stmt->bind_param("i", $campaignId);
    $stmt->execute();
    $res = $stmt->get_result();

    $total = 0;

    while ($row = $res->fetch_assoc()) {
        $status = $row['status'];
        $cnt = (int)$row['cnt'];

        if (isset($stats[$status])) {
            $stats[$status] = $cnt;
        }

        $total += $cnt;
    }

    $stmt->close();

    $stats['total_recipients'] = $total;

    return $stats;
}