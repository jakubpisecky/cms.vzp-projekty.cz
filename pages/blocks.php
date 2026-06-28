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

$stmt = $conn->prepare("
    SELECT 
        pb.*,
        bt.name AS block_type_name,
        bt.icon AS block_type_icon
    FROM page_blocks pb
    LEFT JOIN block_types bt ON pb.type = bt.type
    WHERE pb.page_id = ?
    ORDER BY pb.sort_order ASC, pb.id ASC
");
$stmt->bind_param("i", $pageId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="admin-page-header-content mb-4">
        <div>
            <h2 class="mb-1">Bloky stránky</h2>
            <p class="text-muted mb-0"><?= e($page['title']) ?></p>
        </div>

        <div class="responsive-actions">
            <a href="block_add.php?page_id=<?= (int)$pageId ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Přidat blok
            </a>

            <a href="edit.php?id=<?= (int)$pageId ?>" class="btn btn-outline-secondary">
                <i class="bi bi-pencil me-1"></i> Upravit stránku
            </a>

            <a href="list.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Zpět
            </a>
        </div>
    </div>

    <?php if (isset($_GET['created'])): ?>
        <div class="alert alert-success">Blok byl přidán.</div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success">Blok byl upraven.</div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">Blok byl odstraněn.</div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover align-middle mb-0 bg-white">
                <thead class="table-light">
                    <tr>
                        <th width="70" class="text-center">ID</th>
                        <th>Blok</th>
                        <th width="180">Typ</th>
                        <th width="100" class="text-center">Pořadí</th>
                        <th width="100" class="text-center">Stav</th>
                        <th width="150" class="text-center">Akce</th>
                    </tr>
                </thead>

                <tbody>
                    <?php while ($block = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="text-center"><?= (int)$block['id'] ?></td>

                            <td>
                                <div class="fw-semibold">
                                    <?= e($block['title'] ?: 'Bez názvu') ?>
                                </div>

                                <?php if (!empty($block['subtitle'])): ?>
                                    <div class="text-muted small"><?= e($block['subtitle']) ?></div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (!empty($block['block_type_icon'])): ?>
                                    <i class="<?= e($block['block_type_icon']) ?> me-1"></i>
                                <?php endif; ?>

                                <?= e($block['block_type_name'] ?: $block['type']) ?>

                                <div class="text-muted small">
                                    <code><?= e($block['type']) ?></code>
                                </div>
                            </td>

                            <td class="text-center">
                                <?= (int)$block['sort_order'] ?>
                            </td>

                            <td class="text-center">
                                <?php if ((int)$block['is_active'] === 1): ?>
                                    <a href="block_status.php?id=<?= (int)$block['id'] ?>&to=0"
                                    class="badge bg-success text-white text-decoration-none"
                                    title="Kliknutím skrýt blok">
                                        aktivní
                                    </a>
                                <?php else: ?>
                                    <a href="block_status.php?id=<?= (int)$block['id'] ?>&to=1"
                                    class="badge bg-secondary text-white text-decoration-none"
                                    title="Kliknutím aktivovat blok">
                                        skrytý
                                    </a>
                                <?php endif; ?>
                            </td>

                            <td class="text-center text-nowrap">

                            <div class="btn-group me-1" role="group">
                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary btn-block-move"
                                        data-id="<?= (int)$block['id'] ?>"
                                        data-dir="up"
                                        title="Posunout nahoru">
                                    ↑
                                </button>

                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary btn-block-move"
                                        data-id="<?= (int)$block['id'] ?>"
                                        data-dir="down"
                                        title="Posunout dolů">
                                    ↓
                                </button>
                            </div>

                            <a href="block_edit.php?id=<?= (int)$block['id'] ?>"
                            class="btn btn-sm btn-primary"
                            title="Upravit blok">
                                <i class="bi bi-pencil"></i>
                            </a>

                            <a href="block_delete.php?id=<?= (int)$block['id'] ?>"
                            class="btn btn-sm btn-danger"
                            onclick="return confirm('Opravdu smazat tento blok?');"
                            title="Smazat blok">
                                <i class="bi bi-trash"></i>
                            </a>

                        </td>
                        </tr>
                    <?php endwhile; ?>

                    <?php if ($result->num_rows === 0): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted p-4">
                                Tato stránka zatím nemá žádné bloky.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>

            </table>
        </div>
    </div>

</div>

<script>
document.addEventListener('click', async function(e) {
    const btn = e.target.closest('.btn-block-move');

    if (!btn) {
        return;
    }

    btn.disabled = true;

    try {
        const response = await fetch('block_reorder.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body:
                'id=' + encodeURIComponent(btn.dataset.id) +
                '&dir=' + encodeURIComponent(btn.dataset.dir)
        });

        const text = await response.text();
        let data;

        try {
            data = JSON.parse(text);
        } catch {
            throw new Error('Server nevrátil JSON:\n' + text.slice(0, 300));
        }

        if (!data.ok) {
            throw new Error(data.msg || 'Chyba při změně pořadí.');
        }

        location.reload();

    } catch (err) {
        alert(err.message);
        btn.disabled = false;
    }
});
</script>

<?php include "../includes/footer.php"; ?>