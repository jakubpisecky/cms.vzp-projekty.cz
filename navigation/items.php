<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('pages');

$navigationId = (int)($_GET['id'] ?? 0);

if ($navigationId <= 0) {
    header("Location: list.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT
        id,
        name,
        is_active
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

$created = isset($_GET['created']);
$updated = isset($_GET['updated']);
$deleted = isset($_GET['deleted']);

/*
 * Položky navigace.
 */
$stmt = $conn->prepare("
    SELECT
        ni.id,
        ni.navigation_id,
        ni.parent_id,
        ni.title,
        ni.page_id,
        ni.url,
        ni.target,
        ni.sort_order,
        ni.is_active,
        p.title AS page_title,
        p.slug AS page_slug

    FROM navigation_items ni

    LEFT JOIN pages p
        ON p.id = ni.page_id

    WHERE ni.navigation_id = ?

    ORDER BY
        COALESCE(ni.parent_id, ni.id) ASC,
        CASE
            WHEN ni.parent_id IS NULL THEN 0
            ELSE 1
        END ASC,
        ni.sort_order ASC,
        ni.id ASC
");

$stmt->bind_param("i", $navigationId);
$stmt->execute();

$result = $stmt->get_result();
$stmt->close();

/*
 * Kvůli přehlednému výpisu si data uložíme do pole.
 */
$items = [];

while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

$total = count($items);

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="admin-page-header-content mb-4">

        <div>

            <h2 class="mb-1">
                Položky navigace
                <small class="text-muted">
                    (<?= (int)$total ?>)
                </small>
            </h2>

            <p class="text-muted mb-0">
                <?= e($navigation['name']) ?>
            </p>

        </div>

        <div class="responsive-actions">

            <a
                href="item_add.php?navigation_id=<?= (int)$navigationId ?>"
                class="btn btn-success"
            >
                <i class="bi bi-plus-circle me-1"></i>
                Přidat položku
            </a>

            <a
                href="edit.php?id=<?= (int)$navigationId ?>"
                class="btn btn-outline-primary"
            >
                <i class="bi bi-pencil-square me-1"></i>
                Upravit navigaci
            </a>

            <a
                href="list.php"
                class="btn btn-outline-secondary"
            >
                <i class="bi bi-arrow-left me-1"></i>
                Zpět
            </a>

        </div>

    </div>


    <?php if ($created): ?>

        <div class="alert alert-success">
            Položka navigace byla vytvořena.
        </div>

    <?php endif; ?>


    <?php if ($updated): ?>

        <div class="alert alert-success">
            Položka navigace byla upravena.
        </div>

    <?php endif; ?>


    <?php if ($deleted): ?>

        <div class="alert alert-success">
            Položka navigace byla odstraněna.
        </div>

    <?php endif; ?>


    <div class="card shadow-sm border-0">

        <div class="table-responsive">

            <table class="table table-bordered table-striped table-hover align-middle mb-0 bg-white">

                <thead class="table-light">

                    <tr>

                        <th
                            width="70"
                            class="text-center"
                        >
                            ID
                        </th>

                        <th>
                            Název
                        </th>

                        <th>
                            Cíl
                        </th>

                        <th
                            width="100"
                            class="text-center"
                        >
                            Pořadí
                        </th>

                        <th
                            width="100"
                            class="text-center"
                        >
                            Stav
                        </th>

                        <th
                            width="180"
                            class="text-center"
                        >
                            Akce
                        </th>

                    </tr>

                </thead>

                <tbody>

                    <?php if (!$items): ?>

                        <tr>

                            <td
                                colspan="6"
                                class="text-center text-muted p-4"
                            >
                                Tato navigace zatím nemá žádné položky.
                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($items as $item): ?>

                            <?php
                            $isChild = !empty($item['parent_id']);

                            $isInternal = !empty($item['page_id']);

                            if ($isInternal) {
                                $targetLabel = $item['page_title']
                                    ? 'Stránka: ' . $item['page_title']
                                    : 'Stránka nebyla nalezena';
                            } else {
                                $targetLabel = $item['url'] ?: '—';
                            }
                            ?>

                            <tr>

                                <td class="text-center">
                                    <?= (int)$item['id'] ?>
                                </td>

                                <td>

                                    <div
                                        class="<?= $isChild ? 'ps-4' : '' ?>"
                                    >

                                        <?php if ($isChild): ?>

                                            <span class="text-muted me-2">
                                                └
                                            </span>

                                        <?php endif; ?>

                                        <span class="fw-semibold">
                                            <?= e($item['title']) ?>
                                        </span>

                                    </div>

                                    <?php if ($isChild): ?>

                                        <div class="text-muted small ps-4">
                                            Podřízená položka
                                        </div>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <?php if ($isInternal): ?>

                                        <div>
                                            <i class="bi bi-file-earmark-text me-1"></i>
                                            <?= e($targetLabel) ?>
                                        </div>

                                    <?php else: ?>

                                        <div>
                                            <i class="bi bi-box-arrow-up-right me-1"></i>

                                            <a
                                                href="<?= e($item['url']) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                <?= e($item['url']) ?>
                                            </a>
                                        </div>

                                    <?php endif; ?>

                                    <?php if (($item['target'] ?? '_self') === '_blank'): ?>

                                        <div class="text-muted small">
                                            Otevírá se v novém okně
                                        </div>

                                    <?php endif; ?>

                                </td>

                                <td class="text-center">
                                    <?= (int)$item['sort_order'] ?>
                                </td>

                                <td class="text-center">

                                    <?php if ((int)$item['is_active'] === 1): ?>

                                        <span class="badge bg-success">
                                            Aktivní
                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-secondary">
                                            Neaktivní
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td class="text-center text-nowrap">

                                    <div
                                        class="btn-group me-1"
                                        role="group"
                                    >

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-secondary btn-item-move"
                                            data-id="<?= (int)$item['id'] ?>"
                                            data-dir="up"
                                            title="Posunout nahoru"
                                        >
                                            ↑
                                        </button>

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-secondary btn-item-move"
                                            data-id="<?= (int)$item['id'] ?>"
                                            data-dir="down"
                                            title="Posunout dolů"
                                        >
                                            ↓
                                        </button>

                                    </div>

                                    <a
                                        href="item_edit.php?id=<?= (int)$item['id'] ?>"
                                        class="btn btn-sm btn-primary"
                                        title="Upravit položku"
                                    >
                                        <i class="bi bi-pencil-square"></i>
                                    </a>

                                    <a
                                        href="item_delete.php?id=<?= (int)$item['id'] ?>"
                                        class="btn btn-sm btn-danger"
                                        onclick="return confirm('Opravdu chcete tuto položku navigace odstranit?');"
                                        title="Smazat položku"
                                    >
                                        <i class="bi bi-trash"></i>
                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>


<script>
document.addEventListener('click', async function (event) {

    const button = event.target.closest('.btn-item-move');

    if (!button) {
        return;
    }

    button.disabled = true;

    try {

        const response = await fetch('item_reorder.php', {
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
        } catch (e) {

            throw new Error(
                'Server nevrátil platný JSON:\n'
                + text.slice(0, 300)
            );
        }

        if (!data.ok) {

            throw new Error(
                data.msg
                || 'Pořadí položky se nepodařilo změnit.'
            );
        }

        location.reload();

    } catch (error) {

        alert(error.message);

        button.disabled = false;
    }

});
</script>

<?php include "../includes/footer.php"; ?>