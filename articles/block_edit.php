<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/settings_helpers.php";

if (function_exists('settings_load_all')) {
    settings_load_all($conn);
}

requirePermission('articles');

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: list.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT 
        ab.*,
        a.title AS article_title,
        bt.name AS block_type_name,
        bt.icon AS block_type_icon
    FROM article_blocks ab
    INNER JOIN articles a ON ab.article_id = a.id
    LEFT JOIN block_types bt ON ab.type = bt.type
    WHERE ab.id = ?
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

$errors = [];

$title = $block['title'] ?? '';
$subtitle = $block['subtitle'] ?? '';
$content = $block['content'] ?? '';
$image = $block['image'] ?? '';
$image_position = $block['image_position'] ?? 'right';
$images = $block['images'] ?? '';
$button_text = $block['button_text'] ?? '';
$button_url = $block['button_url'] ?? '';
$gallery_id = (int)($block['gallery_id'] ?? 0);
$form_id = intval($_POST['form_id'] ?? 0);
$article_limit = (int)($block['article_limit'] ?? 3);
$source_type = $block['source_type'] ?? 'manual';
$category_ids = $block['category_ids'] ?? '';
$article_order = $block['article_order'] ?? 'newest';
$layout = $block['layout'] ?? '';
$section_class = $block['section_class'] ?? '';
$sort_order = (int)($block['sort_order'] ?? 10);
$is_active = (int)($block['is_active'] ?? 1);

if (!in_array($source_type, ['manual', 'category'], true)) {
    $source_type = 'manual';
}

if (!in_array($article_order, ['newest', 'oldest', 'random'], true)) {
    $article_order = 'newest';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $content = $_POST['content'] ?? '';
    $image = trim($_POST['image'] ?? '');
    $image_position = trim($_POST['image_position'] ?? 'right');
    $imagesPost = $_POST['images'] ?? '';
    $button_text = trim($_POST['button_text'] ?? '');
    $button_url = trim($_POST['button_url'] ?? '');
    $gallery_id = intval($_POST['gallery_id'] ?? 0);
    $form_id = intval($_POST['form_id'] ?? 0);
    $article_limit = intval($_POST['article_limit'] ?? 0);

    $source_type = trim($_POST['source_type'] ?? 'manual');
    $categoryIdsPost = $_POST['category_ids'] ?? [];
    $article_order = trim($_POST['article_order'] ?? 'newest');

    if (!in_array($source_type, ['manual', 'category'], true)) {
        $source_type = 'manual';
    }

    if (!in_array($article_order, ['newest', 'oldest', 'random'], true)) {
        $article_order = 'newest';
    }

    if (is_array($categoryIdsPost)) {
        $categoryIdsArr = array_filter(array_map('intval', $categoryIdsPost));
        $category_ids = implode(';', $categoryIdsArr);
    } else {
        $category_ids = '';
    }

    if ($article_limit <= 0) {
        $article_limit = 3;
    }

    $layout = trim($_POST['layout'] ?? '');
    $section_class = trim($_POST['section_class'] ?? '');
    $sort_order = intval($_POST['sort_order'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (!in_array($image_position, ['left', 'right'], true)) {
        $image_position = 'right';
    }

    $allowedSectionClasses = ['', 'bg-light', 'bg-light-5', 'bg-primary', 'bg-dark'];

    if (!in_array($section_class, $allowedSectionClasses, true)) {
        $section_class = '';
    }

    if (is_array($imagesPost)) {
        $imagesArr = array_filter(array_map('trim', $imagesPost));
        $images = implode(';', $imagesArr);
    } else {
        $images = trim($imagesPost);
    }

    if ($title === '') {
        $title = $block['block_type_name'] ?: $block['type'];
    }

    if ($block['type'] === 'text' && trim(strip_tags($content)) === '') {
        $errors[] = 'Vyplňte obsah textového bloku.';
    }

    if (!$errors) {
        $stmt = $conn->prepare("
            UPDATE article_blocks
            SET title = ?,
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
                sort_order = ?,
                is_active = ?
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "ssssssssiiisssssiii",
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
            $sort_order,
            $is_active,
            $id
        );

        $stmt->execute();
        $stmt->close();

        $saveFile = __DIR__ . "/../pages/block_forms/" . $block['type'] . "_save.php";

        if (is_file($saveFile)) {
            include $saveFile;
        }

        logAction("Upraven blok '$title' u článku '{$block['article_title']}'");

        header("Location: blocks.php?id=" . (int)$block['article_id'] . "&updated=1");
        exit;
    }
}

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="admin-page-header-content mb-4">
        <div>
            <h2 class="mb-1">Upravit blok článku</h2>
            <p class="text-muted mb-0">
                <?= e($block['article_title']) ?>
                —
                <?= e($block['block_type_name'] ?: $block['type']) ?>
            </p>
        </div>

        <a href="blocks.php?id=<?= (int)$block['article_id'] ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Zpět na bloky
        </a>
    </div>

    <?php if (isset($_GET['created'])): ?>
        <div class="alert alert-success">
            Blok byl vytvořen. Nyní jej můžete nastavit.
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
                            <?php if (!empty($block['block_type_icon'])): ?>
                                <i class="<?= e($block['block_type_icon']) ?> me-1"></i>
                            <?php endif; ?>

                            <strong><?= e($block['block_type_name'] ?: $block['type']) ?></strong>

                            <div class="text-muted small mt-1">
                                Typ bloku: <code><?= e($block['type']) ?></code>
                            </div>
                        </div>

                        <?php
                        $formFile = __DIR__ . "/../pages/block_forms/" . $block['type'] . ".php";

                        if (is_file($formFile)) {
                            include $formFile;
                        } else {
                            include __DIR__ . "/../pages/block_forms/default.php";
                        }
                        ?>

                        <hr class="my-4">

                        <div class="mb-3">
                            <label class="form-label">Pozadí sekce</label>

                            <select name="section_class" class="form-select">
                                <option value="" <?= $section_class === '' ? 'selected' : '' ?>>Bez pozadí</option>
                                <option value="bg-light" <?= $section_class === 'bg-light' ? 'selected' : '' ?>>Bílé</option>
                                <option value="bg-light-5" <?= $section_class === 'bg-light-5' ? 'selected' : '' ?>>Světle šedé</option>
                                <option value="bg-primary" <?= $section_class === 'bg-primary' ? 'selected' : '' ?>>Primární</option>
                                <option value="bg-dark" <?= $section_class === 'bg-dark' ? 'selected' : '' ?>>Tmavé</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Pořadí</label>
                            <input type="number"
                                   name="sort_order"
                                   class="form-control"
                                   value="<?= (int)$sort_order ?>">
                        </div>

                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input"
                                   type="checkbox"
                                   name="is_active"
                                   id="is_active"
                                   value="1"
                                   <?= $is_active ? 'checked' : '' ?>>

                            <label class="form-check-label" for="is_active">
                                Aktivní
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> Uložit blok
                        </button>

                        <a href="blocks.php?id=<?= (int)$block['article_id'] ?>" class="btn btn-outline-secondary">
                            Zrušit
                        </a>

                    </div>
                </div>

            </form>

        </div>
    </div>

</div>

<?php include "../includes/footer.php"; ?>