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
 * Načtení stránky včetně vazby na pracoviště.
 *
 * Stránku lze spravovat pouze tehdy, když:
 * - owner_type je workplace,
 * - owner_id odpovídá pracovišti,
 * - existuje vazba v workplace_pages.
 */
$stmt = $conn->prepare("
    SELECT
        p.id AS page_id,
        p.title AS page_title,
        p.template,
        p.status AS page_status,
        p.owner_id,

        wp.id AS workplace_page_id,
        wp.workplace_id,
        wp.is_active AS workplace_page_active,

        w.name AS workplace_name,
        w.slug AS workplace_slug,
        w.main_page_id

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
$mainPageId = (int)($page['main_page_id'] ?? 0);
$isMainPage = $pageId === $mainPageId;

/*
 * Blokový obsah používáme pouze u univerzálních stránek.
 */
if (!in_array($page['template'], ['home', 'universal'], true)) {
    header(
        "Location: page_edit.php?id="
        . $workplacePageId
    );
    exit;
}

/*
 * Načtení bloků stránky.
 */
$stmt = $conn->prepare("
    SELECT
        pb.*,
        bt.name AS block_type_name,
        bt.icon AS block_type_icon

    FROM page_blocks pb

    LEFT JOIN block_types bt
        ON pb.type = bt.type

    WHERE pb.page_id = ?

    ORDER BY
        pb.sort_order ASC,
        pb.id ASC
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

            <h2 class="mb-1">
                Bloky stránky
            </h2>

            <p class="text-muted mb-0">

                <strong>
                    <?= e($page['workplace_name']) ?>
                </strong>

                <span class="mx-1">—</span>

                <?= e($page['page_title']) ?>

                <?php if ($isMainPage): ?>
                    <span class="badge bg-primary ms-2">
                        Hlavní stránka
                    </span>
                <?php endif; ?>

            </p>

        </div>

        <div class="responsive-actions">

            <a
                href="block_add.php?page_id=<?= $pageId ?>"
                class="btn btn-primary">

                <i class="bi bi-plus-circle me-1"></i>
                Přidat blok

            </a>

            <a
                href="page_edit.php?id=<?= $workplacePageId ?>"
                class="btn btn-outline-secondary">

                <i class="bi bi-pencil me-1"></i>
                Upravit stránku

            </a>

            <a
                href="pages.php?id=<?= $workplaceId ?>"
                class="btn btn-outline-secondary">

                <i class="bi bi-arrow-left me-1"></i>
                Zpět na stránky pracoviště

            </a>

        </div>

    </div>

    <?php if (isset($_GET['created'])): ?>
        <div class="alert alert-success">
            Blok byl přidán.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success">
            Blok byl upraven.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">
            Blok byl odstraněn.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger">
            Operaci s blokem se nepodařilo dokončit.
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">

        <div class="table-responsive">

            <table class="table table-bordered table-striped table-hover align-middle mb-0 bg-white">

                <thead class="table-light">

                    <tr>

                        <th
                            width="70"
                            class="text-center">

                            ID

                        </th>

                        <th>
                            Blok
                        </th>

                        <th width="180">
                            Typ
                        </th>

                        <th
                            width="100"
                            class="text-center">

                            Pořadí

                        </th>

                        <th
                            width="100"
                            class="text-center">

                            Stav

                        </th>

                        <th
                            width="190"
                            class="text-center">

                            Akce

                        </th>

                    </tr>

                </thead>

                <tbody>

                    <?php while ($block = $result->fetch_assoc()): ?>

                        <tr>

                            <td class="text-center">
                                <?= (int)$block['id'] ?>
                            </td>

                            <td>

                                <div class="fw-semibold">

                                    <?= e(
                                        $block['title']
                                        ?: 'Bez názvu'
                                    ) ?>

                                </div>

                                <?php if (!empty($block['subtitle'])): ?>

                                    <div class="text-muted small">

                                        <?= e($block['subtitle']) ?>

                                    </div>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?php if (!empty($block['block_type_icon'])): ?>

                                    <i class="<?= e(
                                        $block['block_type_icon']
                                    ) ?> me-1"></i>

                                <?php endif; ?>

                                <?= e(
                                    $block['block_type_name']
                                    ?: $block['type']
                                ) ?>

                                <div class="text-muted small">

                                    <code>
                                        <?= e($block['type']) ?>
                                    </code>

                                </div>

                            </td>

                            <td class="text-center">

                                <?= (int)$block['sort_order'] ?>

                            </td>

                            <td class="text-center">

                                <?php if (
                                    (int)$block['is_active'] === 1
                                ): ?>

                                    <a
                                        href="block_status.php?id=<?= (int)$block['id'] ?>&to=0"
                                        class="badge bg-success text-white text-decoration-none"
                                        title="Kliknutím skrýt blok">

                                        aktivní

                                    </a>

                                <?php else: ?>

                                    <a
                                        href="block_status.php?id=<?= (int)$block['id'] ?>&to=1"
                                        class="badge bg-secondary text-white text-decoration-none"
                                        title="Kliknutím aktivovat blok">

                                        skrytý

                                    </a>

                                <?php endif; ?>

                            </td>

                            <td class="text-center text-nowrap">

                                <div
                                    class="btn-group me-1"
                                    role="group">

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-secondary btn-block-move"
                                        data-id="<?= (int)$block['id'] ?>"
                                        data-dir="up"
                                        title="Posunout nahoru">

                                        <i class="bi bi-arrow-up"></i>

                                    </button>

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-secondary btn-block-move"
                                        data-id="<?= (int)$block['id'] ?>"
                                        data-dir="down"
                                        title="Posunout dolů">

                                        <i class="bi bi-arrow-down"></i>

                                    </button>

                                </div>

                                <a
                                    href="block_edit.php?id=<?= (int)$block['id'] ?>"
                                    class="btn btn-sm btn-primary"
                                    title="Upravit blok">

                                    <i class="bi bi-pencil"></i>

                                </a>

                                <a
                                    href="block_delete.php?id=<?= (int)$block['id'] ?>"
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

                            <td
                                colspan="6"
                                class="text-center text-muted p-4">

                                Tato stránka zatím nemá žádné bloky.

                                <div class="mt-3">

                                    <a
                                        href="block_add.php?page_id=<?= $pageId ?>"
                                        class="btn btn-primary">

                                        <i class="bi bi-plus-circle me-1"></i>
                                        Přidat první blok

                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<script>
document.addEventListener('click', async function (event) {
    const button = event.target.closest('.btn-block-move');

    if (!button) {
        return;
    }

    button.disabled = true;

    try {
        const response = await fetch('block_reorder.php', {
            method: 'POST',
            headers: {
                'Content-Type':
                    'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body:
                'id='
                + encodeURIComponent(button.dataset.id)
                + '&dir='
                + encodeURIComponent(button.dataset.dir)
        });

        const text = await response.text();

        let data;

        try {
            data = JSON.parse(text);
        } catch (error) {
            throw new Error(
                'Server nevrátil platnou odpověď:\n'
                + text.slice(0, 300)
            );
        }

        if (!data.ok) {
            throw new Error(
                data.msg
                || 'Chyba při změně pořadí.'
            );
        }

        window.location.reload();

    } catch (error) {
        alert(error.message);
        button.disabled = false;
    }
});
</script>

<?php include "../includes/footer.php"; ?>