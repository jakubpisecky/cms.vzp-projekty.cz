<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('settings');

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: list.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT *
    FROM block_types
    WHERE id = ?
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

$name = $block['name'];
$type = $block['type'];
$description = $block['description'];
$icon = $block['icon'];
$sort_order = (int)$block['sort_order'];
$is_active = (int)$block['is_active'];
$use_in_pages = (int)($block['use_in_pages'] ?? 1);
$use_in_articles = (int)($block['use_in_articles'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? '');
    $sort_order = intval($_POST['sort_order'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $use_in_pages = isset($_POST['use_in_pages']) ? 1 : 0;
    $use_in_articles = isset($_POST['use_in_articles']) ? 1 : 0;

    if ($name === '') {
        $errors[] = 'Vyplňte název bloku.';
    }

    if ($type === '') {
        $errors[] = 'Vyplňte typ bloku.';
    } elseif (!preg_match('/^[a-z0-9_]+$/', $type)) {
        $errors[] = 'Typ bloku může obsahovat pouze malá písmena, čísla a podtržítko.';
    }

    if (!$errors) {
        $stmt = $conn->prepare("
            SELECT id
            FROM block_types
            WHERE type = ?
              AND id != ?
            LIMIT 1
        ");
        $stmt->bind_param("si", $type, $id);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($exists) {
            $errors[] = 'Tento typ bloku už existuje.';
        }
    }

    if (!$errors) {
        $stmt = $conn->prepare("
            UPDATE block_types
            SET name = ?,
                type = ?,
                description = ?,
                icon = ?,
                sort_order = ?,
                is_active = ?,
                use_in_pages = ?,
                use_in_articles = ?
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->bind_param(
            "ssssiiiii",
            $name,
            $type,
            $description,
            $icon,
            $sort_order,
            $is_active,
            $use_in_pages,
            $use_in_articles,
            $id
        );
        $stmt->execute();
        $stmt->close();

        logAction("Upraven typ bloku '$name'");

        header("Location: list.php?updated=1");
        exit;
    }
}

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="admin-page-header-content mb-4">
        <div>
            <h2 class="mb-1">Upravit blok</h2>
            <p class="text-muted mb-0"><?= e($name) ?></p>
        </div>

        <a href="list.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Zpět
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

                <div class="row">
                    <div class="col-lg-12">

                        <div class="mb-3">
                            <label for="name" class="form-label">Název bloku</label>
                            <input type="text"
                                   name="name"
                                   id="name"
                                   class="form-control"
                                   value="<?= e($name) ?>"
                                   required>
                        </div>

                        <div class="mb-3">
                            <label for="type" class="form-label">Typ bloku</label>
                            <input type="text"
                                   name="type"
                                   id="type"
                                   class="form-control"
                                   value="<?= e($type) ?>"
                                   required>

                            <div class="form-text">
                                Název odpovídá souboru v <code>/templates/blocks/</code>, například <code><?= e($type) ?>.php</code>.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Popis</label>
                            <textarea name="description"
                                      id="description"
                                      rows="4"
                                      class="form-control"><?= e($description) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="icon" class="form-label">Bootstrap ikona</label>
                            <input type="text"
                                   name="icon"
                                   id="icon"
                                   class="form-control"
                                   value="<?= e($icon) ?>"
                                   placeholder="bi bi-grid-3x3-gap">
                        </div>

                        <div class="mb-3">
                            <label for="sort_order" class="form-label">Pořadí</label>
                            <input type="number"
                                   name="sort_order"
                                   id="sort_order"
                                   class="form-control"
                                   value="<?= (int)$sort_order ?>">
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input"
                                type="checkbox"
                                name="use_in_pages"
                                id="use_in_pages"
                                value="1"
                                <?= $use_in_pages ? 'checked' : '' ?>>

                            <label class="form-check-label" for="use_in_pages">
                                Použít u stránek
                            </label>
                        </div>

                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input"
                                type="checkbox"
                                name="use_in_articles"
                                id="use_in_articles"
                                value="1"
                                <?= $use_in_articles ? 'checked' : '' ?>>

                            <label class="form-check-label" for="use_in_articles">
                                Použít u článků
                            </label>
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
                            <i class="bi bi-save me-1"></i> Uložit změny
                        </button>

                        <a href="list.php" class="btn btn-outline-secondary">
                            Zrušit
                        </a>

                    </div>
                </div>

            </form>

        </div>
    </div>

</div>

<?php include "../includes/footer.php"; ?>