<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('pages');

include "../includes/header.php";

$q          = trim($_GET['q'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 20;
$toggled    = $_GET['toggled'] ?? null;
$errorParam = $_GET['error'] ?? null;

$created = isset($_GET['created']);
$updated = isset($_GET['updated']);
$deleted = isset($_GET['deleted']);

function pageStatusBadge(string $status): string
{
    return $status === 'published'
        ? '<span class="badge bg-success">Publikováno</span>'
        : '<span class="badge bg-secondary">Koncept</span>';
}

function pageTemplateLabel(?string $template): string
{
    $labels = [
        'home'       => 'Homepage',
        'articles'   => 'Výpis článků',
        'page'       => 'Obecná stránka',
        'galleries'  => 'Fotogalerie',
        'contact'    => 'Kontaktní formulář',
        'universal'  => 'Univerzální stránka',
    ];

    return $labels[$template ?? 'page'] ?? 'Obecná stránka';
}

function pageHasBlocks(?string $template): bool
{
    return in_array($template, ['home', 'universal'], true);
}

function renderPageNode(array $n, int $level = 0): void
{
    $id        = (int)$n['id'];
    $parent_id = (int)$n['parent_id'];
    $order     = (int)$n['menu_order'];

    $title = htmlspecialchars(
        $n['title'] ?? '',
        ENT_QUOTES,
        'UTF-8'
    );

    $slug = htmlspecialchars(
        $n['slug'] ?? '',
        ENT_QUOTES,
        'UTF-8'
    );

    $status   = $n['status'] ?? 'draft';
    $template = $n['template'] ?? 'page';

    $badge = pageStatusBadge($status);

    $templateLabel = htmlspecialchars(
        pageTemplateLabel($template),
        ENT_QUOTES,
        'UTF-8'
    );

    $isPublished = $status === 'published';
    $isChild = $level > 0;

    /*
     * Odsazení podle úrovně.
     */
    $padding = $level * 28;

    $toggleBtn = $isPublished
        ? "
            <a
                href='status.php?id={$id}&to=draft'
                class='btn btn-sm btn-outline-secondary me-1'
                title='Přepnout na koncept'
            >
                <i class='bi bi-eye-slash'></i>
            </a>
        "
        : "
            <a
                href='status.php?id={$id}&to=published'
                class='btn btn-sm btn-success me-1'
                title='Publikovat'
            >
                <i class='bi bi-check2-circle'></i>
            </a>
        ";

    $blocksBtn = '';

    if (pageHasBlocks($template)) {
        $blocksBtn = "
            <a
                href='blocks.php?page_id={$id}'
                class='btn btn-sm btn-outline-primary me-1'
                title='Bloky stránky'
            >
                <i class='bi bi-layout-three-columns'></i>
            </a>
        ";
    }

    /*
     * Vizuální označení úrovně.
     */
    $hierarchyIcon = '';

    if ($isChild) {
        $hierarchyIcon = "
            <span class='text-muted me-2'>└</span>
        ";
    }

    $childLabel = '';

    if ($isChild) {
        $childLabel = "
            <div
                class='text-muted small'
                style='padding-left: {$padding}px;'
            >
                Podstránka
            </div>
        ";
    }

    echo "
        <tr
            class='page-row'
            data-id='{$id}'
            data-parent='{$parent_id}'
            data-level='{$level}'
        >

            <td class='text-center'>
                {$id}
            </td>

            <td>

                <div
                    style='padding-left: {$padding}px;'
                    class='d-flex align-items-center'
                >
                    {$hierarchyIcon}

                    <i class='bi bi-file-earmark-text me-2 text-muted'></i>

                    <span class='fw-semibold'>
                        {$title}
                    </span>
                </div>

                <div
                    class='text-muted small'
                    style='padding-left: {$padding}px;'
                >
                    /{$slug}
                </div>

                <div
                    class='text-muted small'
                    style='padding-left: {$padding}px;'
                >
                    {$templateLabel}
                </div>

                {$childLabel}

            </td>

            <td class='text-center'>
                {$badge}
            </td>

            <td class='text-center'>
                {$order}
            </td>

            <td class='text-left text-nowrap'>

                <div
                    class='btn-group me-1'
                    role='group'
                >
                    <button
                        type='button'
                        class='btn btn-sm btn-outline-secondary btn-move'
                        data-id='{$id}'
                        data-dir='up'
                        title='Posunout nahoru'
                    >
                        ↑
                    </button>

                    <button
                        type='button'
                        class='btn btn-sm btn-outline-secondary btn-move'
                        data-id='{$id}'
                        data-dir='down'
                        title='Posunout dolů'
                    >
                        ↓
                    </button>
                </div>

                {$toggleBtn}

                <a
                    href='add.php?parent_id={$id}'
                    class='btn btn-sm btn-success me-1'
                    title='Přidat podstránku'
                >
                    <i class='bi bi-plus-circle'></i>
                </a>

                {$blocksBtn}

                <a
                    href='edit.php?id={$id}'
                    class='btn btn-sm btn-primary me-1'
                    title='Upravit'
                >
                    <i class='bi bi-pencil'></i>
                </a>

                <a
                    href='delete.php?id={$id}'
                    class='btn btn-sm btn-danger'
                    onclick=\"return confirm('Smazat stránku? Podstránky budou přemístěny na úroveň rodiče.');\"
                    title='Smazat'
                >
                    <i class='bi bi-trash'></i>
                </a>

            </td>

        </tr>
    ";

    foreach (($n['children'] ?? []) as $child) {
        renderPageNode(
            $child,
            $level + 1
        );
    }
}

$totalAll = (int)$conn->query("SELECT COUNT(*) FROM pages")->fetch_row()[0];

$base = buildBaseUrl();
?>

<div class="container-fluid py-4">

    <div class="admin-page-header-content mb-4">
        <div>
            <h2 class="mb-1">
                Stránky <small class="text-muted">(<?= (int)$totalAll ?>)</small>
            </h2>
            <p class="text-muted mb-0">Správa stránek a stromové struktury webu</p>
        </div>

        <div class="responsive-actions">
            <a href="add.php" class="btn btn-success">
                <i class="bi bi-plus-circle me-1"></i> Nová stránka
            </a>
        </div>
    </div>

    <?php if ($created): ?>
        <div class="alert alert-success">Stránka byla vytvořena.</div>
    <?php endif; ?>

    <?php if ($updated): ?>
        <div class="alert alert-success">Stránka byla upravena.</div>
    <?php endif; ?>

    <?php if ($deleted): ?>
        <div class="alert alert-success">Stránka byla smazána.</div>
    <?php endif; ?>

    <?php if ($toggled): ?>
        <div class="alert alert-success">
            <?= $toggled === 'published' ? 'Stránka byla publikována.' : 'Stránka byla přepnuta na koncept.' ?>
        </div>
    <?php endif; ?>

    <?php if ($errorParam === 'not_found'): ?>
        <div class="alert alert-danger">Stránka nebyla nalezena.</div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-lg-7">
                    <input type="text"
                           name="q"
                           class="form-control"
                           placeholder="Hledat podle názvu nebo URL..."
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

<?php if ($q !== ''): ?>

    <?php
    $like = "%{$q}%";

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS cnt
        FROM pages p
        WHERE p.title LIKE ? OR p.slug LIKE ? AND owner_type = 'page'
    ");
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $total = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $stmt->close();

    $totalPages = max(1, (int)ceil($total / $perPage));

    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset = ($page - 1) * $perPage;

    $stmt = $conn->prepare("
        SELECT p.*, parent.title AS parent_title
        FROM pages p
        LEFT JOIN pages parent ON parent.id = p.parent_id
        WHERE p.title LIKE ? OR p.slug LIKE ? AND owner_type = 'page'
        ORDER BY p.parent_id ASC, p.menu_order ASC, p.id ASC
        LIMIT ?, ?
    ");
    $stmt->bind_param("ssii", $like, $like, $offset, $perPage);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    ?>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
            <strong>Výsledky hledání</strong>
            <span class="text-muted">(<?= (int)$total ?>)</span>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover align-middle mb-0 bg-white">
                <thead class="table-light">
                    <tr>
                        <th width="70" class="text-center">ID</th>
                        <th>Název / URL</th>
                        <th width="220">Nadřazená</th>
                        <th width="160" class="text-center">Typ</th>
                        <th width="120" class="text-center">Stav</th>
                        <th width="110" class="text-center">Pořadí</th>
                        <th width="260" class="text-left">Akce</th>
                        
                    </tr>
                </thead>

                <tbody>
                    <?php while ($n = $result->fetch_assoc()): ?>
                        <?php
                        $id = (int)$n['id'];
                        $status = $n['status'] ?? 'draft';
                        $template = $n['template'] ?? 'page';
                        $isPublished = $status === 'published';

                        $toggleBtn = $isPublished
                            ? "<a href='status.php?id={$id}&to=draft' class='btn btn-sm btn-outline-secondary me-1' title='Přepnout na koncept'><i class='bi bi-eye-slash'></i></a>"
                            : "<a href='status.php?id={$id}&to=published' class='btn btn-sm btn-success me-1' title='Publikovat'><i class='bi bi-check2-circle'></i></a>";
                        ?>

                        <tr>
                            <td class="text-center"><?= $id ?></td>

                            <td>
                                <div class="fw-semibold"><?= e($n['title'] ?? '') ?></div>
                                <div class="text-muted small">/<?= e($n['slug'] ?? '') ?></div>
                            </td>

                            <td>
                                <?= !empty($n['parent_title']) ? e($n['parent_title']) : '<span class="text-muted">—</span>' ?>
                            </td>

                            <td class="text-center">
                                <span class="badge bg-light text-dark border">
                                    <?= e(pageTemplateLabel($template)) ?>
                                </span>
                            </td>

                            <td class="text-center"><?= pageStatusBadge($status) ?></td>

                            <td class="text-center"><?= (int)$n['menu_order'] ?></td>

                            <td class="text-left text-nowrap">
                                <?= $toggleBtn ?>

                                <a href="add.php?parent_id=<?= $id ?>" class="btn btn-sm btn-success me-1" title="Přidat podstránku">
                                    <i class="bi bi-plus-circle"></i>
                                </a>

                                <?php if (pageHasBlocks($template)): ?>
                                    <a href="blocks.php?page_id=<?= $id ?>"
                                       class="btn btn-sm btn-outline-primary me-1"
                                       title="Bloky stránky">
                                        <i class="bi bi-layout-three-columns"></i>
                                    </a>
                                <?php endif; ?>

                                <a href="edit.php?id=<?= $id ?>" class="btn btn-sm btn-primary me-1" title="Upravit">
                                    <i class="bi bi-pencil"></i>
                                </a>

                                <a href="delete.php?id=<?= $id ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Smazat stránku? Podstránky budou přemístěny na úroveň rodiče.');"
                                   title="Smazat">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                    <?php if ($result->num_rows === 0): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted p-4">
                                Nic nebylo nalezeno.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php renderPagination($totalPages, $page, $base); ?>

<?php else: ?>

    <?php
    $res = $conn->query("
        SELECT *
        FROM pages WHERE owner_type = 'page'
        ORDER BY parent_id ASC, menu_order ASC, id ASC
    ");

    $rows = [];

    while ($r = $res->fetch_assoc()) {
        $r['children'] = [];
        $rows[] = $r;
    }

    $tree = [];
    $byId = [];

    foreach ($rows as $r) {
        $byId[(int)$r['id']] = $r;
    }

    foreach ($byId as $id => &$node) {
        $parentId = (int)$node['parent_id'];

        if ($parentId === 0 || !isset($byId[$parentId])) {
            $tree[] = &$node;
        } else {
            $byId[$parentId]['children'][] = &$node;
        }
    }

    unset($node);

    $totalRoots = count($tree);
    $totalPages = max(1, (int)ceil($totalRoots / $perPage));

    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset = ($page - 1) * $perPage;
    $rootsPage = array_slice($tree, $offset, $perPage);
    ?>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
            <strong>Strom stránek</strong>
            <span class="text-muted">(kořenové stránky: <?= (int)$totalRoots ?>)</span>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover align-middle mb-0 bg-white">
                <thead class="table-light">
                    <tr>
                        <th width="70" class="text-center">ID</th>
                        <th>Název / URL</th>
                        <th width="120" class="text-center">Stav</th>
                        <th width="110" class="text-center">Pořadí</th>
                        <th width="280" class="text-left">Akce</th>
                    </tr>
                </thead>

                <tbody id="pagesTableBody">
                    <?php foreach ($rootsPage as $n): ?>
                        <?php renderPageNode($n); ?>
                    <?php endforeach; ?>

                    <?php if (empty($rootsPage)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted p-4">
                                Žádné stránky nebyly nalezeny.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php renderPagination($totalPages, $page, $base); ?>

<?php endif; ?>

</div>

<script>
document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-move');

    if (!btn) {
        return;
    }

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