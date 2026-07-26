<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('workplaces');

$workplacePageId = intval($_GET['id'] ?? 0);

if ($workplacePageId <= 0) {
    header("Location: list.php");
    exit;
}

/*
 * Načtení stránky a pracoviště
 */
$stmt = $conn->prepare("
    SELECT
        wp.id AS workplace_page_id,
        wp.workplace_id,
        wp.page_id,
        wp.parent_id,
        wp.show_in_menu,
        wp.sort_order,
        wp.is_active,

        p.title,
        p.slug,
        p.meta_title,
        p.meta_description,
        p.show_breadcrumbs,
        p.status,
        p.template,

        w.name AS workplace_name,
        w.slug AS workplace_slug,
        w.main_page_id

    FROM workplace_pages wp

    INNER JOIN pages p
        ON p.id = wp.page_id

    INNER JOIN workplaces w
        ON w.id = wp.workplace_id

    WHERE wp.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $workplacePageId);
$stmt->execute();
$page = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$page) {
    header("Location: list.php");
    exit;
}

$workplaceId = (int)$page['workplace_id'];
$pageId = (int)$page['page_id'];
$mainPageId = (int)($page['main_page_id'] ?? 0);
$isMainPage = $pageId === $mainPageId;

$errors = [];

$title = $page['title'] ?? '';
$storedPageSlug = $page['slug'] ?? '';

$slugPrefix = 'pracoviste-' . slugify($page['workplace_slug']) . '-';

if ($isMainPage) {
    $slug = $page['workplace_slug'] ?? '';
} elseif (
    $slugPrefix !== ''
    && str_starts_with($storedPageSlug, $slugPrefix)
) {
    $slug = substr($storedPageSlug, strlen($slugPrefix));
} else {
    $slug = $storedPageSlug;
}

$metaTitle = $page['meta_title'] ?? '';
$metaDescription = $page['meta_description'] ?? '';

$template = $page['template'] ?? 'universal';
$status = $page['status'] ?? 'draft';

$showInMenu = (int)($page['show_in_menu'] ?? 1);
$showBreadcrumbs = (int)($page['show_breadcrumbs'] ?? 1);
$isActive = (int)($page['is_active'] ?? 1);
$sortOrder = (int)($page['sort_order'] ?? 10);

$templateOptions = [
    'universal' => 'Univerzální stránka',
    'default' => 'Obecná stránka',
    'articles' => 'Výpis článků',
    'gallery' => 'Fotogalerie',
    'contact' => 'Kontaktní formulář',
];

