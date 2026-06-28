<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('redirects');

include "../includes/header.php";

$q       = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$created = isset($_GET['created']);
$updated = isset($_GET['updated']);
$deleted = isset($_GET['deleted']);

$where  = [];
$params = [];
$types  = '';

if ($q !== '') {
    $where[] = "(source_url LIKE ? OR target_url LIKE ? OR note LIKE ?)";
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
    FROM redirects
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
    SELECT id, source_url, target_url, status_code, is_active, note, created_at
    FROM redirects
    {$whereSql}
    ORDER BY id DESC
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
                Přesměrování <small class="text-muted">(<?= (int)$total ?>)</small>
            </h2>
            <p class="text-muted mb-0">Správa URL aliasů a přesměrování</p>
        </div>

        <div class="responsive-actions">
            <a href="add.php" class="btn btn-success">
                <i class="bi bi-plus-circle me-1"></i> Nové přesměrování
            </a>
        </div>
    </div>

    <?php if ($created): ?>
        <div class="alert alert-success">Přesměrování bylo vytvořeno.</div>
    <?php endif; ?>

    <?php if ($updated): ?>
        <div class="alert alert-success">Přesměrování bylo upraveno.</div>
    <?php endif; ?>

    <?php if ($deleted): ?>
        <div class="alert alert-success">Přesměrování bylo smazáno.</div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-lg-7">
                    <input type="text"
                           name="q"
                           class="form-control"
                           placeholder="Hledat podle zdrojové URL, cílové URL nebo poznámky..."
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
                        <th>Zdrojová URL</th>
                        <th>Cílová URL</th>
                        <th width="90" class="text-center">Kód</th>
                        <th width="120" class="text-center">Stav</th>
                        <th width="170">Vytvořeno</th>
                        <th width="140" class="text-center">Akce</th>
                    </tr>
                </thead>

                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <?php $id = (int)$row['id']; ?>

                        <tr>
                            <td class="text-center"><?= $id ?></td>

                            <td>
                                <div class="fw-semibold">
                                    <?= e($row['source_url'] ?? '') ?>
                                </div>

                                <?php if (!empty($row['note'])): ?>
                                    <div class="small text-muted">
                                        <?= e($row['note']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (!empty($row['target_url'])): ?>
                                    <a href="<?= e($row['target_url']) ?>" target="_blank" rel="noopener">
                                        <?= e($row['target_url']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-center">
                                <span class="badge bg-light text-dark">
                                    <?= (int)$row['status_code'] ?>
                                </span>
                            </td>

                            <td class="text-center">
                                <?php if ((int)$row['is_active'] === 1): ?>
                                    <span class="badge bg-success">Aktivní</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Neaktivní</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-nowrap">
                                <?= e(formatDateCz($row['created_at'])) ?>
                            </td>

                            <td class="text-center text-nowrap">
                                <a href="edit.php?id=<?= $id ?>"
                                   class="btn btn-sm btn-primary me-1"
                                   title="Upravit">
                                    <i class="bi bi-pencil"></i>
                                </a>

                                <a href="delete.php?id=<?= $id ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Opravdu smazat toto přesměrování?');"
                                   title="Smazat">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                    <?php if ($result->num_rows === 0): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted p-4">
                                Žádná přesměrování nebyla nalezena.
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