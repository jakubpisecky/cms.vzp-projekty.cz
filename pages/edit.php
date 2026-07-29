<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('pages');

$msg = "";

// CSRF
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
            http_response_code(400);
            exit("Neplatný CSRF token.");
        }
    }
}

csrf_check();

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: list.php");
    exit;
}

$s = $conn->prepare("SELECT * FROM pages WHERE id = ? LIMIT 1");
$s->bind_param("i", $id);
$s->execute();
$page = $s->get_result()->fetch_assoc();
$s->close();

if (!$page) {
    http_response_code(404);
    exit("Stránka nenalezena.");
}

// typy stránek
$pageTemplatesLocal = [
    'home' => 'Homepage',
    'articles' => 'Výpis článků',
    'page' => 'Obecná stránka',
    'gallery' => 'Fotogalerie',
    'contact' => 'Kontaktní formulář',
    'universal' => 'Univerzální stránka',
    'workplaces' => 'Výpis pracovišť',
    'workplace_team' => 'Výpis týmu'
];

$currentTpl = $page['template'] ?? 'page';

if (!isset($pageTemplatesLocal[$currentTpl])) {
    $currentTpl = 'page';
}

$isBlockPage = in_array($currentTpl, ['home', 'universal'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $parent_id     = intval($_POST['parent_id'] ?? 0);
    $title         = trim($_POST['title'] ?? '');
    $slug          = trim($_POST['slug'] ?? '');
    $meta_title    = trim($_POST['meta_title'] ?? '');
    $meta_desc     = trim($_POST['meta_description'] ?? '');
    $show_in_menu  = isset($_POST['show_in_menu']) ? 1 : 0;
    $show_breadcrumbs = isset($_POST['show_breadcrumbs']) ? 1 : 0;
    $menu_order    = intval($_POST['menu_order'] ?? 0);
    $status        = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $template      = trim($_POST['template'] ?? 'page');

    if (!isset($pageTemplatesLocal[$template])) {
        $template = 'page';
    }

    $isBlockPagePost = in_array($template, ['home', 'universal'], true);
    $content = $isBlockPagePost ? '' : ($_POST['content'] ?? '');

    if ($template === 'homepage') {
        $checkHome = $conn->prepare("
            SELECT id
            FROM pages
            WHERE template = 'homepage'
              AND id != ?
            LIMIT 1
        ");
        $checkHome->bind_param("i", $id);
        $checkHome->execute();
        $existsHome = $checkHome->get_result()->fetch_assoc();
        $checkHome->close();

        if ($existsHome) {
            $msg = "Homepage už existuje. Není možné mít dvě homepage.";
        }
    }

    if ($title === '') {
        $msg = "Titulek je povinný.";
    }

    if ($msg === '') {

        if ($slug === '') {
            if (function_exists('slugify')) {
                $slug = slugify($title);
            } else {
                $slug = iconv('UTF-8', 'ASCII//TRANSLIT', $title);
                $slug = strtolower(preg_replace('~[^a-z0-9]+~', '-', $slug));
                $slug = trim($slug, '-') ?: 'stranka';
            }
        }

        if ($parent_id === $id) {
            $parent_id = 0;
        }

        $chk = $conn->prepare("
            SELECT id
            FROM pages
            WHERE parent_id = ?
              AND slug = ?
              AND id <> ?
            LIMIT 1
        ");
        $chk->bind_param("isi", $parent_id, $slug, $id);
        $chk->execute();
        $chk->store_result();

        if ($chk->num_rows > 0) {
            $slug .= '-' . time();
        }

        $chk->close();

        $u = $conn->prepare("
            UPDATE pages
            SET parent_id = ?,
                title = ?,
                slug = ?,
                content = ?,
                meta_title = ?,
                meta_description = ?,
                show_in_menu = ?,
                show_breadcrumbs = ?,
                menu_order = ?,
                status = ?,
                template = ?,
                published_at = IF(? = 'published' AND published_at IS NULL, NOW(), published_at)
            WHERE id = ?
        ");

        $u->bind_param(
            "issssssiisssi",
            $parent_id,
            $title,
            $slug,
            $content,
            $meta_title,
            $meta_desc,
            $show_in_menu,
            $show_breadcrumbs,
            $menu_order,
            $status,
            $template,
            $status,
            $id
        );

        $u->execute();
        $u->close();

        logAction("Upravena stránka '$title'");

        if ($isBlockPagePost) {
            header("Location: blocks.php?page_id=" . $id . "&updated=1");
            exit;
        }

        header("Location: list.php?updated=1");
        exit;
    }

    $page['parent_id'] = $parent_id;
    $page['title'] = $title;
    $page['slug'] = $slug;
    $page['content'] = $content;
    $page['meta_title'] = $meta_title;
    $page['meta_description'] = $meta_desc;
    $page['show_in_menu'] = $show_in_menu;
    $page['show_breadcrumbs'] = $show_breadcrumbs;
    $page['menu_order'] = $menu_order;
    $page['status'] = $status;
    $page['template'] = $template;

    $currentTpl = $template;
    $isBlockPage = in_array($currentTpl, ['homepage', 'universal'], true);
}

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="row justify-content-center">
        <div class="col-md-12">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Upravit stránku</h2>
                    <p class="text-muted mb-0">
                        Typ stránky:
                        <strong><?= htmlspecialchars($pageTemplatesLocal[$currentTpl] ?? 'Obecná stránka') ?></strong>
                    </p>
                </div>

                <?php if ($isBlockPage): ?>
                    <a href="blocks.php?page_id=<?= (int)$page['id'] ?>" class="btn btn-primary">
                        <i class="bi bi-layout-three-columns me-1"></i>
                        Správa bloků
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($msg): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <?php if ($isBlockPage): ?>
                <div class="alert alert-info">
                    Tato stránka se skládá z bloků. Obsah stránky upravíte přes tlačítko
                    <strong>Správa bloků</strong>.
                </div>
            <?php endif; ?>

            <form method="post" class="bg-white p-4 rounded shadow-sm">

                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">

                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label">Titulek</label>
                        <input type="text"
                               name="title"
                               class="form-control"
                               value="<?= htmlspecialchars($page['title'] ?? '') ?>"
                               required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label">Nadřazená stránka</label>
                        <select name="parent_id" class="form-select">
                            <option value="0" <?= (int)$page['parent_id'] === 0 ? 'selected' : '' ?>>
                                — První úroveň —
                            </option>

                            <?php
                            $r = $conn->query("SELECT id, title FROM pages WHERE id <> " . (int)$id . " ORDER BY title ASC");
                            while ($p = $r->fetch_assoc()):
                            ?>
                                <option value="<?= (int)$p['id'] ?>"
                                    <?= (int)$page['parent_id'] === (int)$p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['title']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label">URL (slug)</label>
                        <input type="text"
                               name="slug"
                               class="form-control"
                               value="<?= htmlspecialchars($page['slug'] ?? '') ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label">Stav</label>
                        <select name="status" class="form-select">
                            <option value="draft" <?= ($page['status'] ?? '') === 'draft' ? 'selected' : '' ?>>
                                Koncept
                            </option>
                            <option value="published" <?= ($page['status'] ?? '') === 'published' ? 'selected' : '' ?>>
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
                               class="form-control"
                               value="<?= (int)$page['menu_order'] ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-8">
                        <div class="form-check form-switch">
                            <input class="form-check-input"
                                   type="checkbox"
                                   name="show_in_menu"
                                   id="show_in_menu"
                                   <?= !empty($page['show_in_menu']) ? 'checked' : '' ?>>

                            <label class="form-check-label" for="show_in_menu">
                                Zobrazit v menu
                            </label>
                        </div>
                    </div>
                </div>
                <?php if ($currentTpl === 'universal'): ?>
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <div class="form-check form-switch">
                                <input class="form-check-input"
                                    type="checkbox"
                                    name="show_breadcrumbs"
                                    id="show_breadcrumbs"
                                    value="1"
                                    <?= !empty($page['show_breadcrumbs']) ? 'checked' : '' ?>>

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
                                      rows="12"><?= htmlspecialchars($page['content'] ?? '') ?></textarea>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label">SEO titulek</label>
                        <input type="text"
                               name="meta_title"
                               class="form-control"
                               value="<?= htmlspecialchars($page['meta_title'] ?? '') ?>">
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-8">
                        <label class="form-label">SEO popis</label>
                        <input type="text"
                               name="meta_description"
                               class="form-control"
                               value="<?= htmlspecialchars($page['meta_description'] ?? '') ?>">
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-8">
                        <label class="form-label">Šablona</label>

                        <select name="template" class="form-select">
                            <?php foreach ($pageTemplatesLocal as $value => $label): ?>

                                <?php
                                if (
                                    $value === 'homepage'
                                    && $currentTpl !== 'homepage'
                                ) {
                                    $checkHome = $conn->query("
                                        SELECT id
                                        FROM pages
                                        WHERE template = 'homepage'
                                        LIMIT 1
                                    ");

                                    if ($checkHome && $checkHome->num_rows > 0) {
                                        continue;
                                    }
                                }
                                ?>

                                <option value="<?= htmlspecialchars($value) ?>"
                                    <?= $currentTpl === $value ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>

                            <?php endforeach; ?>
                        </select>

                        <?php if ($currentTpl === 'homepage'): ?>
                            <div class="form-text">
                                Toto je aktuální homepage webu.
                            </div>
                        <?php endif; ?>

                    </div>
                </div>

                <div class="d-flex justify-content-between">

                    <div class="d-flex gap-2">
                        <button class="btn btn-success">
                            <i class="bi bi-save me-1"></i>
                            Uložit změny
                        </button>

                        <?php if ($isBlockPage): ?>
                            <a href="blocks.php?page_id=<?= (int)$page['id'] ?>" class="btn btn-primary">
                                <i class="bi bi-layout-three-columns me-1"></i>
                                Správa bloků
                            </a>
                        <?php endif; ?>
                    </div>

                    <a href="list.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i>
                        Zpět
                    </a>

                </div>

            </form>

        </div>
    </div>

</div>

<?php include "../includes/footer.php"; ?>