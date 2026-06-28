<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('invoices');

include "../includes/header.php";

$q = trim($_GET['q'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$series = trim($_GET['series'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
$types = '';

if ($q !== '') {
    $where[] = "(i.invoice_number LIKE ? OR c.name LIKE ?)";
    $like = "%" . $q . "%";
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

if ($filterStatus !== '' && in_array($filterStatus, ['issued','paid','overdue'], true)) {
    $where[] = "i.status = ?";
    $params[] = $filterStatus;
    $types .= 's';
}

if ($series !== '' && in_array($series, ['FV','ZAL','DOB'], true)) {
    $where[] = "i.series = ?";
    $params[] = $series;
    $types .= 's';
}

if ($dateFrom !== '') {
    $where[] = "i.issue_date >= ?";
    $params[] = $dateFrom;
    $types .= 's';
}

if ($dateTo !== '') {
    $where[] = "i.issue_date <= ?";
    $params[] = $dateTo;
    $types .= 's';
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

// statistiky
$stats = [
    'total' => 0,
    'paid' => 0,
    'overdue' => 0,
    'this_month' => 0,
];

$res = $conn->query("SELECT COALESCE(SUM(total_with_vat),0) AS total FROM invoices");
if ($res && ($row = $res->fetch_assoc())) {
    $stats['total'] = (float)$row['total'];
}

$res = $conn->query("SELECT COALESCE(SUM(total_with_vat),0) AS total FROM invoices WHERE status = 'paid'");
if ($res && ($row = $res->fetch_assoc())) {
    $stats['paid'] = (float)$row['total'];
}

$res = $conn->query("SELECT COALESCE(SUM(total_with_vat),0) AS total FROM invoices WHERE status = 'overdue'");
if ($res && ($row = $res->fetch_assoc())) {
    $stats['overdue'] = (float)$row['total'];
}

$res = $conn->query("
    SELECT COALESCE(SUM(total_with_vat),0) AS total
    FROM invoices
    WHERE YEAR(issue_date) = YEAR(CURDATE())
      AND MONTH(issue_date) = MONTH(CURDATE())
");
if ($res && ($row = $res->fetch_assoc())) {
    $stats['this_month'] = (float)$row['total'];
}

// count
$sqlCount = "
    SELECT COUNT(*) AS total
    FROM invoices i
    LEFT JOIN invoice_customers c ON c.id = i.customer_id
    $whereSql
";
$stmt = $conn->prepare($sqlCount);

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$total = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

// data
$sql = "
    SELECT i.*, c.name AS customer_name
    FROM invoices i
    LEFT JOIN invoice_customers c ON c.id = i.customer_id
    $whereSql
    ORDER BY i.issue_date DESC, i.id DESC
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

function invoiceStatusBadge(string $status): string
{
    switch ($status) {
        case 'issued':
            return '<span class="badge bg-primary">Vystavená</span>';
        case 'paid':
            return '<span class="badge bg-success">Uhrazená</span>';
        case 'overdue':
            return '<span class="badge bg-danger">Po splatnosti</span>';
        default:
            return '<span class="badge bg-light text-dark">' . htmlspecialchars($status) . '</span>';
    }
}
?>

<div class="container-fluid py-4">

    <div class="admin-page-header-content mb-4">
        <div>
            <h2 class="mb-1">Fakturace <small class="text-muted">(<?= $total ?>)</small></h1>
            <p class="text-muted mb-0">Přehled faktur</p>
        </div>
        <div class="responsive-actions">
            <a href="add.php" class="btn btn-success">
                <i class="bi bi-plus-lg"></i> Nová faktura
            </a>
        </div>
    </div>

    <?php include "_menu.php"; ?>

    <?php if (isset($_GET['created'])): ?>
        <div class="alert alert-success">Faktura byla vytvořena.</div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success">Faktura byla upravena.</div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">Faktura byla smazána.</div>
    <?php endif; ?>

    <?php if (isset($_GET['paid'])): ?>
        <div class="alert alert-success">Faktura byla označena jako uhrazená.</div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 text-center">
                <div class="card-body">
                    <div class="text-muted small">Vyfakturováno celkem</div>
                    <div class="fs-4 fw-bold"><?= number_format($stats['total'], 2, ',', ' ') ?> Kč</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 text-center">
                <div class="card-body">
                    <div class="text-muted small">Uhrazeno</div>
                    <div class="fs-4 fw-bold text-success"><?= number_format($stats['paid'], 2, ',', ' ') ?> Kč</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 text-center">
                <div class="card-body">
                    <div class="text-muted small">Po splatnosti</div>
                    <div class="fs-4 fw-bold text-danger"><?= number_format($stats['overdue'], 2, ',', ' ') ?> Kč</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 text-center">
                <div class="card-body">
                    <div class="text-muted small">Tento měsíc</div>
                    <div class="fs-4 fw-bold"><?= number_format($stats['this_month'], 2, ',', ' ') ?> Kč</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Hledat</label>
                    <input type="text"
                        name="q"
                        class="form-control"
                        placeholder="Číslo faktury nebo odběratel..."
                        value="<?= htmlspecialchars($q) ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Stav</label>
                    <select name="status" class="form-select">
                        <option value="">-- Vše --</option>
                        <option value="issued" <?= $filterStatus === 'issued' ? 'selected' : '' ?>>Vystavená</option>
                        <option value="paid" <?= $filterStatus === 'paid' ? 'selected' : '' ?>>Uhrazená</option>
                        <option value="overdue" <?= $filterStatus === 'overdue' ? 'selected' : '' ?>>Po splatnosti</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Typ</label>
                    <select name="series" class="form-select">
                        <option value="">-- Vše --</option>
                        <option value="FV" <?= $series === 'FV' ? 'selected' : '' ?>>Faktura</option>
                        <option value="ZAL" <?= $series === 'ZAL' ? 'selected' : '' ?>>Zálohová faktura</option>
                        <option value="DOB" <?= $series === 'DOB' ? 'selected' : '' ?>>Dobropis</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Vystavení od</label>
                    <input type="date"
                        name="date_from"
                        class="form-control"
                        value="<?= htmlspecialchars($dateFrom) ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Vystavení do</label>
                    <input type="date"
                        name="date_to"
                        class="form-control"
                        value="<?= htmlspecialchars($dateTo) ?>">
                </div>

                <div class="col-md-1 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary" title="Hledat">
                        <i class="bi bi-search"></i>
                    </button>
                    <a href="list.php" class="btn btn-outline-secondary" title="Reset">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>


        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover bg-white">
                <thead class="table-light">
                    <tr>
                        <th>Číslo</th>
                        <th>Odběratel</th>
                        <th>Vystavení</th>
                        <th>Splatnost</th>
                        <th>Stav</th>
                        <th>Celkem</th>
                        <th class="text-center">Akce</th>
                    </tr>
                </thead>
                <tbody>

                <?php if ($result->num_rows === 0): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted p-4">
                            Žádné faktury nebyly nalezeny.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php while ($row = $result->fetch_assoc()): 
                    $displayStatus = $row['status'] ?? 'issued';
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['invoice_number']) ?></strong></td>
                        <td><?= htmlspecialchars($row['customer_name'] ?? '') ?></td>
                        <td><?= htmlspecialchars(formatDateCz($row['issue_date'])) ?></td>
                        <td><?= htmlspecialchars(formatDateCz($row['due_date'])) ?></td>
                        <td>
                            <?php
                            switch ($displayStatus) {
                                case 'draft':
                                    echo '<span class="badge bg-secondary">Koncept</span>';
                                    break;

                                case 'issued':
                                    echo '<span class="badge bg-primary">Vystavená</span>';
                                    break;

                                case 'paid':
                                    echo '<span class="badge bg-success">Uhrazená</span>';
                                    break;

                                case 'overdue':
                                    $daysOverdue = 0;
                                    if (!empty($row['due_date'])) {
                                        $due = new DateTime($row['due_date']);
                                        $today = new DateTime(date('Y-m-d'));
                                        $daysOverdue = (int)$due->diff($today)->days;
                                    }
                                    $text = 'Po splatnosti';

                                    if ($daysOverdue > 0) {
                                        $text .= ' (' . czDays($daysOverdue) . ')';
                                    }
                                    echo '<span class="badge bg-danger">' . htmlspecialchars($text) . '</span>';
                                    break;
                                default:
                                    echo '<span class="badge bg-light text-dark">' . htmlspecialchars($displayStatus) . '</span>';
                            }
                            ?>
                        </td>
                        <td><?= number_format((float)$row['total_with_vat'], 2, ',', ' ') ?> <?= htmlspecialchars($row['currency']) ?></td>
                        <td class="text-end">
                            <div class="d-flex flex-wrap gap-1 justify-content-end">
                                <?php if (($row['status'] ?? '') !== 'paid'): ?>
                                    <a href="mark_paid.php?id=<?= (int)$row['id'] ?>"
                                    class="btn btn-sm btn-outline-success"
                                    title="Označit jako uhrazenou"
                                    onclick="return confirm('Opravdu označit fakturu jako uhrazenou?');">
                                        <i class="bi bi-check2-circle"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="detail.php?id=<?= (int)$row['id'] ?>"
                                class="btn btn-sm btn-outline-dark"
                                title="Detail faktury">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="edit.php?id=<?= (int)$row['id'] ?>"
                                   class="btn btn-sm btn-outline-primary"
                                   title="Upravit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="delete.php?id=<?= (int)$row['id'] ?>"
                                   class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('Opravdu smazat tuto fakturu?');"
                                   title="Smazat">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </td>
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
                        <a class="page-link" href="?q=<?= urlencode($q) ?>&status=<?= urlencode($filterStatus) ?>&page=<?= $i ?>">
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
include "../includes/footer.php";
?>