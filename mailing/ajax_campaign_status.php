<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/mailing.php";

requirePermission('mailing');

$res = $conn->query("
    SELECT id, status
    FROM mailing_campaigns
");

$data = [];

while ($row = $res->fetch_assoc()) {
    $id = (int)$row['id'];
    $status = $row['status'];

    // HTML pro badge
    $badge = mailingStatusBadge($status);

    // Statistiky
    $stats = mailingCampaignStats($id);
    $sent = (int)($stats['sent'] ?? 0);
    $totalRecipients = (int)($stats['total_recipients'] ?? 0);

    $statsHtml = '<small class="text-muted">' . $sent . ' / ' . $totalRecipients . '</small>';

    // HTML pro akce
    ob_start();
    ?>

    <div class="d-flex flex-wrap gap-1 justify-content-end">

        <?php if ($status === 'draft'): ?>
            <a href="queue_campaign.php?id=<?php echo $id; ?>"
               class="btn btn-sm btn-outline-success"
               onclick="return confirm('Opravdu zařadit kampaň do fronty?');"
               title="Zařadit do fronty">
                <i class="bi bi-send"></i>
            </a>
        <?php endif; ?>

        <?php if (in_array($status, ['queued', 'sending'], true)): ?>
            <a href="stop.php?id=<?php echo $id; ?>"
               class="btn btn-sm btn-outline-warning"
               onclick="return confirm('Opravdu zastavit tuto kampaň? Zbývající e-maily zůstanou čekat ve frontě.');"
               title="Zastavit kampaň">
                <i class="bi bi-pause-fill"></i>
            </a>
        <?php endif; ?>

        <?php if ($status === 'stopped'): ?>
            <a href="play.php?id=<?php echo $id; ?>"
               class="btn btn-sm btn-outline-success"
               onclick="return confirm('Opravdu znovu spustit tuto kampaň?');"
               title="Pokračovat v odesílání">
                <i class="bi bi-play-fill"></i>
            </a>
        <?php endif; ?>

        <a href="send_test.php?id=<?php echo $id; ?>"
           class="btn btn-sm btn-outline-info"
           title="Testovací odeslání">
            <i class="bi bi-envelope-check"></i>
        </a>

        <a href="preview.php?id=<?php echo $id; ?>"
           class="btn btn-sm btn-outline-secondary"
           target="_blank"
           title="Náhled e-mailu">
            <i class="bi bi-eye"></i>
        </a>
         <a href="detail.php?id=<?php echo (int)$row['id']; ?>"
            class="btn btn-sm btn-outline-dark"
            title="Detail kampaně">
                <i class="bi bi-bar-chart"></i>
        </a>
        <a href="edit.php?id=<?php echo $id; ?>"
           class="btn btn-sm btn-outline-primary"
           title="Upravit">
            <i class="bi bi-pencil"></i>
        </a>

        <a href="delete.php?id=<?php echo $id; ?>"
           class="btn btn-sm btn-outline-danger"
           onclick="return confirm('Opravdu smazat tuto kampaň?');"
           title="Smazat">
            <i class="bi bi-trash"></i>
        </a>

    </div>

    <?php

    $actions = ob_get_clean();

    $data[$id] = [
        'status' => $status,
        'badge' => $badge,
        'actions' => $actions,
        'stats_html' => $statsHtml
    ];
}

header('Content-Type: application/json');
echo json_encode($data);