<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('logs');

$q       = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where  = [];
$params = [];
$types  = '';

if ($q !== '') {
    $where[] = "(u.email LIKE ? OR l.action LIKE ?)";
    $like = "%{$q}%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

$whereSql = $where ? " WHERE " . implode(" AND ", $where) : "";

// počet
$sqlCount = "
    SELECT COUNT(*) AS c
    FROM logs l
    LEFT JOIN users u ON l.admin_id = u.id
    {$whereSql}
";

$stmt = $conn->prepare($sqlCount);

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$total = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

$paramsUrl = $_GET;
unset($paramsUrl['page']);
$base = http_build_query($paramsUrl);
$base = $base ? ('?' . $base . '&') : '?';

// data
$sql = "
    SELECT l.*, u.email
    FROM logs l
    LEFT JOIN users u ON l.admin_id = u.id
    {$whereSql}
    ORDER BY l.id DESC
    LIMIT ?, ?
";

$stmt = $conn->prepare($sql);

$typesData = $types . "ii";
$paramsData = $params;
$paramsData[] = $offset;
$paramsData[] = $perPage;

$stmt->bind_param($typesData, ...$paramsData);
$stmt->execute();
$logs = $stmt->get_result();
$stmt->close();

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">
                Přehled logů <small class="text-muted">(<?= (int)$total ?>)</small>
            </h2>
            <p class="text-muted mb-0">Historie událostí v administraci</p>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-lg-7">
                    <input type="text"
                           name="q"
                           class="form-control"
                           placeholder="Hledat podle uživatele nebo akce..."
                           value="<?= e($q) ?>">
                </div>

                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-search me-1"></i> Hledat
                    </button>
                </div>

                <?php if ($q !== ''): ?>
                    <div class="col-auto">
                        <a href="list.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-1"></i> Zrušit
                        </a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover align-middle mb-0 bg-white">
                <thead class="table-light">
                    <tr>
                        <th width="70" class="text-center">ID</th>
                        <th width="240">Uživatel</th>
                        <th>Akce</th>
                        <th width="180">Datum</th>
                    </tr>
                </thead>

                <tbody>
                    <?php while ($l = $logs->fetch_assoc()): ?>
                        <tr>
                            <td class="text-center"><?= (int)$l['id'] ?></td>

                            <td>
                                <?php if (!empty($l['email'])): ?>
                                    <span class="fw-semibold"><?= e($l['email']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">Systém</span>
                                <?php endif; ?>
                            </td>

                            <td><?= e($l['action'] ?? '') ?></td>

                            <td class="text-nowrap">
                                <?= e(formatDateTimeCz($l['created_at'])) ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                    <?php if ($logs->num_rows === 0): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted p-4">
                                Žádné logy nebyly nalezeny.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (function_exists('renderPagination')): ?>
        <?php renderPagination($totalPages, $page, $base); ?>
    <?php elseif ($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $base ?>page=<?= $i ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>

</div>

<?php include "../includes/footer.php"; ?>