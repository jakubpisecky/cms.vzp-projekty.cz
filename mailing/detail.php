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
    die("Kampaň nebyla nalezena.");
}

$stats = mailingCampaignStats($id);

$total = (int)($stats['total_recipients'] ?? 0);
$pending = (int)($stats['pending'] ?? 0);
$processing = (int)($stats['processing'] ?? 0);
$sent = (int)($stats['sent'] ?? 0);
$failed = (int)($stats['failed'] ?? 0);
$skipped = (int)($stats['skipped'] ?? 0);

$percent = $total > 0 ? (int)round(($sent / $total) * 100) : 0;

// poslední logy
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

// poslední fronta
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

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="admin-page-header-content mb-4">
        <div>
            <h2 class="mb-1">Detail kampaně</h1>
            <p class="text-muted mb-0"><?php echo htmlspecialchars($campaign['title']); ?></p>
        </div>
        <div class="responsive-actions">
            <a href="list.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Zpět
            </a>

            <a href="preview.php?id=<?php echo (int)$campaign['id']; ?>"
               class="btn btn-outline-secondary"
               target="_blank">
                <i class="bi bi-eye me-1"></i> Náhled
            </a>

            <a href="send_test.php?id=<?php echo (int)$campaign['id']; ?>"
               class="btn btn-outline-info">
                <i class="bi bi-envelope-check me-1"></i> Test
            </a>

            <a href="edit.php?id=<?php echo (int)$campaign['id']; ?>"
               class="btn btn-outline-primary">
                <i class="bi bi-pencil me-1"></i> Upravit
            </a>

            <?php if (($campaign['status'] ?? '') === 'draft'): ?>
                <a href="queue_campaign.php?id=<?php echo (int)$campaign['id']; ?>"
                   class="btn btn-success"
                   onclick="return confirm('Opravdu zařadit kampaň do fronty?');">
                    <i class="bi bi-send me-1"></i> Zařadit do fronty
                </a>
            <?php endif; ?>

            <?php if (in_array(($campaign['status'] ?? ''), ['queued', 'sending'], true)): ?>
                <a href="stop.php?id=<?php echo (int)$campaign['id']; ?>"
                   class="btn btn-warning"
                   onclick="return confirm('Opravdu zastavit tuto kampaň?');">
                    <i class="bi bi-pause-fill me-1"></i> Stop
                </a>
            <?php endif; ?>

            <?php if (($campaign['status'] ?? '') === 'stopped'): ?>
                <a href="play.php?id=<?php echo (int)$campaign['id']; ?>"
                   class="btn btn-success"
                   onclick="return confirm('Opravdu znovu spustit tuto kampaň?');">
                    <i class="bi bi-play-fill me-1"></i> Play
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php include "_menu.php"; ?>

    <div class="row g-4">

        <div class="col-xl-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-3">
                        <div>
                            <h2 class="h5 mb-2">Přehled</h2>
                            <div><strong>Předmět:</strong> <?php echo htmlspecialchars($campaign['subject']); ?></div>
                            <div><strong>Stav:</strong> <span id="detail-status"><?php echo mailingStatusBadge($campaign['status']); ?></span></div>
                            <div><strong>Vytvořeno:</strong> <?php echo formatDateTimeCz(htmlspecialchars($campaign['created_at'])); ?></div>
                        </div>
                    </div>

                    <div id="detail-progress-wrapper">
                        <div class="progress mb-2" style="height: 10px;">
                            <div class="progress-bar" role="progressbar" style="width: <?php echo $percent; ?>%;"></div>
                        </div>
                        <small class="text-muted"><?php echo $sent; ?> / <?php echo $total; ?> odesláno</small>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4" id="detail-stats-cards">
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
            </div>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white">
                    <h2 class="h6 mb-0">Obsah e-mailu</h2>
                </div>
                <div class="card-body">
                    <div class="border rounded p-3 bg-light-subtle" style="max-height: 600px; overflow:auto;">
                        <?php echo $campaign['content']; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white">
                    <h2 class="h6 mb-0">Poslední logy</h2>
                </div>
                <div class="card-body p-0">
                    <?php if ($logs->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Status</th>
                                        <th>Email</th>
                                        <th>Zpráva</th>
                                    </tr>
                                </thead>
                                <tbody id="detail-logs-body">
                                    <?php while ($row = $logs->fetch_assoc()): ?>
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
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-3 text-muted">Zatím bez logů.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <h2 class="h6 mb-0">Poslední položky fronty</h2>
                </div>
                <div class="card-body p-0">
                    <?php if ($queue->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Email</th>
                                        <th>Stav</th>
                                        <th>Pokusy</th>
                                    </tr>
                                </thead>
                                <tbody id="detail-queue-body">
                                    <?php while ($row = $queue->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                                            <td><?php echo mailingQueueStatusBadge($row['status']); ?></td>
                                            <td><?php echo (int)$row['attempts']; ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-3 text-muted">Fronta je prázdná.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

</div>

<script>
(function () {
    const campaignId = <?php echo (int)$campaign['id']; ?>;

    function refreshDetail() {
        fetch('ajax_campaign_detail.php?id=' + campaignId)
            .then(res => res.json())
            .then(data => {
                const statusEl = document.getElementById('detail-status');
                const progressEl = document.getElementById('detail-progress-wrapper');
                const cardsEl = document.getElementById('detail-stats-cards');
                const logsEl = document.getElementById('detail-logs-body');
                const queueEl = document.getElementById('detail-queue-body');

                if (statusEl && data.status_badge) {
                    statusEl.innerHTML = data.status_badge;
                }

                if (progressEl && data.progress_html) {
                    progressEl.innerHTML = data.progress_html;
                }

                if (cardsEl && data.cards_html) {
                    cardsEl.innerHTML = data.cards_html;
                }

                if (logsEl && data.logs_html) {
                    logsEl.innerHTML = data.logs_html;
                }

                if (queueEl && data.queue_html) {
                    queueEl.innerHTML = data.queue_html;
                }
            })
            .catch(err => console.error('Detail refresh error:', err));
    }

    setInterval(refreshDetail, 10000);
})();
</script>

<?php include "../includes/footer.php"; ?>