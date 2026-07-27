<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('workplaces');

$pageId = intval($_GET['page_id'] ?? 0);

if ($pageId <= 0) {
    header("Location: list.php");
    exit;
}

/*
 * Načtení stránky a jejího pracoviště.
 * Současně ověříme, že stránka opravdu patří do modulu Pracoviště.
 */
$stmt = $conn->prepare("
    SELECT
        p.id AS page_id,
        p.title AS page_title,
        p.template,
        p.owner_id,

        wp.id AS workplace_page_id,
        wp.workplace_id,

        w.name AS workplace_name

    FROM pages p

    INNER JOIN workplace_pages wp
        ON wp.page_id = p.id

    INNER JOIN workplaces w
        ON w.id = wp.workplace_id

    WHERE p.id = ?
      AND p.owner_type = 'workplace'
      AND p.owner_id = wp.workplace_id

    LIMIT 1
");

$stmt->bind_param("i", $pageId);
$stmt->execute();

$page = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$page) {
    header("Location: list.php");
    exit;
}

$workplaceId = (int)$page['workplace_id'];
$workplacePageId = (int)$page['workplace_page_id'];

/*
 * Bloky lze přidávat pouze k blokovým šablonám.
 */
if (!in_array($page['template'], ['home', 'universal'], true)) {
    header(
        "Location: page_edit.php?id="
        . $workplacePageId
    );
    exit;
}

/*
 * Dostupné typy bloků.
 */
$types = $conn->query("
    SELECT
        name,
        type,
        description,
        icon

    FROM block_types

    WHERE is_active = 1
      AND use_in_pages = 1

    ORDER BY
        sort_order ASC,
        id ASC
");

$errors = [];
$selectedType = '';

/*
 * Uložení nového bloku.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $selectedType = trim($_POST['type'] ?? '');

    if ($selectedType === '') {
        $errors[] = 'Vyberte typ bloku.';
    }

    $blockType = null;

    if (!$errors) {
        $stmt = $conn->prepare("
            SELECT
                name,
                type

            FROM block_types

            WHERE type = ?
              AND is_active = 1
              AND use_in_pages = 1

            LIMIT 1
        ");

        $stmt->bind_param(
            "s",
            $selectedType
        );

        $stmt->execute();

        $blockType = $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();

        if (!$blockType) {
            $errors[] =
                'Vybraný typ bloku neexistuje, '
                . 'není aktivní nebo není povolený pro stránky.';
        }
    }

    if (!$errors) {

        /*
         * Další pořadí bloku.
         */
        $stmt = $conn->prepare("
            SELECT
                COALESCE(MAX(sort_order), 0) + 10
                    AS next_order

            FROM page_blocks

            WHERE page_id = ?
        ");

        $stmt->bind_param(
            "i",
            $pageId
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

        $title = trim(
            (string)($blockType['name'] ?? '')
        );

        if ($title === '') {
            $title = $selectedType;
        }

        /*
         * Vložení bloku.
         */
        $stmt = $conn->prepare("
            INSERT INTO page_blocks
            (
                page_id,
                type,
                title,
                sort_order,
                is_active
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                1
            )
        ");

        $stmt->bind_param(
            "issi",
            $pageId,
            $selectedType,
            $title,
            $sortOrder
        );

        $stmt->execute();

        $blockId = (int)$stmt->insert_id;

        $stmt->close();

        if ($blockId <= 0) {
            $errors[] = 'Blok se nepodařilo vytvořit.';
        } else {
            logAction(
                "Přidán blok '$title' "
                . "ke stránce '{$page['page_title']}' "
                . "pracoviště '{$page['workplace_name']}'"
            );

            header(
                "Location: block_edit.php?id="
                . $blockId
                . "&created=1"
            );
            exit;
        }
    }
}

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="admin-page-header-content mb-4">

        <div>

            <h2 class="mb-1">
                Přidat blok
            </h2>

            <p class="text-muted mb-0">

                <strong>
                    <?= e($page['workplace_name']) ?>
                </strong>

                <span class="mx-1">—</span>

                <?= e($page['page_title']) ?>

            </p>

        </div>

        <a
            href="page_blocks.php?page_id=<?= $pageId ?>"
            class="btn btn-outline-secondary">

            <i class="bi bi-arrow-left me-1"></i>
            Zpět na bloky

        </a>

    </div>

    <?php if ($errors): ?>

        <div class="alert alert-danger">

            <?php foreach ($errors as $error): ?>
                <div><?= e($error) ?></div>
            <?php endforeach; ?>

        </div>

    <?php endif; ?>

    <?php if (!$types || $types->num_rows === 0): ?>

        <div class="alert alert-warning">
            Nejsou dostupné žádné aktivní typy bloků pro stránky.
        </div>

    <?php else: ?>

        <form method="post">

            <div class="row g-4">

                <?php while ($type = $types->fetch_assoc()): ?>

                    <?php
                    $typeValue = (string)$type['type'];
                    $inputId = 'block_type_'
                        . preg_replace(
                            '/[^a-zA-Z0-9_-]/',
                            '_',
                            $typeValue
                        );
                    ?>

                    <div class="col-md-12 col-xl-4">

                        <label
                            class="card shadow-sm border-0 h-100"
                            for="<?= e($inputId) ?>"
                            style="cursor:pointer;">

                            <div class="card-body">

                                <div class="d-flex gap-3 align-items-start">

                                    <div class="fs-2 text-primary">

                                        <?php if (!empty($type['icon'])): ?>

                                            <i class="<?= e(
                                                $type['icon']
                                            ) ?>"></i>

                                        <?php else: ?>

                                            <i class="bi bi-grid-3x3-gap"></i>

                                        <?php endif; ?>

                                    </div>

                                    <div class="flex-grow-1">

                                        <h5 class="mb-1">
                                            <?= e($type['name']) ?>
                                        </h5>

                                        <?php if (!empty($type['description'])): ?>

                                            <p class="text-muted mb-2">
                                                <?= e(
                                                    $type['description']
                                                ) ?>
                                            </p>

                                        <?php endif; ?>

                                        <div class="form-check">

                                            <input
                                                class="form-check-input"
                                                type="radio"
                                                name="type"
                                                id="<?= e($inputId) ?>"
                                                value="<?= e($typeValue) ?>"
                                                required
                                                <?= $selectedType === $typeValue
                                                    ? 'checked'
                                                    : '' ?>>

                                            <span class="form-check-label">
                                                Vybrat tento blok
                                            </span>

                                        </div>

                                        <div class="text-muted small mt-2">

                                            <code>
                                                <?= e($typeValue) ?>
                                            </code>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </label>

                    </div>

                <?php endwhile; ?>

            </div>

            <div class="mt-4">

                <button
                    type="submit"
                    class="btn btn-primary">

                    <i class="bi bi-plus-circle me-1"></i>
                    Pokračovat

                </button>

                <a
                    href="page_blocks.php?page_id=<?= $pageId ?>"
                    class="btn btn-outline-secondary">

                    Zrušit

                </a>

            </div>

        </form>

    <?php endif; ?>

</div>

<?php include "../includes/footer.php"; ?>