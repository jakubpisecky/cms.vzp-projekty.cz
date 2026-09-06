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

$created = isset($_GET['created']);
$updated = isset($_GET['updated']);
$deleted = isset($_GET['deleted']);
$error   = $_GET['error'] ?? null;

$where  = [];
$params = [];
$types  = '';

if ($q !== '') {
    $where[] = "(u.email LIKE ? OR u.role LIKE ? OR r.name LIKE ? OR r.label LIKE ?)";
    $like = "%{$q}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "ssss";
}

$whereSql = $where ? " WHERE " . implode(" AND ", $where) : "";

// počet
$sqlCount = "
    SELECT COUNT(DISTINCT u.id) AS c
    FROM users u
    LEFT JOIN user_role ur ON ur.user_id = u.id
    LEFT JOIN roles r ON r.id = ur.role_id
    {$whereSql}
";

$stmt = $conn->prepare($sqlCount);

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$totalUsers = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$totalPages = max(1, (int)ceil($totalUsers / $perPage));
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
        u.id,
        u.email,
        u.created_at,
        u.role AS role_legacy,
        GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS role_names,
        GROUP_CONCAT(DISTINCT r.label ORDER BY r.label SEPARATOR ', ') AS role_labels
    FROM users u
    LEFT JOIN user_role ur ON ur.user_id = u.id
    LEFT JOIN roles r ON r.id = ur.role_id
    {$whereSql}
    GROUP BY u.id
    ORDER BY u.id ASC
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
                Uživatelé <small class="text-muted">(<?= (int)$totalUsers ?>)</small>
            </h2>
            <p class="text-muted mb-0">Správa administrátorů a oprávnění</p>
        </div>

        <div class="responsive-actions">
            <a href="add.php" class="btn btn-success">
                <i class="bi bi-plus-circle me-1"></i> Přidat uživatele
            </a>
        </div>
    </div>

    <?php if ($created): ?>
        <div class="alert alert-success">Uživatel byl vytvořen.</div>
    <?php endif; ?>

    <?php if ($updated): ?>
        <div class="alert alert-success">Uživatel byl upraven.</div>
    <?php endif; ?>

    <?php if ($deleted): ?>
        <div class="alert alert-success">Uživatel byl úspěšně smazán.</div>
    <?php endif; ?>

    <?php if ($error === 'self_delete'): ?>
        <div class="alert alert-danger">Nemůžeš smazat svůj vlastní účet.</div>
    <?php endif; ?>

    <?php if ($error === 'not_found'): ?>
        <div class="alert alert-warning">Požadovaný uživatel nebyl nalezen.</div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-lg-7">
                    <input type="text"
                           name="q"
                           class="form-control"
                           placeholder="Hledat podle e-mailu nebo role..."
                           value="<?= e($q) ?>">
                </div>

                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">
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
                        <th>Email</th>
                        <th>Role</th>
                        <th width="180">Vytvořeno</th>
                        <th width="160" class="text-center">Akce</th>
                    </tr>
                </thead>

                <tbody>
                    <?php while ($u = $result->fetch_assoc()): ?>
                        <?php
                            $id = (int)$u['id'];
                            $roleLabels = trim((string)($u['role_labels'] ?? ''));
                            $roleNames  = trim((string)($u['role_names'] ?? ''));
                            $legacyRole = trim((string)($u['role_legacy'] ?? ''));

                            $roleText = $roleLabels !== '' ? $roleLabels : ($legacyRole !== '' ? $legacyRole : '—');
                            $roleSmall = $roleNames !== '' ? $roleNames : ($legacyRole !== '' ? $legacyRole : '');
                        ?>

                        <tr>
                            <td class="text-center"><?= $id ?></td>

                            <td>
                                <div class="fw-semibold">
                                    <?= e($u['email'] ?? '') ?>
                                </div>
                            </td>

                            <td>
                                <?= e($roleText) ?>

                                <?php if ($roleSmall !== ''): ?>
                                    <small class="text-muted">(<?= e($roleSmall) ?>)</small>
                                <?php endif; ?>
                            </td>

                            <td class="text-nowrap">
                                <?= e(formatDateCz($u['created_at'])) ?>
                            </td>

                            <td class="text-center text-nowrap">
                                <a href="edit.php?id=<?= $id ?>"
                                   class="btn btn-sm btn-primary me-1"
                                   title="Upravit">
                                    <i class="bi bi-pencil"></i>
                                </a>

                                <?php if ($id !== (int)($_SESSION['admin_id'] ?? 0)): ?>
                                    <a href="delete.php?id=<?= $id ?>&csrf=<?= urlencode($_SESSION['csrf']) ?>"
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Opravdu smazat uživatele?');"
                                       title="Smazat">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                    <?php if ($result->num_rows === 0): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted p-4">
                                Žádní uživatelé nebyli nalezeni.
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