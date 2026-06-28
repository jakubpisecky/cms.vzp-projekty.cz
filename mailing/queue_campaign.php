<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/mailing.php";

requirePermission('mailing');

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die("Neplatné ID kampaně.");
}

// --- načti kampaň ---
$stmt = $conn->prepare("SELECT * FROM mailing_campaigns WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$campaign = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$campaign) {
    die("Kampaň nenalezena.");
}

// --- vezmi příjemce ---
$res = $conn->query("
    SELECT *
    FROM mailing_recipients
    WHERE active = 1 AND unsubscribed = 0
");

if (!$res) {
    die("Chyba při načítání příjemců.");
}

// --- připrav insert ---
$stmt = $conn->prepare("
    INSERT INTO mailing_queue
    (campaign_id, recipient_id, email, first_name, last_name, company, subject, html_content)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

$count = 0;

while ($r = $res->fetch_assoc()) {

    // nahradíme proměnné ve zprávě
    $content = mailingReplaceVariables($campaign['content'], $r);

    $stmt->bind_param(
        "iissssss",
        $campaign['id'],
        $r['id'],
        $r['email'],
        $r['first_name'],
        $r['last_name'],
        $r['company'],
        $campaign['subject'],
        $content
    );

    $stmt->execute();
    $count++;
}

$stmt->close();

// --- update kampaně ---
$stmt = $conn->prepare("
    UPDATE mailing_campaigns
    SET status = 'queued', queued_at = NOW()
    WHERE id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

// --- log ---
if (function_exists('logAction')) {
    logAction("Kampaň ID {$id} zařazena do fronty ({$count} příjemců)");
}

header("Location: list.php?queued=1");
exit;