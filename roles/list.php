<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('users');

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

include "../includes/header.php";

$q       = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$created      = isset($_GET['created']);
$updated      = isset($_GET['updated']);
$deleted      = isset($_GET['deleted']);
$usersUpdated = isset($_GET['users_updated']);
$error        = $_GET['error'] ?? null;

$where  = [];
$params = [];
$types  = '';

if ($q !== '') {
    $where[] = "(r.name LIKE ? OR r.label LIKE ?)";
    $like = "%{$q}%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

$whereSql = $where ? " WHERE " . implode(" AND ", $where) : "";

// počet
$sqlCount = "
    SELECT COUNT(*) AS c
    FROM roles r
    {$whereSql}
";

$stmt = $conn->prepare($sqlCount);

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$totalRoles = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$totalPages = max(1, (int)ceil($totalRoles / $perPage));
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
    SELECT 
        r.id,
        r.name,
        r.label,
        COUNT(ur.user_id) AS members
    FROM roles r
    LEFT JOIN user_role ur ON ur.role_id = r.id
    {$whereSql}
    GROUP BY r.id, r.name, r.label
    ORDER BY r.name ASC
    LIMIT ?, ?
";

$stmt = $conn->prepare($sql);

$typesData = $types . "ii";
$paramsData = $params;
$paramsData[] = $offset;
$paramsData[] = $perPage;

$stmt->bind_param($typesData, ...$paramsData);
$stmt->execute();
$roles = $stmt->get_result();
$stmt->close();

$errorMessages = [
    'invalid'  => 'Neplatný požadavek.',
    'notfound' => 'Role nebyla nalezena.',
    'admin'    => 'Systémovou roli „admin“ nelze smazat.',
    'assigned' => 'Roli nelze smazat – je přiřazena uživatelům.',
    'server'   => 'Při mazání došlo k chybě. Zkuste to znovu.',
];
?>

<div class="container-fluid py-4">
    <div class="admin-page-header-content mb-4">
        <div>
            <h2 class="mb-1">
                Role <small class="text-muted">(<?= (int)$totalRoles ?>)</small>
            </h2>
            <p class="text-muted mb-0">Správa rolí, členů a oprávnění</p>
        </div>
        <div class="responsive-actions">
            <a href="add.php" class="btn btn-success">
                <i class="bi bi-plus-circle me-1"></i> Přidat roli
            </a>
        </div>
    </div>

    <?php if ($created): ?>
        <div class="alert alert-success">Role byla vytvořena.</div>
    <?php endif; ?>

    <?php if ($usersUpdated): ?>
        <div class="alert alert-success">Členové role byli uloženi.</div>
    <?php endif; ?>

    <?php if ($updated): ?>
        <div class="alert alert-success">Role byla upravena.</div>
    <?php endif; ?>

    <?php if ($deleted): ?>
        <div class="alert alert-success">Role byla smazána.</div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= e($errorMessages[$error] ?? 'Neznámá chyba.') ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-lg-7">
                    <input type="text"
                           name="q"
                           class="form-control"
                           placeholder="Hledat podle systémového názvu nebo názvu role..."
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
                        <th width="80" class="text-center">ID</th>
                        <th>Systémový název</th>
                        <th>Název</th>
                        <th width="140" class="text-center">Uživatelů</th>
                        <th width="360" class="text-center">Akce</th>
                    </tr>
                </thead>

                <tbody>
                    <?php while ($r = $roles->fetch_assoc()): ?>
                        <?php $id = (int)$r['id']; ?>

                        <tr>
                            <td class="text-center"><?= $id ?></td>

                            <td>
                                <?= e($r['name'] ?? '') ?>
                            </td>

                            <td>
                                <div class="fw-semibold">
                                    <?= e($r['label'] ?? '') ?>
                                </div>
                            </td>

                            <td class="text-center">
                                <span class="badge bg-light text-dark">
                                    <?= (int)$r['members'] ?>
                                </span>
                            </td>

                            <td class="text-center text-nowrap">
                                <a href="users.php?id=<?= $id ?>"
                                   class="btn btn-sm btn-secondary me-1"
                                   title="Členové role">
                                    <i class="bi bi-people me-1"></i> Členové
                                </a>

                                <a href="edit.php?id=<?= $id ?>"
                                   class="btn btn-sm btn-warning me-1"
                                   title="Práva modulů">
                                    <i class="bi bi-shield-check me-1"></i> Práva
                                </a>

                                <a href="meta.php?id=<?= $id ?>"
                                   class="btn btn-sm btn-primary me-1"
                                   title="Upravit název">
                                    <i class="bi bi-pencil me-1"></i> Upravit
                                </a>

                                <?php if (($r['name'] ?? '') !== 'admin'): ?>
                                    <a href="delete.php?id=<?= $id ?>&csrf=<?= urlencode($_SESSION['csrf']) ?>"
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Opravdu smazat roli „<?= e($r['label'] ?? '') ?>“?');"
                                       title="Smazat">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                    <?php if ($roles->num_rows === 0): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted p-4">
                                Žádné role nebyly nalezeny.
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