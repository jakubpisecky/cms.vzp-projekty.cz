<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('mailing');

include "../includes/header.php";

$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
$types = '';

if ($q !== '') {
    $where[] = "(email LIKE ? OR message LIKE ?)";
    $like = "%" . $q . "%";
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

if ($status !== '' && in_array($status, ['info', 'success', 'error'], true)) {
    $where[] = "status = ?";
    $params[] = $status;
    $types .= 's';
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

// COUNT
$sqlCount = "SELECT COUNT(*) AS total FROM mailing_logs $whereSql";
$stmt = $conn->prepare($sqlCount);

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$total = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

// DATA
$sql = "
    SELECT *
    FROM mailing_logs
    $whereSql
    ORDER BY id DESC
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
            <p class="text-muted mb-0">Logy odesílání</p>
        </div>
    </div>

    <?php include "_menu.php"; ?>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-5">
                    <input type="text"
                           name="q"
                           class="form-control"
                           placeholder="Hledat podle e-mailu nebo zprávy..."
                           value="<?= htmlspecialchars($q) ?>">
                </div>

                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">-- Všechny stavy --</option>
                        <option value="info" <?= $status === 'info' ? 'selected' : '' ?>>Info</option>
                        <option value="success" <?= $status === 'success' ? 'selected' : '' ?>>Úspěch</option>
                        <option value="error" <?= $status === 'error' ? 'selected' : '' ?>>Chyba</option>
                    </select>
                </div>

                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-search"></i> Hledat
                    </button>
                    <a href="logs.php" class="btn btn-outline-secondary">
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
                        <th>Email</th>
                        <th>Status</th>
                        <th>Zpráva</th>
                        <th>Datum</th>
                    </tr>
                </thead>
                <tbody id="logs-table-body">

                <?php if ($result->num_rows === 0): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted p-4">
                            Žádné logy nebyly nalezeny.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= (int)$row['id'] ?></td>
                        <td><?= htmlspecialchars($row['email'] ?? '') ?></td>
                        <td>
                            <?php if ($row['status'] === 'success'): ?>
                                <span class="badge bg-success">OK</span>
                            <?php elseif ($row['status'] === 'error'): ?>
                                <span class="badge bg-danger">Chyba</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Info</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['message']) ?></td>
                        <td><?= formatDateTimeCz(htmlspecialchars($row['created_at'])) ?></td>
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

    function refreshLogsTable() {
        const params = new URLSearchParams(window.location.search);

        fetch('ajax_logs_table.php?' + params.toString(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('logs-table-body');
            if (tbody && data.html !== undefined) {
                tbody.innerHTML = data.html;
            }
        })
        .catch(error => {
            console.error('Logs refresh error:', error);
        });
    }

    setInterval(refreshLogsTable, refreshInterval);
})();
</script>
<?php
include "../includes/footer.php";
?>