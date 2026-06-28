<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/mailing.php";

requirePermission('mailing');

include "../includes/header.php";

$status = trim($_GET['status'] ?? '');
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
$types = '';

if ($status !== '' && in_array($status, ['pending', 'processing', 'sent', 'failed', 'skipped'], true)) {
    $where[] = "mq.status = ?";
    $params[] = $status;
    $types .= 's';
}

if ($q !== '') {
    $where[] = "(mq.email LIKE ? OR mc.title LIKE ?)";
    $like = "%" . $q . "%";
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

// COUNT
$sqlCount = "
    SELECT COUNT(*) AS total
    FROM mailing_queue mq
    LEFT JOIN mailing_campaigns mc ON mc.id = mq.campaign_id
    $whereSql
";

$stmt = $conn->prepare($sqlCount);

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$total = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

// DATA
$sql = "
    SELECT mq.*, mc.title AS campaign_title
    FROM mailing_queue mq
    LEFT JOIN mailing_campaigns mc ON mc.id = mq.campaign_id
    $whereSql
    ORDER BY mq.id DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);

$bindParams = $params;
$bindTypes = $types . 'ii';
$bindParams[] = $perPage;
$bindParams[] = $offset;

$stmt->bind_param($bindTypes, ...$bindParams);
$stmt->execute();
$result = $stmt->get_result();

$totalPages = max(1, (int)ceil($total / $perPage));
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Mailing</h1>
            <p class="text-muted mb-0">Fronta e-mailů</p>
        </div>
    </div>

    <?php include "_menu.php"; ?>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">

                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">-- Stav --</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Čeká</option>
                        <option value="processing" <?= $status === 'processing' ? 'selected' : '' ?>>Zpracování</option>
                        <option value="sent" <?= $status === 'sent' ? 'selected' : '' ?>>Odesláno</option>
                        <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Chyba</option>
                        <option value="skipped" <?= $status === 'skipped' ? 'selected' : '' ?>>Přeskočeno</option>
                    </select>
                </div>

                <div class="col-md-5">
                    <input type="text"
                           name="q"
                           class="form-control"
                           placeholder="Hledat e-mail nebo kampaň..."
                           value="<?= htmlspecialchars($q) ?>">
                </div>

                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-search"></i> Hledat
                    </button>
                    <a href="queue_list.php" class="btn btn-outline-secondary">
                        Reset
                    </a>
                </div>

            </form>
        </div>
    </div>

        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover bg-white">

                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Kampaň</th>
                        <th>Email</th>
                        <th>Stav</th>
                        <th>Pokusy</th>
                        <th>Chyba</th>
                        <th>Odesláno</th>
                    </tr>
                </thead>

                <tbody id="queue-table-body">

                <?php if ($result->num_rows === 0): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted p-4">
                            Fronta je prázdná.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>

                        <td><?= (int)$row['id'] ?></td>

                        <td><?= htmlspecialchars($row['campaign_title'] ?? '-') ?></td>

                        <td><?= htmlspecialchars($row['email']) ?></td>

                        <td><?= mailingQueueStatusBadge($row['status']) ?></td>

                        <td><?= (int)$row['attempts'] ?></td>

                        <td>
                            <?php if (!empty($row['last_error'])): ?>
                                <span class="text-danger small">
                                    <?= htmlspecialchars(mb_strimwidth($row['last_error'], 0, 80, '...')) ?>
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>

                        <td><?= !empty($row['sent_at']) ? formatDateTimeCz(htmlspecialchars($row['sent_at'])) : '-' ?></td>

                    </tr>
                <?php endwhile; ?>

                </tbody>

            </table>
        </div>


    <?php if ($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link"
                           href="?q=<?= urlencode($q) ?>&status=<?= urlencode($status) ?>&page=<?= $i ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>

</div>

<?php
$stmt->close();
?>
<script>
(function () {
    const refreshInterval = 10000; // 10 sekund

    function refreshQueueTable() {
        const params = new URLSearchParams(window.location.search);

        fetch('ajax_queue_table.php?' + params.toString(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('queue-table-body');
            if (tbody && data.html !== undefined) {
                tbody.innerHTML = data.html;
            }
        })
        .catch(error => {
            console.error('Queue refresh error:', error);
        });
    }

    setInterval(refreshQueueTable, refreshInterval);
})();
</script>
<?php
include "../includes/footer.php";
?>