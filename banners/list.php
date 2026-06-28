<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('banners');

include "../includes/header.php";

$q       = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$created = isset($_GET['created']);
$updated = isset($_GET['updated']);
$deleted = isset($_GET['deleted']);
$toggled = $_GET['toggled'] ?? null;

$where  = [];
$params = [];
$types  = '';

if ($q !== '') {
    $where[] = "(title LIKE ? OR slug LIKE ?)";
    $like = "%{$q}%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

$whereSql = $where ? " WHERE " . implode(" AND ", $where) : "";

// počet
$sqlCount = "SELECT COUNT(*) AS c FROM banners {$whereSql}";
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
    SELECT id, title, slug, image, status, sort_order, created_at
    FROM banners
    {$whereSql}
    ORDER BY sort_order ASC, id ASC
    LIMIT ?, ?
";

$stmt = $conn->prepare($sql);

$typesData = $types . "ii";
$paramsData = $params;
$paramsData[] = $offset;
$paramsData[] = $perPage;

$stmt->bind_param($typesData, ...$paramsData);
$stmt->execute();
$res = $stmt->get_result();
$stmt->close();
?>

<div class="container-fluid py-4">

    <div class="admin-page-header-content mb-4">
        <div>
            <h2 class="mb-1">
                Bannery <small class="text-muted">(<?= (int)$total ?>)</small>
            </h2>
            <p class="text-muted mb-0">Správa bannerů na webu</p>
        </div>
        <div class="responsive-actions">
            <a href="add.php" class="btn btn-success">
                <i class="bi bi-plus-circle me-1"></i> Nový banner
            </a>
        </div>
    </div>

    <?php if ($created): ?>
        <div class="alert alert-success">Banner byl vytvořen.</div>
    <?php endif; ?>

    <?php if ($updated): ?>
        <div class="alert alert-success">Banner byl upraven.</div>
    <?php endif; ?>

    <?php if ($deleted): ?>
        <div class="alert alert-success">Banner byl smazán.</div>
    <?php endif; ?>

    <?php if ($toggled): ?>
        <div class="alert alert-success">
            <?= $toggled === 'published' ? 'Banner publikován.' : 'Banner přepnut na koncept.' ?>
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
                        <th width="170">Obrázek</th>
                        <th width="120" class="text-center">Stav</th>
                        <th width="110" class="text-center">Pořadí</th>
                        <th width="260" class="text-center">Akce</th>
                    </tr>
                </thead>

                <tbody>
                    <?php while ($r = $res->fetch_assoc()): ?>
                        <?php
                            $id = (int)$r['id'];
                            $status = $r['status'] ?? 'draft';
                            $isPublished = $status === 'published';

                            $badge = $isPublished
                                ? '<span class="badge bg-success">Publikováno</span>'
                                : '<span class="badge bg-secondary">Koncept</span>';

                            $toggleBtn = $isPublished
                                ? "<a href='status.php?id={$id}&to=draft' class='btn btn-sm btn-outline-secondary me-1' title='Přepnout na koncept'><i class='bi bi-eye-slash'></i></a>"
                                : "<a href='status.php?id={$id}&to=published' class='btn btn-sm btn-success me-1' title='Publikovat'><i class='bi bi-check2-circle'></i></a>";
                        ?>

                        <tr>
                            <td class="text-center"><?= $id ?></td>

                            <td>
                                <div class="fw-semibold"><?= e($r['title'] ?? '') ?></div>

                                <?php if (!empty($r['slug'])): ?>
                                    <div class="text-muted small">/<?= e($r['slug']) ?></div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (!empty($r['image'])): ?>
                                    <img src="<?= e($r['image']) ?>"
                                         alt=""
                                         class="img-fluid rounded border"
                                         style="max-height:60px">
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-center">
                                <?= $badge ?>
                            </td>

                            <td class="text-center">
                                <?= (int)$r['sort_order'] ?>
                            </td>

                            <td class="text-center text-nowrap">
                                <div class="btn-group me-1" role="group">
                                    <button type="button"
                                            class="btn btn-sm btn-outline-secondary btn-move"
                                            data-id="<?= $id ?>"
                                            data-dir="up"
                                            title="Posunout nahoru">
                                        ↑
                                    </button>

                                    <button type="button"
                                            class="btn btn-sm btn-outline-secondary btn-move"
                                            data-id="<?= $id ?>"
                                            data-dir="down"
                                            title="Posunout dolů">
                                        ↓
                                    </button>
                                </div>

                                <?= $toggleBtn ?>

                                <a href="edit.php?id=<?= $id ?>"
                                   class="btn btn-sm btn-primary me-1"
                                   title="Upravit">
                                    <i class="bi bi-pencil"></i>
                                </a>

                                <a href="delete.php?id=<?= $id ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Smazat banner?');"
                                   title="Smazat">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                    <?php if ($res->num_rows === 0): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted p-4">
                                Žádné bannery nebyly nalezeny.
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

<script>
document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-move');
    if (!btn) return;

    btn.disabled = true;

    try {
        const r = await fetch('reorder.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: 'id=' + encodeURIComponent(btn.dataset.id) + '&dir=' + encodeURIComponent(btn.dataset.dir)
        });

        const text = await r.text();
        let j;

        try {
            j = JSON.parse(text);
        } catch {
            throw new Error('Server nevrátil JSON:\n' + text.slice(0, 300));
        }

        if (!j.ok) {
            throw new Error(j.msg || 'Chyba při změně pořadí');
        }

        location.reload();

    } catch (err) {
        alert(err.message);
        btn.disabled = false;
    }
});
</script>

<?php include "../includes/footer.php"; ?>