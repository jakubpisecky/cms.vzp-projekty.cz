<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/settings_helpers.php';
require_once __DIR__ . '/../includes/mailing.php';

// kolik mailů za běh
$limit = (int) setting('mailing_batch_size');
if ($limit <= 0) {
    $limit = 10;
}

// max pokusů
$maxAttempts = (int) setting('mailing_max_attempts');
if ($maxAttempts <= 0) {
    $maxAttempts = 3;
}

// budeme si pamatovat, kterých kampaní se tento běh dotkl
$affectedCampaignIds = [];

// --- vezmi dávku ---
$res = $conn->query("
    SELECT mq.*
    FROM mailing_queue mq
    INNER JOIN mailing_campaigns mc ON mc.id = mq.campaign_id
    WHERE mq.status = 'pending'
      AND mc.status <> 'stopped'
    ORDER BY mq.id ASC
    LIMIT $limit
");

if (!$res || $res->num_rows === 0) {
    exit; // nic k odeslání
}

while ($row = $res->fetch_assoc()) {

    $id = (int) $row['id'];
    $campaignId = (int) $row['campaign_id'];

    $affectedCampaignIds[$campaignId] = $campaignId;

    // zamkni záznam
    $conn->query("
        UPDATE mailing_queue
        SET status = 'processing', locked_at = NOW()
        WHERE id = $id
    ");

    $result = sendMailingMessage([
        'to_email' => $row['email'],
        'to_name'  => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
        'subject'  => $row['subject'],
        'html'     => $row['html_content']
    ]);

    if ($result['success']) {

        $conn->query("
            UPDATE mailing_queue
            SET status = 'sent',
                sent_at = NOW()
            WHERE id = $id
        ");

        mailingLog(
            $campaignId,
            $row['recipient_id'],
            $id,
            $row['email'],
            'success',
            'E-mail odeslán'
        );

    } else {

        $attempts = (int) $row['attempts'] + 1;
        $error = $conn->real_escape_string($result['error']);

        if ($attempts >= $maxAttempts) {
            $status = 'failed';
        } else {
            $status = 'pending';
        }

        $conn->query("
            UPDATE mailing_queue
            SET status = '$status',
                attempts = $attempts,
                last_error = '$error'
            WHERE id = $id
        ");

        mailingLog(
            $campaignId,
            $row['recipient_id'],
            $id,
            $row['email'],
            'error',
            $result['error']
        );
    }

    // po zpracování každé položky přepočítáme stav kampaně
    updateMailingCampaignStatus($campaignId);
}

// pro jistotu ještě jednou na konci přepočítáme všechny dotčené kampaně
foreach ($affectedCampaignIds as $campaignId) {
    updateMailingCampaignStatus((int) $campaignId);
}