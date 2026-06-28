<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('articles');

$articleId = intval($_GET['id'] ?? 0);

if ($articleId <= 0) {
    header("Location: list.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT id, title
    FROM articles
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $articleId);
$stmt->execute();
$article = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$article) {
    header("Location: list.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT ab.*, bt.name AS block_type_name, bt.icon AS block_type_icon
    FROM article_blocks ab
    LEFT JOIN block_types bt ON ab.type = bt.type
    WHERE ab.article_id = ?
    ORDER BY ab.sort_order ASC, ab.id ASC
");
$stmt->bind_param("i", $articleId);
$stmt->execute();
$blocks = $stmt->get_result();
$stmt->close();

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Bloky článku</h2>
            <p class="text-muted mb-0"><?= e($article['title']) ?></p>
        </div>

        <div>
            <a href="edit.php?id=<?= (int)$articleId ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Zpět na článek
            </a>

            <a href="block_add.php?article_id=<?= (int)$articleId ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Přidat blok
            </a>
        </div>
    </div>

    <?php if (isset($_GET['created'])): ?>
        <div class="alert alert-success">Blok byl vytvořen.</div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success">Blok byl upraven.</div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">Blok byl smazán.</div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body">

            <?php if ($blocks->num_rows === 0): ?>

                <div class="alert alert-info mb-0">
                    Tento článek zatím nemá žádné bloky.
                </div>

            <?php else: ?>

                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th style="width:70px;">ID</th>
                                <th>Blok</th>
                                <th>Typ</th>
                                <th class="text-center">Pořadí</th>
                                <th class="text-center">Stav</th>
                                <th class="text-end">Akce</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php while ($block = $blocks->fetch_assoc()): ?>
                                <tr>
                                    <td><?= (int)$block['id'] ?></td>

                                    <td>
                                        <?php if (!empty($block['block_type_icon'])): ?>
                                            <i class="<?= e($block['block_type_icon']) ?> me-1"></i>
                                        <?php endif; ?>

                                        <strong><?= e($block['title'] ?: ($block['block_type_name'] ?: $block['type'])) ?></strong>
                                    </td>

                                    <td>
                                        <code><?= e($block['type']) ?></code>
                                    </td>

                                    <td class="text-center">
                                        <?= (int)$block['sort_order'] ?>
                                    </td>

                                    <td class="text-center">
                                        <?php if ((int)$block['is_active'] === 1): ?>
                                            <span class="badge bg-success">Aktivní</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Neaktivní</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-end text-nowrap">
                                        <a href="block_edit.php?id=<?= (int)$block['id'] ?>"
                                           class="btn btn-sm btn-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <a href="block_delete.php?id=<?= (int)$block['id'] ?>"
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Smazat blok?');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>

        </div>
    </div>

</div>

<?php include "../includes/footer.php"; ?>