<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('pages');

$pageId = intval($_GET['page_id'] ?? 0);

if ($pageId <= 0) {
    header("Location: list.php");
    exit;
}

// načtení stránky
$stmt = $conn->prepare("
    SELECT id, title, template
    FROM pages
    WHERE id = ?
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

if (!in_array($page['template'], ['home', 'universal'], true)) {
    header("Location: edit.php?id=" . $pageId);
    exit;
}

// dostupné typy bloků
$types = $conn->query("
    SELECT name, type, description, icon
    FROM block_types
    WHERE is_active = 1
      AND use_in_pages = 1
    ORDER BY sort_order ASC, id ASC
");

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = trim($_POST['type'] ?? '');

    if ($type === '') {
        $errors[] = 'Vyberte typ bloku.';
    } else {
        $stmt = $conn->prepare("
            SELECT name
            FROM block_types
            WHERE type = ?
              AND is_active = 1
            LIMIT 1
        ");
        $stmt->bind_param("s", $type);
        $stmt->execute();
        $blockType = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$blockType) {
            $errors[] = 'Vybraný typ bloku neexistuje nebo není aktivní.';
        }
    }

    if (!$errors) {
        $stmt = $conn->prepare("
            SELECT COALESCE(MAX(sort_order), 0) + 10 AS next_order
            FROM page_blocks
            WHERE page_id = ?
        ");
        $stmt->bind_param("i", $pageId);
        $stmt->execute();
        $sortOrder = (int)($stmt->get_result()->fetch_assoc()['next_order'] ?? 10);
        $stmt->close();

        if ($sortOrder <= 0) {
            $sortOrder = 10;
        }

        $title = $blockType['name'];

        $stmt = $conn->prepare("
            INSERT INTO page_blocks
                (page_id, type, title, sort_order, is_active)
            VALUES
                (?, ?, ?, ?, 1)
        ");
        $stmt->bind_param("issi", $pageId, $type, $title, $sortOrder);
        $stmt->execute();

        $blockId = (int)$stmt->insert_id;
        $stmt->close();

        logAction("Přidán blok '$title' ke stránce '{$page['title']}'");

        header("Location: block_edit.php?id=" . $blockId . "&created=1");
        exit;
    }
}

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="admin-page-header-content mb-4">
        <div>
            <h2 class="mb-1">Přidat blok</h2>
            <p class="text-muted mb-0"><?= e($page['title']) ?></p>
        </div>

        <a href="blocks.php?page_id=<?= (int)$pageId ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Zpět na bloky
        </a>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <div><?= e($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post">

        <div class="row g-4">

            <?php while ($type = $types->fetch_assoc()): ?>
                <div class="col-md-12 col-xl-4">
                    <label class="card shadow-sm border-0 h-100" style="cursor:pointer;">

                        <div class="card-body">
                            <div class="d-flex gap-3 align-items-start">

                                <div class="fs-2 text-primary">
                                    <?php if (!empty($type['icon'])): ?>
                                        <i class="<?= e($type['icon']) ?>"></i>
                                    <?php else: ?>
                                        <i class="bi bi-grid-3x3-gap"></i>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <h5 class="mb-1"><?= e($type['name']) ?></h5>

                                    <?php if (!empty($type['description'])): ?>
                                        <p class="text-muted mb-2">
                                            <?= e($type['description']) ?>
                                        </p>
                                    <?php endif; ?>

                                    <div class="form-check">
                                        <input class="form-check-input"
                                               type="radio"
                                               name="type"
                                               value="<?= e($type['type']) ?>"
                                               required>

                                        <span class="form-check-label">
                                            Vybrat tento blok
                                        </span>
                                    </div>

                                    <div class="text-muted small mt-2">
                                        <code><?= e($type['type']) ?></code>
                                    </div>
                                </div>

                            </div>
                        </div>

                    </label>
                </div>
            <?php endwhile; ?>

        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Pokračovat
            </button>

            <a href="blocks.php?page_id=<?= (int)$pageId ?>" class="btn btn-outline-secondary">
                Zrušit
            </a>
        </div>

    </form>

</div>

<?php include "../includes/footer.php"; ?>