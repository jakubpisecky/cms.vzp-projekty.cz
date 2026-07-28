<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/settings_helpers.php";

requirePermission('workplaces');

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: list.php");
    exit;
}

/*
 * Načtení bloku, stránky a pracoviště.
 *
 * Ověřujeme:
 * - stránka patří modulu Pracoviště,
 * - owner_id odpovídá pracovišti,
 * - stránka je propojena přes workplace_pages.
 */
$stmt = $conn->prepare("
    SELECT
        pb.*,

        p.title AS page_title,
        p.template AS page_template,
        p.owner_type,
        p.owner_id,

        wp.id AS workplace_page_id,
        wp.workplace_id,

        w.name AS workplace_name,

        bt.name AS block_type_name,
        bt.icon AS block_type_icon

    FROM page_blocks pb

    INNER JOIN pages p
        ON p.id = pb.page_id

    INNER JOIN workplace_pages wp
        ON wp.page_id = p.id

    INNER JOIN workplaces w
        ON w.id = wp.workplace_id

    LEFT JOIN block_types bt
        ON bt.type = pb.type

    WHERE pb.id = ?
      AND p.owner_type = 'workplace'
      AND p.owner_id = wp.workplace_id

    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$block = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$block) {
    header("Location: list.php");
    exit;
}

$pageId = (int)$block['page_id'];
$workplaceId = (int)$block['workplace_id'];
$workplacePageId = (int)$block['workplace_page_id'];

/*
 * Bloky lze upravovat pouze u blokových šablon.
 */
if (!in_array(
    $block['page_template'],
    ['home', 'universal'],
    true
)) {
    header(
        "Location: page_edit.php?id="
        . $workplacePageId
    );
    exit;
}

$errors = [];

/*
 * Výchozí hodnoty.
 *
 * Názvy proměnných záměrně odpovídají
 * původnímu /pages/block_edit.php, protože je očekávají
 * společné formuláře v /pages/block_forms/.
 */
$title = $block['title'] ?? '';
$subtitle = $block['subtitle'] ?? '';
$content = $block['content'] ?? '';

$image = $block['image'] ?? '';
$image_position = $block['image_position'] ?? 'right';
$images = $block['images'] ?? '';

$button_text = $block['button_text'] ?? '';
$button_url = $block['button_url'] ?? '';

$gallery_id = (int)($block['gallery_id'] ?? 0);
$form_id = (int)($block['form_id'] ?? 0);

$article_limit = (int)($block['article_limit'] ?? 3);
$source_type = $block['source_type'] ?? 'manual';
$category_ids = $block['category_ids'] ?? '';
$article_order = $block['article_order'] ?? 'newest';

$layout = $block['layout'] ?? '';
$section_class = $block['section_class'] ?? '';

$sort_order = (int)($block['sort_order'] ?? 10);
$is_active = (int)($block['is_active'] ?? 1);

$col1_icon = $block['col1_icon'] ?? '';
$col1_kicker = $block['col1_kicker'] ?? '';
$col1_title = $block['col1_title'] ?? '';
$col1_text = $block['col1_text'] ?? '';

$col2_icon = $block['col2_icon'] ?? '';
$col2_kicker = $block['col2_kicker'] ?? '';
$col2_title = $block['col2_title'] ?? '';
$col2_text = $block['col2_text'] ?? '';

$col3_icon = $block['col3_icon'] ?? '';
$col3_kicker = $block['col3_kicker'] ?? '';
$col3_title = $block['col3_title'] ?? '';
$col3_text = $block['col3_text'] ?? '';

if (!in_array(
    $source_type,
    ['manual', 'category'],
    true
)) {
    $source_type = 'manual';
}

if (!in_array(
    $article_order,
    ['newest', 'oldest', 'random'],
    true
)) {
    $article_order = 'newest';
}

if (!in_array(
    $image_position,
    ['left', 'right'],
    true
)) {
    $image_position = 'right';
}

