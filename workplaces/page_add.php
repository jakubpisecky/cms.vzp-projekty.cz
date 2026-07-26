<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('workplaces');

$workplaceId = intval($_GET['id'] ?? 0);

if ($workplaceId <= 0) {
    header("Location: list.php");
    exit;
}

/*
 * Načtení pracoviště
 */
$stmt = $conn->prepare("
    SELECT
        id,
        name,
        slug,
        main_page_id
    FROM workplaces
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $workplaceId);
$stmt->execute();

$workplace = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$workplace) {
    header("Location: list.php");
    exit;
}

/*
 * Výchozí hodnoty
 */
$errors = [];

$title = '';
$slug = '';
$metaTitle = '';
$metaDescription = '';

$template = 'universal';
$status = 'draft';

$showInMenu = 1;
$showBreadcrumbs = 1;
$isActive = 1;

$templateOptions = [
    'universal' => 'Univerzální stránka',
    'default' => 'Obecná stránka',
    'articles' => 'Výpis článků',
    'gallery' => 'Fotogalerie',
    'contact' => 'Kontaktní formulář',
];

/*
 * Uložení stránky
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');

    $metaTitle = trim($_POST['meta_title'] ?? '');
    $metaDescription = trim(
        $_POST['meta_description'] ?? ''
    );

    $template = trim(
        $_POST['template'] ?? 'universal'
    );

    $status = trim(
        $_POST['status'] ?? 'draft'
    );

    $showInMenu = isset($_POST['show_in_menu'])
        ? 1
        : 0;

    $showBreadcrumbs = isset($_POST['show_breadcrumbs'])
        ? 1
        : 0;

    $isActive = isset($_POST['is_active'])
        ? 1
        : 0;

    /*
     * Slug
     */
    if ($slug === '' && $title !== '') {
        $slug = slugify($title);
    } else {
        $slug = slugify($slug);
    }

    /*
     * Povolený typ stránky
     */
    if (!array_key_exists($template, $templateOptions)) {
        $template = 'universal';
    }

    /*
     * Povolený stav
     */
    if (!in_array(
        $status,
        ['draft', 'published'],
        true
    )) {
        $status = 'draft';
    }

    /*
     * Validace
     */
    if ($title === '') {
        $errors[] = 'Vyplňte název stránky.';
    }

    if ($slug === '') {
        $errors[] =
            'Nepodařilo se vytvořit platný slug.';
    }

    /*
     * Interní slug stránky.
     *
     * Veřejný router později sestaví například:
     *
     * /kliniky-a-pracoviste/slug-pracoviste/slug-stranky
     */
    $pageSlug = 'pracoviste-'
        . slugify($workplace['slug'])
        . '-'
        . $slug;

    /*
     * Kontrola duplicity stránky
     * v rámci stejného pracoviště.
     */
    if (!$errors) {
        $stmt = $conn->prepare("
            SELECT p.id
            FROM pages p
            WHERE p.owner_type = 'workplace'
              AND p.owner_id = ?
              AND p.slug = ?
            LIMIT 1
        ");
        $stmt->bind_param(
            "is",
            $workplaceId,
            $pageSlug
        );
        $stmt->execute();

        $existsInWorkplace = $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();

        if ($existsInWorkplace) {
            $errors[] =
                'Stránka s tímto slugem už '
                . 'v pracovišti existuje.';
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
            LIMIT 1
        ");
        $stmt->bind_param(
            "s",
            $pageSlug
        );
        $stmt->execute();

        $existsGlobally = $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();

        if ($existsGlobally) {
            $errors[] =
                'Interní slug stránky už používá jiná stránka.';
        }
    }

    /*
     * Uložení
     */
    if (!$errors) {

        /*
         * Technické hodnoty běžné stránky.
         */
        $parentId = 0;
        $ownerType = 'workplace';
        $ownerId = $workplaceId;

        $content = null;

        /*
         * Stránka pracoviště se nesmí objevit
         * v hlavní navigaci běžných stránek.
         *
         * Její vlastní navigaci řeší workplace_pages.
         */
        $pageShowInMenu = 0;
        $menuOrder = 0;

        $publishedAt = $status === 'published'
            ? date('Y-m-d H:i:s')
            : null;

        /*
         * Další pořadí v navigaci pracoviště.
         */
        $stmt = $conn->prepare("
            SELECT
                COALESCE(MAX(sort_order), 0) + 10
                    AS next_order
            FROM workplace_pages
            WHERE workplace_id = ?
        ");
        $stmt->bind_param(
            "i",
            $workplaceId
        );
        $stmt->execute();

        $orderRow = $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();

        $sortOrder = (int)(
            $orderRow['next_order'] ?? 10
        );

        if ($sortOrder <= 0) {
            $sortOrder = 10;
        }

        $conn->begin_transaction();

        try {
            /*
             * 1. Vytvoření stránky.
             */
            $stmt = $conn->prepare("
                INSERT INTO pages
                (
                    parent_id,
                    owner_type,
                    owner_id,
                    title,
                    slug,
                    content,
                    meta_title,
                    meta_description,
                    show_in_menu,
                    show_breadcrumbs,
                    menu_order,
                    status,
                    template,
                    published_at
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            /*
             * Typy parametrů:
             *
             * i parent_id
             * s owner_type
             * i owner_id
             * s title
             * s slug
             * s content
             * s meta_title
             * s meta_description
             * i show_in_menu
             * i show_breadcrumbs
             * i menu_order
             * s status
             * s template
             * s published_at
             */
            $stmt->bind_param(
                "isisssssiiisss",
                $parentId,
                $ownerType,
                $ownerId,
                $title,
                $pageSlug,
                $content,
                $metaTitle,
                $metaDescription,
                $pageShowInMenu,
                $showBreadcrumbs,
                $menuOrder,
                $status,
                $template,
                $publishedAt
            );

            $stmt->execute();

            $pageId = (int)$stmt->insert_id;

            $stmt->close();

            if ($pageId <= 0) {
                throw new RuntimeException(
                    'Nepodařilo se vytvořit stránku.'
                );
            }

            /*
             * 2. Propojení stránky s pracovištěm.
             */
            $workplaceParentId = null;

            $stmt = $conn->prepare("
                INSERT INTO workplace_pages
                (
                    workplace_id,
                    page_id,
                    parent_id,
                    show_in_menu,
                    sort_order,
                    is_active
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            $stmt->bind_param(
                "iiiiii",
                $workplaceId,
                $pageId,
                $workplaceParentId,
                $showInMenu,
                $sortOrder,
                $isActive
            );

            $stmt->execute();
            $stmt->close();

            $conn->commit();

            logAction(
                "Vytvořena stránka '$title' "
                . "pracoviště '{$workplace['name']}'"
            );

            /*
             * Administrátor zůstane uvnitř
             * modulu Pracoviště.
             *
             * Až vytvoříme vlastní page_blocks.php,
             * přesměrujeme ho po uložení rovnou tam.
             */
            header(
                "Location: pages.php?id="
                . $workplaceId
                . "&created=1"
            );
            exit;

        } catch (Throwable $e) {
            $conn->rollback();

            error_log(
                'Workplace page create error: '
                . $e->getMessage()
            );

            $errors[] =
                'Stránku se nepodařilo vytvořit: '
                . $e->getMessage();
        }
    }
}

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="admin-page-header-content mb-4">

        <div>

            <h2 class="mb-1">
                Přidat stránku pracoviště
            </h2>

            <p class="text-muted mb-0">
                <?= e($workplace['name']) ?>
            </p>

        </div>

        <a
            href="pages.php?id=<?= $workplaceId ?>"
            class="btn btn-outline-secondary">

            <i class="bi bi-arrow-left me-1"></i>
            Zpět na stránky
        </a>

    </div>

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
                                value="<?= e($slug) ?>">

                            <div class="form-text">

                                Veřejná URL bude například:

                                <code>
                                    /kliniky-a-pracoviste/<?= e(
                                        $workplace['slug']
                                    ) ?>/nazev-stranky
                                </code>

                            </div>

                        </div>

                        <div class="mb-3">

                            <label class="form-label">
                                Typ stránky
                            </label>

                            <select
                                name="template"
                                class="form-select">

                                <?php foreach (
                                    $templateOptions
                                    as $value => $label
                                ): ?>

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
                                rows="4"><?= e(
                                    $metaDescription
                                ) ?></textarea>

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

                                <div class="form-check form-switch mb-3">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="show_in_menu"
                                        id="show_in_menu"
                                        value="1"
                                        <?= $showInMenu
                                            ? 'checked'
                                            : '' ?>>

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
                                        <?= $showBreadcrumbs
                                            ? 'checked'
                                            : '' ?>>

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
                                        <?= $isActive
                                            ? 'checked'
                                            : '' ?>>

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
                    Uložit stránku
                </button>

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