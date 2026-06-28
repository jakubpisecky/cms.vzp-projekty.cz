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
    SELECT *
    FROM article_blocks
    WHERE article_id = ?
    ORDER BY sort_order ASC, id ASC
");

$stmt->bind_param("i", $articleId);
$stmt->execute();

$blocks = $stmt->get_result();

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="admin-page-header-content mb-4">

        <div>
            <h2 class="mb-1">Bloky článku</h2>
            <p class="text-muted mb-0">
                <?= e($article['title']) ?>
            </p>
        </div>

        <div class="responsive-actions">
            <a href="edit.php?id=<?= (int)$articleId ?>"
               class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>
                Zpět na článek
            </a>

            <a href="block_add.php?article_id=<?= (int)$articleId ?>"
               class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i>
                Přidat blok
            </a>
        </div>

    </div>

    <?php if (isset($_GET['created'])): ?>
        <div class="alert alert-success">
            Blok byl vytvořen.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success">
            Blok byl upraven.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">
            Blok byl smazán.
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body">

            <?php if ($blocks->num_rows === 0): ?>

                <div class="alert alert-info mb-0">
                    Tento článek zatím neobsahuje žádné bloky.
                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead>
                            <tr>
                                <th width="70">ID</th>
                                <th>Název</th>
                                <th>Typ</th>
                                <th width="100">Pořadí</th>
                                <th width="100">Stav</th>
                                <th width="220" class="text-end">Akce</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php while ($block = $blocks->fetch_assoc()): ?>

                                <tr>

                                    <td>
                                        <?= (int)$block['id'] ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= e($block['title'] ?: $block['type']) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <code>
                                            <?= e($block['type']) ?>
                                        </code>
                                    </td>

                                    <td>
                                        <?= (int)$block['sort_order'] ?>
                                    </td>

                                    <td>

                                        <?php if ((int)$block['is_active'] === 1): ?>
                                            <span class="badge bg-success">
                                                Aktivní
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">
                                                Neaktivní
                                            </span>
                                        <?php endif; ?>

                                    </td>

                                    <td class="text-end text-nowrap">

                                        <a href="block_move.php?id=<?= (int)$block['id'] ?>&dir=up"
                                           class="btn btn-sm btn-outline-secondary"
                                           title="Nahoru">
                                            ↑
                                        </a>

                                        <a href="block_move.php?id=<?= (int)$block['id'] ?>&dir=down"
                                           class="btn btn-sm btn-outline-secondary"
                                           title="Dolů">
                                            ↓
                                        </a>

                                        <a href="block_edit.php?id=<?= (int)$block['id'] ?>"
                                           class="btn btn-sm btn-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <a href="block_delete.php?id=<?= (int)$block['id'] ?>"
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Opravdu chcete blok smazat?');">
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