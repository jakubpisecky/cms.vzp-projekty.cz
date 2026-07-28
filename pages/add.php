<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('pages');

$msg = "";

// --- helpers ---
function makeUniqueSlug(mysqli $conn, string $baseSlug): string {
    $slug = $baseSlug !== '' ? $baseSlug : 'stranka';
    $i = 1;

    $stmt = $conn->prepare("SELECT COUNT(*) FROM pages WHERE slug=?");

    while (true) {
        $check = $slug;
        $stmt->bind_param("s", $check);
        $stmt->execute();
        $stmt->bind_result($cnt);
        $stmt->fetch();
        $stmt->free_result();

        if ((int)$cnt === 0) {
            $stmt->close();
            return $slug;
        }

        $i++;
        $slug = $baseSlug . '-' . $i;
    }
}

function nextOrderForParent(mysqli $conn, int $parentId): int {
    $stmt = $conn->prepare("SELECT COALESCE(MAX(menu_order), 0) + 10 AS next_ord FROM pages WHERE parent_id=?");
    $stmt->bind_param("i", $parentId);
    $stmt->execute();

    $next = (int)($stmt->get_result()->fetch_assoc()['next_ord'] ?? 10);
    $stmt->close();

    return $next ?: 10;
}

function normalizeSiblings(mysqli $conn, int $parentId): void {
    $stmt = $conn->prepare("SELECT id FROM pages WHERE parent_id=? ORDER BY menu_order ASC, id ASC FOR UPDATE");
    $stmt->bind_param("i", $parentId);
    $stmt->execute();

    $res = $stmt->get_result();
    $stmt->close();

    $upd = $conn->prepare("UPDATE pages SET menu_order=? WHERE id=?");
    $order = 10;

    while ($row = $res->fetch_assoc()) {
        $id = (int)$row['id'];
        $upd->bind_param("ii", $order, $id);
        $upd->execute();
        $order += 10;
    }

    $upd->close();
}

function getPagesFlat(mysqli $conn): array {
    $res = $conn->query("SELECT id, title, parent_id FROM pages ORDER BY parent_id ASC, menu_order ASC, id ASC");

    $rows = [];

    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }

    return $rows;
}

function buildTree(array $rows): array {
    $byId = [];
    $tree = [];

    foreach ($rows as $r) {
        $r['children'] = [];
        $byId[$r['id']] = $r;
    }

    foreach ($byId as $id => &$n) {
        if ((int)$n['parent_id'] === 0) {
            $tree[] = &$n;
        } elseif (isset($byId[$n['parent_id']])) {
            $byId[$n['parent_id']]['children'][] = &$n;
        }
    }

    unset($n);

    return $tree;
}

function renderParentOptions(array $nodes, int $level, int $selectedId) {
    $pad = str_repeat('— ', $level);

    foreach ($nodes as $n) {
        $sel = ((int)$n['id'] === (int)$selectedId) ? 'selected' : '';

        echo "<option value='".(int)$n['id']."' $sel>"
            . htmlspecialchars($pad . $n['title'])
            . "</option>";

        if (!empty($n['children'])) {
            renderParentOptions($n['children'], $level + 1, $selectedId);
        }
    }
}

// --- kontrola existence homepage ---
$homepageExists = false;

