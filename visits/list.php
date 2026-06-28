<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('visits');

include "../includes/header.php";

$q       = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where  = [];
$params = [];
$types  = '';

if ($q !== '') {
    $where[] = "(v.name LIKE ? OR v.email LIKE ? OR a.email LIKE ?)";
    $like = "%{$q}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

$whereSql = $where ? " WHERE " . implode(" AND ", $where) : "";

// počet
$sqlCount = "
    SELECT COUNT(*) AS c
    FROM visits v
    LEFT JOIN users a ON v.confirmed_by = a.id
    {$whereSql}
";

$stmt = $conn->prepare($sqlCount);

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$totalVisits = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$totalPages = max(1, (int)ceil($totalVisits / $perPage));
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
    SELECT v.*, a.email AS admin_email
    FROM visits v
    LEFT JOIN users a ON v.confirmed_by = a.id
    {$whereSql}
    ORDER BY v.created_at DESC, v.id DESC
    LIMIT ?, ?
";

$stmt = $conn->prepare($sql);

$typesData = $types . "ii";
$paramsData = $params;
$paramsData[] = $offset;
$paramsData[] = $perPage;

$stmt->bind_param($typesData, ...$paramsData);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>

<div class="container-fluid py-4">

    <div class="admin-page-header-content mb-4">
        <div>
            <h2 class="mb-1">
                Návštěvy <small class="text-muted">(<?= (int)$totalVisits ?>)</small>
            </h2>
            <p class="text-muted mb-0">Správa návštěv a potvrzení plateb</p>
        </div>

        <div class="responsive-actions">
            <a href="add.php" class="btn btn-success">
                <i class="bi bi-plus-circle me-1"></i> Přidat návštěvu
            </a>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-lg-7">
                    <input type="text"
                           name="q"
                           class="form-control"
                           placeholder="Hledat podle jména, e-mailu nebo potvrzujícího uživatele..."
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
                        <th>Jméno</th>
                        <th>Email</th>
                        <th width="150">Vytvořeno</th>
                        <th width="110" class="text-center">Uhrazeno</th>
                        <th width="150">Potvrzeno</th>
                        <th>Potvrdil</th>
                        <th width="220" class="text-center">Akce</th>
                    </tr>
                </thead>

                <tbody>
                    <?php while ($visit = $result->fetch_assoc()): ?>
                        <?php $id = (int)$visit['id']; ?>

                        <tr>
                            <td class="text-center"><?= $id ?></td>

                            <td>
                                <div class="fw-semibold">
                                    <?= e($visit['name'] ?? '') ?>
                                </div>
                            </td>

                            <td>
                                <?php if (!empty($visit['email'])): ?>
                                    <a href="mailto:<?= e($visit['email']) ?>">
                                        <?= e($visit['email']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-nowrap">
                                <?= e(formatDateCz($visit['created_at'])) ?>
                            </td>

                            <td class="text-center">
                                <?php if (!empty($visit['paid'])): ?>
                                    <span class="badge bg-success">Ano</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Ne</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-nowrap">
                                <?php if (!empty($visit['confirmed_at'])): ?>
                                    <?= e(formatDateCz($visit['confirmed_at'])) ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (!empty($visit['admin_email'])): ?>
                                    <?= e($visit['admin_email']) ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-center text-nowrap">
                                <a href="edit.php?id=<?= $id ?>"
                                   class="btn btn-sm btn-primary me-1"
                                   title="Upravit">
                                    <i class="bi bi-pencil"></i>
                                </a>

                                <?php if (empty($visit['paid'])): ?>
                                    <a href="update.php?action=pay&id=<?= $id ?>"
                                       class="btn btn-sm btn-success me-1"
                                       title="Označit jako uhrazené">
                                        <i class="bi bi-currency-dollar"></i>
                                    </a>
                                <?php endif; ?>

                                <?php if (empty($visit['confirmed_at'])): ?>
                                    <a href="update.php?action=confirm&id=<?= $id ?>"
                                       class="btn btn-sm btn-warning me-1"
                                       title="Potvrdit">
                                        <i class="bi bi-check-circle"></i>
                                    </a>
                                <?php endif; ?>

                                <a href="delete.php?id=<?= $id ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Opravdu smazat návštěvu?');"
                                   title="Smazat">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                    <?php if ($result->num_rows === 0): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted p-4">
                                Žádné návštěvy nebyly nalezeny.
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