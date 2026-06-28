<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/mailing.php";

requirePermission('mailing');

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'invalid_id']);
    exit;
}

$stmt = $conn->prepare("
    SELECT *
    FROM mailing_campaigns
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$campaign = $result->fetch_assoc();
$stmt->close();

if (!$campaign) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'not_found']);
    exit;
}

$stats = mailingCampaignStats($id);

$total = (int)($stats['total_recipients'] ?? 0);
$pending = (int)($stats['pending'] ?? 0);
$processing = (int)($stats['processing'] ?? 0);
$sent = (int)($stats['sent'] ?? 0);
$failed = (int)($stats['failed'] ?? 0);
$skipped = (int)($stats['skipped'] ?? 0);

$percent = $total > 0 ? (int)round(($sent / $total) * 100) : 0;

$statusBadge = mailingStatusBadge($campaign['status']);

$progressHtml = '
<div class="progress mb-2" style="height: 10px;">
    <div class="progress-bar" role="progressbar" style="width: ' . $percent . '%;"></div>
</div>
<small class="text-muted">' . $sent . ' / ' . $total . ' odesláno</small>';

ob_start();
?>
<div class="col-md-4 col-xl-2">
    <div class="card shadow-sm border-0 text-center">
        <div class="card-body">
            <div class="text-muted small">Celkem</div>
            <div class="fs-4 fw-bold"><?php echo $total; ?></div>
        </div>
    </div>
</div>
<div class="col-md-4 col-xl-2">
    <div class="card shadow-sm border-0 text-center">
        <div class="card-body">
            <div class="text-muted small">Čeká</div>
            <div class="fs-4 fw-bold"><?php echo $pending; ?></div>
        </div>
    </div>
</div>
<div class="col-md-4 col-xl-2">
    <div class="card shadow-sm border-0 text-center">
        <div class="card-body">
            <div class="text-muted small">Zpracování</div>
            <div class="fs-4 fw-bold"><?php echo $processing; ?></div>
        </div>
    </div>
</div>
<div class="col-md-4 col-xl-2">
    <div class="card shadow-sm border-0 text-center">
        <div class="card-body">
            <div class="text-muted small">Odesláno</div>
            <div class="fs-4 fw-bold text-success"><?php echo $sent; ?></div>
        </div>
    </div>
</div>
<div class="col-md-4 col-xl-2">
    <div class="card shadow-sm border-0 text-center">
        <div class="card-body">
            <div class="text-muted small">Chyba</div>
            <div class="fs-4 fw-bold text-danger"><?php echo $failed; ?></div>
        </div>
    </div>
</div>
<div class="col-md-4 col-xl-2">
    <div class="card shadow-sm border-0 text-center">
        <div class="card-body">
            <div class="text-muted small">Přeskočeno</div>
            <div class="fs-4 fw-bold"><?php echo $skipped; ?></div>
        </div>
    </div>
</div>
<?php
$cardsHtml = ob_get_clean();

// logy
$stmt = $conn->prepare("
    SELECT *
    FROM mailing_logs
    WHERE campaign_id = ?
    ORDER BY id DESC
    LIMIT 10
");
$stmt->bind_param("i", $id);
$stmt->execute();
$logs = $stmt->get_result();
$stmt->close();

ob_start();
if ($logs->num_rows > 0):
    while ($row = $logs->fetch_assoc()):
?>
<tr>
    <td>
        <?php if ($row['status'] === 'success'): ?>
            <span class="badge bg-success">OK</span>
        <?php elseif ($row['status'] === 'error'): ?>
            <span class="badge bg-danger">Chyba</span>
        <?php else: ?>
            <span class="badge bg-secondary">Info</span>
        <?php endif; ?>
    </td>
    <td><?php echo htmlspecialchars($row['email'] ?? ''); ?></td>
    <td><?php echo htmlspecialchars(mb_strimwidth($row['message'], 0, 60, '...')); ?></td>
</tr>
<?php
    endwhile;
else:
?>
<tr>
    <td colspan="3" class="text-muted">Zatím bez logů.</td>
</tr>
<?php
endif;
$logsHtml = ob_get_clean();

// queue
$stmt = $conn->prepare("
    SELECT *
    FROM mailing_queue
    WHERE campaign_id = ?
    ORDER BY id DESC
    LIMIT 10
");
$stmt->bind_param("i", $id);
$stmt->execute();
$queue = $stmt->get_result();
$stmt->close();

ob_start();
if ($queue->num_rows > 0):
    while ($row = $queue->fetch_assoc()):
?>
<tr>
    <td><?php echo htmlspecialchars($row['email']); ?></td>
    <td><?php echo mailingQueueStatusBadge($row['status']); ?></td>
    <td><?php echo (int)$row['attempts']; ?></td>
</tr>
<?php
    endwhile;
else:
?>
<tr>
    <td colspan="3" class="text-muted">Fronta je prázdná.</td>
</tr>
<?php
endif;
$queueHtml = ob_get_clean();

header('Content-Type: application/json');
echo json_encode([
    'status_badge' => $statusBadge,
    'progress_html' => $progressHtml,
    'cards_html' => $cardsHtml,
    'logs_html' => $logsHtml,
    'queue_html' => $queueHtml
]);