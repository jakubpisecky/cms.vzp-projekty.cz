<?php 
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('categories');

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
    $where[] = "(name LIKE ? OR slug LIKE ?)";
    $like = "%{$q}%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

$whereSql = $where ? " WHERE " . implode(" AND ", $where) : "";

// počet
$sqlCount = "SELECT COUNT(*) AS c FROM categories {$whereSql}";
$stmt = $conn->prepare($sqlCount);

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$totalCategories = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$totalPages = max(1, (int)ceil($totalCategories / $perPage));
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
    SELECT id, name, slug
    FROM categories
    {$whereSql}
    ORDER BY name ASC
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
                Kategorie <small class="text-muted">(<?= (int)$totalCategories ?>)</small>
            </h2>
            <p class="text-muted mb-0">Správa kategorií článků</p>
        </div>

        <div class="responsive-actions">
            <a href="add.php" class="btn btn-success">
                <i class="bi bi-plus-circle me-1"></i> Přidat kategorii
            </a>
        </div>
    </div>

    <?php if ($created): ?>
        <div class="alert alert-success">Kategorie byla vytvořena.</div>
    <?php endif; ?>

    <?php if ($updated): ?>
        <div class="alert alert-success">Kategorie byla upravena.</div>
    <?php endif; ?>

    <?php if ($deleted): ?>
        <div class="alert alert-success">Kategorie byla smazána.</div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-lg-7">
                    <input type="text"
                           name="q"
                           class="form-control"
                           placeholder="Hledat podle názvu nebo slugu..."
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
                        <th>Název</th>
                        <th>Slug</th>
                        <th width="140" class="text-center">Akce</th>
                    </tr>
                </thead>

                <tbody>
                    <?php while ($category = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="text-center"><?= (int)$category['id'] ?></td>

                            <td>
                                <div class="fw-semibold">
                                    <?= e($category['name'] ?? '') ?>
                                </div>
                            </td>

                            <td>
                                <?php if (!empty($category['slug'])): ?>
                                    <span class="text-muted small">/<?= e($category['slug']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-center text-nowrap">
                                <a href="edit.php?id=<?= (int)$category['id'] ?>"
                                   class="btn btn-sm btn-primary me-1"
                                   title="Upravit">
                                    <i class="bi bi-pencil"></i>
                                </a>

                                <a href="delete.php?id=<?= (int)$category['id'] ?>"
                                   class="btn btn-sm btn-danger"
                                   title="Smazat"
                                   onclick="return confirm('Opravdu chcete kategorii smazat?');">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                    <?php if ($result->num_rows === 0): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted p-4">
                                Žádné kategorie nebyly nalezeny.
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