/*
 * Uložení bloku.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $content = $_POST['content'] ?? '';

    $image = trim($_POST['image'] ?? '');
    $image_position = trim(
        $_POST['image_position'] ?? 'right'
    );

    $imagesPost = $_POST['images'] ?? '';

    $button_text = trim($_POST['button_text'] ?? '');
    $button_url = trim($_POST['button_url'] ?? '');

    $gallery_id = intval($_POST['gallery_id'] ?? 0);
    $form_id = intval($_POST['form_id'] ?? 0);

    $article_limit = intval(
        $_POST['article_limit'] ?? 0
    );

    $source_type = trim(
        $_POST['source_type'] ?? 'manual'
    );

    $categoryIdsPost = $_POST['category_ids'] ?? [];

    $article_order = trim(
        $_POST['article_order'] ?? 'newest'
    );

    $layout = trim($_POST['layout'] ?? '');

    $section_class = trim(
        $_POST['section_class'] ?? ''
    );

    $sort_order = intval(
        $_POST['sort_order'] ?? 0
    );

    $is_active = isset($_POST['is_active'])
        ? 1
        : 0;

    $col1_icon = trim($_POST['col1_icon'] ?? '');
    $col1_kicker = trim($_POST['col1_kicker'] ?? '');
    $col1_title = trim($_POST['col1_title'] ?? '');
    $col1_text = $_POST['col1_text'] ?? '';

    $col2_icon = trim($_POST['col2_icon'] ?? '');
    $col2_kicker = trim($_POST['col2_kicker'] ?? '');
    $col2_title = trim($_POST['col2_title'] ?? '');
    $col2_text = $_POST['col2_text'] ?? '';

    $col3_icon = trim($_POST['col3_icon'] ?? '');
    $col3_kicker = trim($_POST['col3_kicker'] ?? '');
    $col3_title = trim($_POST['col3_title'] ?? '');
    $col3_text = $_POST['col3_text'] ?? '';

    /*
     * Validace nastavení výpisu článků.
     */
    if (!in_array(
        $source_type,
        ['manual', 'category'],
        true
    )) {
        $source_type = 'manual';
    }

    if (!in_array(
        $article_order,
        ['newest', 'oldest', 'random'],
        true
    )) {
        $article_order = 'newest';
    }

    if (is_array($categoryIdsPost)) {
        $categoryIdsArray = array_values(
            array_unique(
                array_filter(
                    array_map(
                        'intval',
                        $categoryIdsPost
                    )
                )
            )
        );

        $category_ids = implode(
            ';',
            $categoryIdsArray
        );
    } else {
        $category_ids = '';
    }

    if ($article_limit <= 0) {
        $article_limit = 3;
    }

    /*
     * Pozice obrázku.
     */
    if (!in_array(
        $image_position,
        ['left', 'right'],
        true
    )) {
        $image_position = 'right';
    }

    /*
     * Povolená pozadí sekcí.
     */
    $allowedSectionClasses = [
        '',
        'bg-light',
        'bg-light-5',
        'bg-primary',
        'bg-dark',
    ];

    if (!in_array(
        $section_class,
        $allowedSectionClasses,
        true
    )) {
        $section_class = '';
    }

    /*
     * Více obrázků.
     */
    if (is_array($imagesPost)) {
        $imagesArray = array_filter(
            array_map(
                'trim',
                $imagesPost
            )
        );

        $images = implode(';', $imagesArray);
    } else {
        $images = trim((string)$imagesPost);
    }

    /*
     * Výchozí název bloku.
     */
    if ($title === '') {
        $title = $block['block_type_name']
            ?: $block['type'];
    }

    /*
     * Validace textového bloku.
     */
    if (
        $block['type'] === 'text'
        && trim(strip_tags($content)) === ''
    ) {
        $errors[] = 'Vyplňte obsah textového bloku.';
    }

    if (!$errors) {

        $stmt = $conn->prepare("
            UPDATE page_blocks
            SET
                title = ?,
                subtitle = ?,
                content = ?,
                image = ?,
                image_position = ?,
                images = ?,
                button_text = ?,
                button_url = ?,

                gallery_id = ?,
                form_id = ?,
                article_limit = ?,

                source_type = ?,
                category_ids = ?,
                article_order = ?,
                layout = ?,
                section_class = ?,

                col1_icon = ?,
                col1_kicker = ?,
                col1_title = ?,
                col1_text = ?,

                col2_icon = ?,
                col2_kicker = ?,
                col2_title = ?,
                col2_text = ?,

                col3_icon = ?,
                col3_kicker = ?,
                col3_title = ?,
                col3_text = ?,

                sort_order = ?,
                is_active = ?

            WHERE id = ?
              AND page_id = ?

            LIMIT 1
        ");

        /*
         * Stejná struktura proměnných jako v původním
         * funkčním /pages/block_edit.php.
         */
        $stmt->bind_param(
            "ssssssssssssssssssssssssssssiiii",
            $title,
            $subtitle,
            $content,
            $image,
            $image_position,
            $images,
            $button_text,
            $button_url,

            $gallery_id,
            $form_id,
            $article_limit,

            $source_type,
            $category_ids,
            $article_order,
            $layout,
            $section_class,

            $col1_icon,
            $col1_kicker,
            $col1_title,
            $col1_text,

            $col2_icon,
            $col2_kicker,
            $col2_title,
            $col2_text,

            $col3_icon,
            $col3_kicker,
            $col3_title,
            $col3_text,

            $sort_order,
            $is_active,
            $id,
            $pageId
        );

        $stmt->execute();
        $stmt->close();

        /*
         * Doplňkové ukládání konkrétních typů bloků
         * používáme společné s Pages a Articles.
         */
        $saveFile = __DIR__
            . "/../pages/block_forms/"
            . $block['type']
            . "_save.php";

        if (is_file($saveFile)) {
            include $saveFile;
        }

        logAction(
            "Upraven blok '$title' "
            . "na stránce '{$block['page_title']}' "
            . "pracoviště '{$block['workplace_name']}'"
        );

        header(
            "Location: page_blocks.php?page_id="
            . $pageId
            . "&updated=1"
        );
        exit;
    }
}

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="admin-page-header-content mb-4">

        <div>

            <h2 class="mb-1">
                Upravit blok
            </h2>

            <p class="text-muted mb-0">

                <strong>
                    <?= e($block['workplace_name']) ?>
                </strong>

                <span class="mx-1">—</span>

                <?= e($block['page_title']) ?>

                <span class="mx-1">—</span>

                <?= e(
                    $block['block_type_name']
                    ?: $block['type']
                ) ?>

            </p>

        </div>

        <a
            href="page_blocks.php?page_id=<?= $pageId ?>"
            class="btn btn-outline-secondary">

            <i class="bi bi-arrow-left me-1"></i>
            Zpět na bloky

        </a>

    </div>

    <?php if (isset($_GET['created'])): ?>

        <div class="alert alert-success">

            Blok byl vytvořen.
            Nyní jej můžete nastavit.

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

                <div class="row">

                    <div class="col-lg-12">

                        <div class="alert alert-light border">

                            <?php if (
                                !empty($block['block_type_icon'])
                            ): ?>

                                <i class="<?= e(
                                    $block['block_type_icon']
                                ) ?> me-1"></i>

                            <?php endif; ?>

                            <strong>

                                <?= e(
                                    $block['block_type_name']
                                    ?: $block['type']
                                ) ?>

                            </strong>

                            <div class="text-muted small mt-1">

                                Typ bloku:

                                <code>
                                    <?= e($block['type']) ?>
                                </code>

                            </div>

                        </div>

                        <?php
                        /*
                         * Formuláře bloků jsou společné pro
                         * Pages, Articles a Workplaces.
                         *
                         * Díky zachování původních názvů proměnných
                         * mají k dispozici například:
                         *
                         * $button_text
                         * $button_url
                         * $gallery_id
                         * $form_id
                         * $article_limit
                         * $source_type
                         * $category_ids
                         * $image_position
                         */
                        $formFile = __DIR__
                            . "/../pages/block_forms/"
                            . $block['type']
                            . ".php";

                        if (is_file($formFile)) {
                            include $formFile;
                        } else {
                            include __DIR__
                                . "/../pages/block_forms/default.php";
                        }
                        ?>

                        <hr class="my-4">

                        <div class="mb-3">

                            <label class="form-label">
                                Pozadí sekce
                            </label>

                            <select
                                name="section_class"
                                class="form-select">

                                <option
                                    value=""
                                    <?= $section_class === ''
                                        ? 'selected'
                                        : '' ?>>

                                    Bez pozadí

                                </option>

                                <option
                                    value="bg-light"
                                    <?= $section_class === 'bg-light'
                                        ? 'selected'
                                        : '' ?>>

                                    Světlé

                                </option>

                                <option
                                    value="bg-light-5"
                                    <?= $section_class === 'bg-light-5'
                                        ? 'selected'
                                        : '' ?>>

                                    Velmi světlé

                                </option>

                                <option
                                    value="bg-primary"
                                    <?= $section_class === 'bg-primary'
                                        ? 'selected'
                                        : '' ?>>

                                    Primární

                                </option>

                                <option
                                    value="bg-dark"
                                    <?= $section_class === 'bg-dark'
                                        ? 'selected'
                                        : '' ?>>

                                    Tmavé

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
                                value="<?= (int)$sort_order ?>">

                        </div>

                        <div class="form-check form-switch mb-4">

                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="is_active"
                                id="is_active"
                                value="1"
                                <?= $is_active
                                    ? 'checked'
                                    : '' ?>>

                            <label
                                class="form-check-label"
                                for="is_active">

                                Aktivní

                            </label>

                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary">

                            <i class="bi bi-save me-1"></i>
                            Uložit blok

                        </button>

                        <a
                            href="page_blocks.php?page_id=<?= $pageId ?>"
                            class="btn btn-outline-secondary">

                            Zrušit

                        </a>

                    </div>

                </div>

            </form>

        </div>

    </div>

</div>

<?php include "../includes/footer.php"; ?>