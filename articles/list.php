<?php 
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('articles');

include "../includes/header.php";

$q       = trim($_GET['q'] ?? '');
$isAdmin = isAdmin();
$uid     = (int)($_SESSION['admin_id'] ?? 0);

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$created = isset($_GET['created']);
$updated = isset($_GET['updated']);
$deleted = isset($_GET['deleted']);
$toggled = $_GET['toggled'] ?? null;

$joins = "
LEFT JOIN article_category ac ON a.id = ac.article_id
LEFT JOIN categories c ON ac.category_id = c.id
LEFT JOIN users u ON a.author_id = u.id
";

$where  = [];
$types  = '';
$params = [];

if (!$isAdmin) {
    $where[] = "a.author_id = ?";
    $types .= "i";
    $params[] = $uid;
}

if ($q !== '') {
    $where[] = "(a.title LIKE ? OR a.slug LIKE ?)";
    $types .= "ss";
    $like = "%{$q}%";
    $params[] = $like;
    $params[] = $like;
}

$whereSql = $where ? " WHERE " . implode(" AND ", $where) : "";

// počet
$sqlCount = "
    SELECT COUNT(DISTINCT a.id) AS cnt
    FROM articles a
    {$joins}
    {$whereSql}
";

$stmt = $conn->prepare($sqlCount);

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$total = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
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
$sqlData = "
    SELECT 
        a.*,
        GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') AS categories,
        u.email AS author
    FROM articles a
    {$joins}
    {$whereSql}
    GROUP BY a.id
    ORDER BY a.created_at DESC
    LIMIT ?, ?
";

$stmt = $conn->prepare($sqlData);

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
            Články <small class="text-muted">(<?= (int)$total ?>)</small>
        </h2>
        <p class="text-muted mb-0">
            Správa článků a aktualit
        </p>
    </div>
    <div class="responsive-actions">
        <a href="add.php" class="btn btn-success">
            <i class="bi bi-plus-circle me-1"></i>
            Přidat článek
        </a>
    </div>
</div>

    <?php if ($created): ?>
        <div class="alert alert-success">Článek byl vytvořen.</div>
    <?php endif; ?>

    <?php if ($updated): ?>
        <div class="alert alert-success">Článek byl upraven.</div>
    <?php endif; ?>

    <?php if ($deleted): ?>
        <div class="alert alert-success">Článek byl smazán.</div>
    <?php endif; ?>

    <?php if ($toggled): ?>
        <div class="alert alert-success">
            <?= $toggled === 'published' ? 'Článek byl publikován.' : 'Článek byl přepnut na koncept.' ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'not_found'): ?>
        <div class="alert alert-danger">
            Článek nebyl nalezen nebo k němu nemáte přístup.
        </div>
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
                        <th>Název / Slug</th>
                        <th>Kategorie</th>
                        <th>Autor</th>
                        <th width="150">Datum publikace</th>
                        <th width="120" class="text-center">Stav</th>
                        <th width="190" class="text-center">Akce</th>
                    </tr>
                </thead>

                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <?php
                            $status = $row['status'] ?? 'draft';
                            $isPublished = $status === 'published';

                            $badge = $isPublished
                                ? '<span class="badge bg-success">Publikováno</span>'
                                : '<span class="badge bg-secondary">Koncept</span>';

                            $toggleBtn = $isPublished
                                ? "<a href='status.php?id=" . (int)$row['id'] . "&to=draft' class='btn btn-sm btn-outline-secondary me-1' title='Přepnout na koncept'><i class='bi bi-eye-slash'></i></a>"
                                : "<a href='status.php?id=" . (int)$row['id'] . "&to=published' class='btn btn-sm btn-success me-1' title='Publikovat'><i class='bi bi-check2-circle'></i></a>";
                        ?>

                        <tr>
                            <td class="text-center"><?= (int)$row['id'] ?></td>

                            <td>
                                <div class="fw-semibold">
                                    <?= e($row['title'] ?? '') ?>
                                </div>

                                <?php if (!empty($row['slug'])): ?>
                                    <div class="text-muted small">
                                        /<?= e($row['slug']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (!empty($row['categories'])): ?>
                                    <?= e($row['categories']) ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (!empty($row['author'])): ?>
                                    <?= e($row['author']) ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-nowrap">
                                <?= !empty($row['publish_date']) ? e(formatDateCz($row['publish_date'])) : '<span class="text-muted">—</span>' ?>
                            </td>

                            <td class="text-center">
                                <?= $badge ?>
                            </td>

                            <td class="text-center text-nowrap">
                                <?= $toggleBtn ?>

                                <a href="edit.php?id=<?= (int)$row['id'] ?>"
                                   class="btn btn-sm btn-primary me-1"
                                   title="Upravit">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <a href="blocks.php?id=<?= (int)$row['id'] ?>"
                                class="btn btn-sm btn-outline-primary"
                                title="Bloky článku">
                                    <i class="bi bi-layout-three-columns"></i>
                                </a>
                                <a href="delete.php?id=<?= (int)$row['id'] ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Opravdu chcete článek smazat?');"
                                   title="Smazat">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                    <?php if ($result->num_rows === 0): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted p-4">
                                Žádné články nebyly nalezeny.
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