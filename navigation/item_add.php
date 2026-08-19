<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('pages');

$navigationId = (int)($_GET['navigation_id'] ?? 0);

if ($navigationId <= 0) {
    header("Location: list.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT id, name, is_active
    FROM navigation
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $navigationId);
$stmt->execute();

$navigation = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$navigation) {
    header("Location: list.php");
    exit;
}

/*
 * Dostupné stránky.
 */
$pages = [];

$result = $conn->query("
    SELECT
        id,
        title,
        slug,
        parent_id,
        status
    FROM pages
    WHERE owner_type = 'page'
    ORDER BY title ASC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $pages[] = $row;
    }
}

/*
 * Dostupné rodičovské položky této navigace.
 *
 * Zatím povolujeme maximálně 2 úrovně:
 * rodič → dítě.
 */
$parentItems = [];

$stmt = $conn->prepare("
    SELECT
        id,
        title
    FROM navigation_items
    WHERE navigation_id = ?
      AND parent_id IS NULL
    ORDER BY sort_order ASC, id ASC
");
$stmt->bind_param("i", $navigationId);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $parentItems[] = $row;
}

$stmt->close();

$errors = [];

$title      = '';
$linkType   = 'page';
$pageId     = 0;
$url        = '';
$target     = '_self';
$parentId   = 0;
$isActive   = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title'] ?? '');

    $linkType = $_POST['link_type'] ?? 'page';

    $pageId = (int)($_POST['page_id'] ?? 0);

    $url = trim($_POST['url'] ?? '');

    $target = $_POST['target'] ?? '_self';

    $parentId = (int)($_POST['parent_id'] ?? 0);

    $isActive = isset($_POST['is_active']) ? 1 : 0;

    /*
     * Název položky.
     */
    if ($title === '') {
        $errors[] = 'Název položky je povinný.';
    }

    /*
     * Typ odkazu.
     */
    if (!in_array(
        $linkType,
        ['page', 'url'],
        true
    )) {
        $errors[] = 'Neplatný typ odkazu.';
    }

    /*
     * Interní stránka.
     */
    if ($linkType === 'page') {

        if ($pageId <= 0) {
            $errors[] = 'Vyberte cílovou stránku.';
        } else {

            $stmt = $conn->prepare("
                SELECT id
                FROM pages
                WHERE id = ?
                  AND owner_type = 'page'
                LIMIT 1
            ");

            $stmt->bind_param(
                "i",
                $pageId
            );

            $stmt->execute();

            $pageExists = $stmt
                ->get_result()
                ->fetch_assoc();

            $stmt->close();

            if (!$pageExists) {
                $errors[] = 'Vybraná stránka neexistuje.';
            }
        }

        /*
         * U interní stránky se URL neukládá.
         */
        $url = '';
    }

    /*
     * Externí URL.
     */
    if ($linkType === 'url') {

        $pageId = 0;

        if ($url === '') {
            $errors[] = 'Zadejte externí URL adresu.';
        } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
            $errors[] = 'Externí URL adresa není platná.';
        }
    }

    /*
     * Target.
     */
    if (!in_array(
        $target,
        ['_self', '_blank'],
        true
    )) {
        $target = '_self';
    }

    /*
     * Kontrola rodičovské položky.
     */
    if ($parentId > 0) {

        $stmt = $conn->prepare("
            SELECT id
            FROM navigation_items
            WHERE id = ?
              AND navigation_id = ?
              AND parent_id IS NULL
            LIMIT 1
        ");

        $stmt->bind_param(
            "ii",
            $parentId,
            $navigationId
        );

        $stmt->execute();

        $parentExists = $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();

        if (!$parentExists) {
            $errors[] = 'Vybraná nadřazená položka neexistuje.';
        }
    }

    /*
     * Automatické pořadí.
     *
     * Pořadí se počítá v rámci stejného parent_id.
     */
    $sortOrder = 10;

    if (!$errors) {

        if ($parentId > 0) {

            $stmt = $conn->prepare("
                SELECT
                    COALESCE(MAX(sort_order), 0) + 10 AS next_order
                FROM navigation_items
                WHERE navigation_id = ?
                  AND parent_id = ?
            ");

            $stmt->bind_param(
                "ii",
                $navigationId,
                $parentId
            );

        } else {

            $stmt = $conn->prepare("
                SELECT
                    COALESCE(MAX(sort_order), 0) + 10 AS next_order
                FROM navigation_items
                WHERE navigation_id = ?
                  AND parent_id IS NULL
            ");

            $stmt->bind_param(
                "i",
                $navigationId
            );
        }

        $stmt->execute();

        $row = $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();

        $sortOrder = (int)(
            $row['next_order']
            ?? 10
        );

        if ($sortOrder <= 0) {
            $sortOrder = 10;
        }
    }

    /*
     * Uložení.
     */
    if (!$errors) {

        /*
         * NULL hodnoty pro DB.
         */
        $dbPageId = $linkType === 'page'
            ? $pageId
            : null;

        $dbUrl = $linkType === 'url'
            ? $url
            : null;

        $dbParentId = $parentId > 0
            ? $parentId
            : null;

        $stmt = $conn->prepare("
            INSERT INTO navigation_items
            (
                navigation_id,
                parent_id,
                title,
                page_id,
                url,
                target,
                sort_order,
                is_active
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?, ?, ?
            )
        ");

        $stmt->bind_param(
            "iisissii",
            $navigationId,
            $dbParentId,
            $title,
            $dbPageId,
            $dbUrl,
            $target,
            $sortOrder,
            $isActive
        );

        if ($stmt->execute()) {

            $stmt->close();

            logAction(
                "Přidána položka '$title' do navigace '{$navigation['name']}'"
            );

            header(
                "Location: items.php?id="
                . $navigationId
                . "&created=1"
            );

            exit;
        }

        $errors[] = 'Položku navigace se nepodařilo uložit.';

        $stmt->close();
    }
}

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="row justify-content-center">

        <div class="col-md-12">

            <div class="d-flex justify-content-between align-items-center mb-4">

                <div>

                    <h2 class="mb-1">
                        Přidat položku navigace
                    </h2>

                    <p class="text-muted mb-0">
                        <?= e($navigation['name']) ?>
                    </p>

                </div>

                <a
                    href="items.php?id=<?= $navigationId ?>"
                    class="btn btn-outline-secondary"
                >
                    <i class="fa fa-arrow-left me-1"></i>
                    Zpět
                </a>

            </div>

            <?php if ($errors): ?>

                <div class="alert alert-danger">

                    <?php foreach ($errors as $error): ?>

                        <div>
                            <?= e($error) ?>
                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

            <form
                method="post"
                class="bg-white p-4 rounded shadow-sm"
            >

                <div class="row mb-4">

                    <div class="col-md-8">

                        <label
                            for="title"
                            class="form-label"
                        >
                            Název položky *
                        </label>

                        <input
                            type="text"
                            name="title"
                            id="title"
                            class="form-control"
                            required
                            value="<?= e($title) ?>"
                        >

                    </div>

                </div>

                <div class="row mb-4">

                    <div class="col-md-8">

                        <label class="form-label">
                            Typ odkazu
                        </label>

                        <div class="d-flex flex-wrap gap-4">

                            <div class="form-check">

                                <input
                                    class="form-check-input"
                                    type="radio"
                                    name="link_type"
                                    id="link_type_page"
                                    value="page"
                                    <?= $linkType === 'page' ? 'checked' : '' ?>
                                >

                                <label
                                    class="form-check-label"
                                    for="link_type_page"
                                >
                                    Interní stránka
                                </label>

                            </div>

                            <div class="form-check">

                                <input
                                    class="form-check-input"
                                    type="radio"
                                    name="link_type"
                                    id="link_type_url"
                                    value="url"
                                    <?= $linkType === 'url' ? 'checked' : '' ?>
                                >

                                <label
                                    class="form-check-label"
                                    for="link_type_url"
                                >
                                    Externí URL
                                </label>

                            </div>

                        </div>

                    </div>

                </div>

                <div
                    class="row mb-4"
                    id="page-link-row"
                >

                    <div class="col-md-8">

                        <label
                            for="page_id"
                            class="form-label"
                        >
                            Cílová stránka
                        </label>

                        <select
                            name="page_id"
                            id="page_id"
                            class="form-select"
                        >

                            <option value="0">
                                — Vyberte stránku —
                            </option>

                            <?php foreach ($pages as $p): ?>

                                <option
                                    value="<?= (int)$p['id'] ?>"
                                    <?= $pageId === (int)$p['id'] ? 'selected' : '' ?>
                                >
                                    <?= e($p['title']) ?>

                                    <?php if (($p['status'] ?? '') !== 'published'): ?>
                                        (koncept)
                                    <?php endif; ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <div class="form-text">
                            URL se bude generovat automaticky podle aktuálního slugu stránky.
                        </div>

                    </div>

                </div>

                <div
                    class="row mb-4"
                    id="url-link-row"
                >

                    <div class="col-md-8">

                        <label
                            for="url"
                            class="form-label"
                        >
                            Externí URL
                        </label>

                        <input
                            type="url"
                            name="url"
                            id="url"
                            class="form-control"
                            placeholder="https://www.example.cz/"
                            value="<?= e($url) ?>"
                        >

                    </div>

                </div>

                <div class="row mb-4">

                    <div class="col-md-8">

                        <label
                            for="target"
                            class="form-label"
                        >
                            Otevřít odkaz
                        </label>

                        <select
                            name="target"
                            id="target"
                            class="form-select"
                        >

                            <option
                                value="_self"
                                <?= $target === '_self' ? 'selected' : '' ?>
                            >
                                Ve stejném okně
                            </option>

                            <option
                                value="_blank"
                                <?= $target === '_blank' ? 'selected' : '' ?>
                            >
                                V novém okně
                            </option>

                        </select>

                    </div>

                </div>

                <div class="row mb-4">

                    <div class="col-md-8">

                        <label
                            for="parent_id"
                            class="form-label"
                        >
                            Nadřazená položka
                        </label>

                        <select
                            name="parent_id"
                            id="parent_id"
                            class="form-select"
                        >

                            <option value="0">
                                — První úroveň —
                            </option>

                            <?php foreach ($parentItems as $parent): ?>

                                <option
                                    value="<?= (int)$parent['id'] ?>"
                                    <?= $parentId === (int)$parent['id'] ? 'selected' : '' ?>
                                >
                                    <?= e($parent['title']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <div class="form-text">
                            Navigace podporuje dvě úrovně položek.
                        </div>

                    </div>

                </div>

                <div class="row mb-4">

                    <div class="col-md-8">

                        <div class="form-check form-switch">

                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="is_active"
                                id="is_active"
                                value="1"
                                <?= $isActive === 1 ? 'checked' : '' ?>
                            >

                            <label
                                class="form-check-label"
                                for="is_active"
                            >
                                Aktivní
                            </label>

                        </div>

                    </div>

                </div>

                <div class="d-flex justify-content-between">

                    <button
                        type="submit"
                        class="btn btn-success"
                    >
                        <i class="fa fa-save me-1"></i>
                        Uložit položku
                    </button>

                    <a
                        href="items.php?id=<?= $navigationId ?>"
                        class="btn btn-secondary"
                    >
                        Zrušit
                    </a>

                </div>

            </form>

        </div>

    </div>

</div>

<script>
document.addEventListener(
    'DOMContentLoaded',
    function () {

        const pageRadio = document.getElementById(
            'link_type_page'
        );

        const urlRadio = document.getElementById(
            'link_type_url'
        );

        const pageRow = document.getElementById(
            'page-link-row'
        );

        const urlRow = document.getElementById(
            'url-link-row'
        );

        const pageSelect = document.getElementById(
            'page_id'
        );

        const urlInput = document.getElementById(
            'url'
        );

        function updateLinkType() {

            const isPage = pageRadio.checked;

            pageRow.style.display = isPage
                ? ''
                : 'none';

            urlRow.style.display = isPage
                ? 'none'
                : '';

            pageSelect.required = isPage;
            urlInput.required = !isPage;

        }

        pageRadio.addEventListener(
            'change',
            updateLinkType
        );

        urlRadio.addEventListener(
            'change',
            updateLinkType
        );

        updateLinkType();

    }
);
</script>

<?php include "../includes/footer.php"; ?>