$resHome = $conn->query("
    SELECT id 
    FROM pages 
    WHERE template = 'home' 
    LIMIT 1
");

if ($resHome && $resHome->num_rows > 0) {
    $homepageExists = true;
}

// --- typy stránek ---
$pageTemplatesLocal = [
    'home' => [
        'title' => 'Homepage',
        'description' => 'Úvodní stránka webu skládaná z bloků.',
        'icon' => 'bi bi-house',
        'blocks' => true,
    ],
    'articles' => [
        'title' => 'Výpis článků',
        'description' => 'Automatický výpis aktualit nebo článků.',
        'icon' => 'bi bi-newspaper',
        'blocks' => false,
    ],
    'page' => [
        'title' => 'Obecná stránka',
        'description' => 'Klasická stránka s jedním editorem obsahu.',
        'icon' => 'bi bi-file-text',
        'blocks' => false,
    ],
    'gallery' => [
        'title' => 'Fotogalerie',
        'description' => 'Stránka s výpisem fotogalerií.',
        'icon' => 'bi bi-images',
        'blocks' => false,
    ],
    'contact' => [
        'title' => 'Kontaktní formulář',
        'description' => 'Kontaktní stránka s formulářem.',
        'icon' => 'bi bi-envelope',
        'blocks' => false,
    ],
    'universal' => [
        'title' => 'Univerzální stránka',
        'description' => 'Stránka skládaná z obsahových bloků.',
        'icon' => 'bi bi-layout-three-columns',
        'blocks' => true,
    ],
    'workplaces' => [
        'title' => 'Výpis pracovišť',
        'description' => 'Rozcestník klinik, oddělení a dalších pracovišť.',
        'icon' => 'bi bi-hospital',
        'blocks' => false,
    ],
];

// DŮLEŽITÉ: tady už nevoláme normalizePageTemplate()
$template = trim($_GET['template'] ?? ($_POST['template'] ?? ''));

// výběr typu stránky
if ($template === '') {
    include "../includes/header.php";
    ?>

    <div class="container-fluid py-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">Přidat stránku</h2>
                <p class="text-muted mb-0">Nejdříve vyberte typ stránky</p>
            </div>

            <a href="list.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Zpět
            </a>
        </div>

        <div class="row g-4">
            <?php foreach ($pageTemplatesLocal as $key => $tpl): ?>

                <?php if ($key === 'home' && $homepageExists): ?>
                    <?php continue; ?>
                <?php endif; ?>

                <div class="col-md-6 col-xl-4">
                    <a href="add.php?template=<?= htmlspecialchars($key) ?>" class="text-decoration-none text-dark">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="fs-2 text-primary">
                                        <i class="<?= htmlspecialchars($tpl['icon']) ?>"></i>
                                    </div>

                                    <div>
                                        <h5 class="mb-1"><?= htmlspecialchars($tpl['title']) ?></h5>
                                        <p class="text-muted mb-0">
                                            <?= htmlspecialchars($tpl['description']) ?>
                                        </p>

                                        <?php if (!empty($tpl['blocks'])): ?>
                                            <div class="mt-2">
                                                <span class="badge bg-info">bloková stránka</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

            <?php endforeach; ?>
        </div>

        <?php if ($homepageExists): ?>
            <div class="alert alert-info mt-4">
                Homepage už je vytvořená, proto se v nabídce nezobrazuje.
            </div>
        <?php endif; ?>

    </div>

    <?php
    include "../includes/footer.php";
    exit;
}

// neplatný typ stránky
if (!isset($pageTemplatesLocal[$template])) {
    header("Location: add.php");
    exit;
}

// ochrana proti vytvoření druhé homepage přes URL
if ($template === 'home' && $homepageExists) {
    include "../includes/header.php";
    ?>

    <div class="container-fluid py-4">

        <div class="alert alert-warning">
            Homepage už existuje. Není možné vytvořit druhou.
        </div>

        <a href="list.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Zpět na stránky
        </a>

    </div>

    <?php
    include "../includes/footer.php";
    exit;
}

$isBlockPage = !empty($pageTemplatesLocal[$template]['blocks']);

// --- defaults ---
$parent_id_default = intval($_GET['parent_id'] ?? 0);
$prefill_order = nextOrderForParent($conn, $parent_id_default);

// --- POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title        = trim($_POST['title'] ?? '');
    $slugInput    = trim($_POST['slug'] ?? '');
    $slug         = $slugInput !== '' ? slugify($slugInput) : slugify($title);
    $status       = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $parent_id    = intval($_POST['parent_id'] ?? 0);
    $menu_order   = trim($_POST['menu_order'] ?? '');
    $show_in_menu = isset($_POST['show_in_menu']) ? 1 : 0;
    $show_breadcrumbs = isset($_POST['show_breadcrumbs']) ? 1 : 0;
    $content = $isBlockPage ? '' : ($_POST['content'] ?? '');
    $meta_title       = trim($_POST['meta_title'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');

    if ($title === '') {
        $msg = "Titulek je povinný.";
    } else {
        if ($slug === '') {
            $slug = 'stranka';
        }

        $slug = makeUniqueSlug($conn, $slug);

        if ($menu_order === '' || !is_numeric($menu_order)) {
            $menu_order = nextOrderForParent($conn, $parent_id);
        } else {
            $menu_order = intval($menu_order);
        }

        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare(
                "INSERT INTO pages
                 (title, slug, parent_id, status, menu_order, show_in_menu, show_breadcrumbs, content, meta_title, meta_description, template, created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())"
            );

            $stmt->bind_param(
                "ssisiiissss",
                $title,
                $slug,
                $parent_id,
                $status,
                $menu_order,
                $show_in_menu,
                $show_breadcrumbs,
                $content,
                $meta_title,
                $meta_description,
                $template
            );

            $stmt->execute();
            $newPageId = (int)$stmt->insert_id;
            $stmt->close();

            normalizeSiblings($conn, $parent_id);

            $conn->commit();

            logAction("Vytvořena stránka '$title'");

            if ($isBlockPage) {
                header("Location: blocks.php?page_id=" . $newPageId . "&created=1");
                exit;
            }

            header("Location: list.php?created=1");
            exit;

        } catch (Throwable $e) {
            $conn->rollback();
            $msg = "Chyba při ukládání: " . htmlspecialchars($e->getMessage());
        }
    }

    $prefill_order = nextOrderForParent($conn, $parent_id);

    if (is_numeric($_POST['menu_order'] ?? '')) {
        $prefill_order = (int)$_POST['menu_order'];
    }

    $parent_id_default = $parent_id;
}

// data pro select rodiče
$tree = buildTree(getPagesFlat($conn));

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Přidat stránku</h2>
            <p class="text-muted mb-0">
                Typ stránky:
                <strong><?= htmlspecialchars($pageTemplatesLocal[$template]['title']) ?></strong>
            </p>
        </div>

        <a href="add.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Změnit typ stránky
        </a>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="alert <?= $isBlockPage ? 'alert-info' : 'alert-secondary' ?>">
        <strong><?= htmlspecialchars($pageTemplatesLocal[$template]['title']) ?></strong><br>
        <?= htmlspecialchars($pageTemplatesLocal[$template]['description']) ?>

        <?php if ($isBlockPage): ?>
            <div class="mt-2">
                Po uložení budete přesměrováni do správy bloků této stránky.
            </div>
        <?php endif; ?>
    </div>

    <form method="post" class="bg-white p-4 rounded shadow-sm">

        <input type="hidden" name="template" value="<?= htmlspecialchars($template) ?>">

        <div class="row mb-3">
            <div class="col-md-8">
                <label class="form-label">Titulek</label>
                <input type="text"
                       name="title"
                       class="form-control"
                       required
                       value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-8">
                <label class="form-label">Nadřazená stránka</label>
                <select name="parent_id" id="parent_id" class="form-select">
                    <option value="0" <?= $parent_id_default === 0 ? 'selected' : '' ?>>
                        — První úroveň —
                    </option>

                    <?php renderParentOptions($tree, 0, $parent_id_default); ?>
                </select>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-8">
                <label class="form-label">URL (slug)</label>
                <input type="text"
                       name="slug"
                       class="form-control"
                       placeholder="prázdné = vygeneruje se z titulku"
                       value="<?= htmlspecialchars($_POST['slug'] ?? '') ?>">
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-8">
                <?php $st = $_POST['status'] ?? 'draft'; ?>

                <label class="form-label">Stav</label>
                <select name="status" class="form-select">
                    <option value="draft" <?= $st === 'published' ? '' : 'selected' ?>>
                        Koncept
                    </option>
                    <option value="published" <?= $st === 'published' ? 'selected' : '' ?>>
                        Publikováno
                    </option>
                </select>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-8">
                <label class="form-label">Pořadí v menu</label>
                <input type="number"
                       name="menu_order"
                       id="menu_order"
                       class="form-control"
                       value="<?= (int)$prefill_order ?>">
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-8">
                <div class="form-check form-switch">
                    <input class="form-check-input"
                           type="checkbox"
                           name="show_in_menu"
                           id="show_in_menu"
                           <?= isset($_POST['show_in_menu']) ? 'checked' : 'checked' ?>>

                    <label class="form-check-label" for="show_in_menu">
                        Zobrazit v menu
                    </label>
                </div>
            </div>
        </div>
        <?php if ($template === 'universal'): ?>
            <div class="row mb-3">
                <div class="col-md-8">
                    <div class="form-check form-switch">
                        <input class="form-check-input"
                            type="checkbox"
                            name="show_breadcrumbs"
                            id="show_breadcrumbs"
                            value="1"
                            <?= isset($_POST['show_breadcrumbs']) ? 'checked' : 'checked' ?>>

                        <label class="form-check-label" for="show_breadcrumbs">
                            Zobrazit drobečkovou navigaci
                        </label>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$isBlockPage): ?>
            <div class="row mb-4">
                <div class="col-md-12">
                    <label class="form-label">Obsah</label>
                    <textarea name="content"
                              class="form-control editor"
                              rows="12"><?= htmlspecialchars($_POST['content'] ?? '') ?></textarea>
                </div>
            </div>
        <?php endif; ?>

        <div class="row mb-3">
            <div class="col-md-8">
                <label class="form-label">SEO titulek</label>
                <input type="text"
                       name="meta_title"
                       class="form-control"
                       value="<?= htmlspecialchars($_POST['meta_title'] ?? '') ?>">
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-8">
                <label class="form-label">SEO popis</label>
                <input type="text"
                       name="meta_description"
                       class="form-control"
                       value="<?= htmlspecialchars($_POST['meta_description'] ?? '') ?>">
            </div>
        </div>

        <div class="d-flex justify-content-between">
            <button class="btn btn-success">
                <i class="bi bi-save me-1"></i>
                <?= $isBlockPage ? 'Uložit a pokračovat na bloky' : 'Uložit' ?>
            </button>

            <a href="list.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i> Zpět
            </a>
        </div>

    </form>

</div>

<script>
document.getElementById('parent_id')?.addEventListener('change', async function() {
    const pid = this.value || 0;

    try {
        const r = await fetch('next_order.php?parent_id=' + encodeURIComponent(pid));
        const j = await r.json();

        if (j.ok && typeof j.next === 'number') {
            document.getElementById('menu_order').value = j.next;
        }
    } catch(e) {}
});
</script>

<?php include "../includes/footer.php"; ?>