/*
 * Uložení
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $metaTitle = trim($_POST['meta_title'] ?? '');
    $metaDescription = trim($_POST['meta_description'] ?? '');

    $template = trim($_POST['template'] ?? 'universal');
    $status = trim($_POST['status'] ?? 'draft');

    $showInMenu = isset($_POST['show_in_menu']) ? 1 : 0;
    $showBreadcrumbs = isset($_POST['show_breadcrumbs']) ? 1 : 0;
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $sortOrder = intval($_POST['sort_order'] ?? 10);

    if ($slug === '' && $title !== '') {
        $slug = slugify($title);
    } else {
        $slug = slugify($slug);
    }

    if (!array_key_exists($template, $templateOptions)) {
        $template = 'universal';
    }

    if (!in_array($status, ['draft', 'published'], true)) {
        $status = 'draft';
    }

    if ($sortOrder <= 0) {
        $sortOrder = 10;
    }

    if ($title === '') {
        $errors[] = 'Vyplňte název stránky.';
    }

    if ($slug === '') {
        $errors[] = 'Nepodařilo se vytvořit platný slug.';
    }

    /*
     * Hlavní stránka používá interní slug:
     * pracoviste-slug-pracoviste
     *
     * Podstránka:
     * pracoviste-slug-pracoviste-slug-stranky
     */
    if ($isMainPage) {
        $pageSlug = 'pracoviste-' . slugify($page['workplace_slug']);
    } else {
        $pageSlug = 'pracoviste-'
            . slugify($page['workplace_slug'])
            . '-'
            . $slug;
    }

    /*
     * Kontrola duplicity veřejného slugu v daném pracovišti.
     */
    if (!$errors && !$isMainPage) {
        $stmt = $conn->prepare("
            SELECT wp.id
            FROM workplace_pages wp
            INNER JOIN pages p
                ON p.id = wp.page_id
            WHERE wp.workplace_id = ?
              AND wp.id != ?
              AND p.slug = ?
            LIMIT 1
        ");
        $stmt->bind_param(
            "iis",
            $workplaceId,
            $workplacePageId,
            $pageSlug
        );
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($exists) {
            $errors[] = 'Jiná stránka tohoto pracoviště už používá stejný slug.';
        }
    }

    /*
     * Globální kontrola pages.slug.
     */
    if (!$errors) {
        $stmt = $conn->prepare("
            SELECT id
            FROM pages
            WHERE slug = ?
              AND id != ?
            LIMIT 1
        ");
        $stmt->bind_param("si", $pageSlug, $pageId);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($exists) {
            $errors[] = 'Tento interní slug už používá jiná stránka.';
        }
    }

    if (!$errors) {
        $publishedAt = $status === 'published'
            ? date('Y-m-d H:i:s')
            : null;

        $conn->begin_transaction();

        try {
            /*
             * 1. Aktualizace běžné stránky
             */
            $stmt = $conn->prepare("
                UPDATE pages
                SET title = ?,
                    slug = ?,
                    meta_title = ?,
                    meta_description = ?,
                    show_breadcrumbs = ?,
                    status = ?,
                    template = ?,
                    published_at = ?
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->bind_param(
                "ssssisssi",
                $title,
                $pageSlug,
                $metaTitle,
                $metaDescription,
                $showBreadcrumbs,
                $status,
                $template,
                $publishedAt,
                $pageId
            );

            $stmt->execute();
            $stmt->close();

            /*
             * 2. Aktualizace navigace pracoviště
             */
            $stmt = $conn->prepare("
                UPDATE workplace_pages
                SET show_in_menu = ?,
                    sort_order = ?,
                    is_active = ?,
                    updated_at = NOW()
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->bind_param(
                "iiii",
                $showInMenu,
                $sortOrder,
                $isActive,
                $workplacePageId
            );

            $stmt->execute();
            $stmt->close();

            /*
             * Hlavní stránka synchronizuje název a SEO pracoviště.
             */
            if ($isMainPage) {
                $stmt = $conn->prepare("
                    UPDATE workplaces
                    SET name = ?,
                        meta_title = ?,
                        meta_description = ?,
                        updated_at = NOW()
                    WHERE id = ?
                    LIMIT 1
                ");

                $stmt->bind_param(
                    "sssi",
                    $title,
                    $metaTitle,
                    $metaDescription,
                    $workplaceId
                );

                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();

            logAction(
                "Upravena stránka '$title' pracoviště '{$page['workplace_name']}'"
            );

            header(
                "Location: pages.php?id="
                . $workplaceId
                . "&updated=1"
            );
            exit;

        } catch (Throwable $e) {
            $conn->rollback();

            error_log(
                'Workplace page update error: ' . $e->getMessage()
            );

            $errors[] = 'Stránku se nepodařilo uložit: '
                . $e->getMessage();
        }
    }
}

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="admin-page-header-content mb-4">

        <div>
            <h2 class="mb-1">Upravit stránku pracoviště</h2>

            <p class="text-muted mb-0">
                <?= e($page['workplace_name']) ?>
                —
                <?= e($page['title']) ?>
            </p>
        </div>

        <div class="d-flex flex-wrap gap-2">

            <a
                href="../pages/blocks.php?page_id=<?= $pageId ?>"
                class="btn btn-outline-primary">

                <i class="bi bi-grid me-1"></i>
                Správa bloků
            </a>

            <a
                href="pages.php?id=<?= $workplaceId ?>"
                class="btn btn-outline-secondary">

                <i class="bi bi-arrow-left me-1"></i>
                Zpět na stránky
            </a>

        </div>

    </div>

    <?php if ($isMainPage): ?>
        <div class="alert alert-info">
            Toto je hlavní stránka pracoviště. Její název a SEO údaje
            se synchronizují se základními údaji pracoviště.
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <div><?= e($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">

        <div class="card-body">

            <form method="post">

                <div class="row g-4">

                    <div class="col-lg-8">

                        <div class="mb-3">
                            <label class="form-label">
                                Název stránky *
                            </label>

                            <input
                                type="text"
                                name="title"
                                class="form-control"
                                required
                                value="<?= e($title) ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                Slug
                            </label>

                            <input
                                type="text"
                                name="slug"
                                class="form-control"
                                value="<?= e($slug) ?>"
                                <?= $isMainPage ? 'readonly' : '' ?>>

                            <?php if ($isMainPage): ?>
                                <div class="form-text">
                                    Slug hlavní stránky se řídí slugem pracoviště.
                                </div>
                            <?php else: ?>
                                <div class="form-text">
                                    Veřejná URL bude například:
                                    <code>
                                        /kliniky-a-pracoviste/<?= e($page['workplace_slug']) ?>/<?= e($slug ?: 'nazev-stranky') ?>
                                    </code>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                Typ stránky
                            </label>

                            <select
                                name="template"
                                class="form-select">

                                <?php foreach ($templateOptions as $value => $label): ?>
                                    <option
                                        value="<?= e($value) ?>"
                                        <?= $template === $value
                                            ? 'selected'
                                            : '' ?>>

                                        <?= e($label) ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>
                        </div>

                        <hr class="my-4">

                        <div class="mb-3">
                            <label class="form-label">
                                Meta title
                            </label>

                            <input
                                type="text"
                                name="meta_title"
                                class="form-control"
                                value="<?= e($metaTitle) ?>">
                        </div>

                        <div class="mb-0">
                            <label class="form-label">
                                Meta description
                            </label>

                            <textarea
                                name="meta_description"
                                class="form-control"
                                rows="4"><?= e($metaDescription) ?></textarea>
                        </div>

                    </div>

                    <div class="col-lg-4">

                        <div class="card bg-light border-0">

                            <div class="card-body">

                                <h5 class="mb-3">
                                    Nastavení
                                </h5>

                                <div class="mb-3">
                                    <label class="form-label">
                                        Stav
                                    </label>

                                    <select
                                        name="status"
                                        class="form-select">

                                        <option
                                            value="draft"
                                            <?= $status === 'draft'
                                                ? 'selected'
                                                : '' ?>>

                                            Koncept
                                        </option>

                                        <option
                                            value="published"
                                            <?= $status === 'published'
                                                ? 'selected'
                                                : '' ?>>

                                            Publikováno
                                        </option>

                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">
                                        Pořadí
                                    </label>

                                    <input
                                        type="number"
                                        name="sort_order"
                                        class="form-control"
                                        value="<?= (int)$sortOrder ?>">
                                </div>

                                <div class="form-check form-switch mb-3">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="show_in_menu"
                                        id="show_in_menu"
                                        value="1"
                                        <?= $showInMenu ? 'checked' : '' ?>>

                                    <label
                                        class="form-check-label"
                                        for="show_in_menu">

                                        Zobrazit v navigaci pracoviště
                                    </label>
                                </div>

                                <div class="form-check form-switch mb-3">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="show_breadcrumbs"
                                        id="show_breadcrumbs"
                                        value="1"
                                        <?= $showBreadcrumbs ? 'checked' : '' ?>>

                                    <label
                                        class="form-check-label"
                                        for="show_breadcrumbs">

                                        Zobrazit drobečkovou navigaci
                                    </label>
                                </div>

                                <div class="form-check form-switch mb-0">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="is_active"
                                        id="is_active"
                                        value="1"
                                        <?= $isActive ? 'checked' : '' ?>>

                                    <label
                                        class="form-check-label"
                                        for="is_active">

                                        Aktivní
                                    </label>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <hr class="my-4">

                <button
                    type="submit"
                    class="btn btn-success">

                    <i class="bi bi-save me-1"></i>
                    Uložit změny
                </button>

                <a
                    href="../pages/blocks.php?page_id=<?= $pageId ?>"
                    class="btn btn-outline-primary">

                    Správa bloků
                </a>

                <a
                    href="pages.php?id=<?= $workplaceId ?>"
                    class="btn btn-outline-secondary">

                    Zrušit
                </a>

            </form>

        </div>

    </div>

</div>

<?php include "../includes/footer.php"; ?>