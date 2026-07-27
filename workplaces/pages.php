<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('workplaces');

$workplaceId = intval($_GET['id'] ?? 0);

if ($workplaceId <= 0) {
    header("Location: list.php");
    exit;
}

/*
 * Načtení pracoviště
 */
$stmt = $conn->prepare("
    SELECT
        w.id,
        w.name,
        w.main_page_id,
        wl.name AS location_name,
        wt.name AS type_name
    FROM workplaces w
    INNER JOIN workplace_locations wl
        ON wl.id = w.location_id
    LEFT JOIN workplace_types wt
        ON wt.id = w.type_id
    WHERE w.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $workplaceId);
$stmt->execute();
$workplace = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$workplace) {
    header("Location: list.php");
    exit;
}

$mainPageId = (int)($workplace['main_page_id'] ?? 0);

/*
 * Stránky pracoviště
 */
$stmt = $conn->prepare("
    SELECT
        wp.id AS workplace_page_id,
        wp.parent_id AS workplace_parent_id,
        wp.show_in_menu AS workplace_show_in_menu,
        wp.sort_order,
        wp.is_active AS workplace_page_active,

        p.id AS page_id,
        p.title,
        p.slug,
        p.status,
        p.template,
        p.show_breadcrumbs,
        p.updated_at,

        (
            SELECT COUNT(*)
            FROM page_blocks pb
            WHERE pb.page_id = p.id
        ) AS blocks_count,

        (
            SELECT COUNT(*)
            FROM page_blocks pb
            WHERE pb.page_id = p.id
              AND pb.is_active = 1
        ) AS active_blocks_count

    FROM workplace_pages wp

    INNER JOIN pages p
        ON p.id = wp.page_id

    WHERE wp.workplace_id = ?

    ORDER BY
        wp.sort_order ASC,
        wp.id ASC
");
$stmt->bind_param("i", $workplaceId);
$stmt->execute();
$pages = $stmt->get_result();
$stmt->close();

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="admin-page-header-content mb-4">

        <div>
            <h2 class="mb-1">Stránky pracoviště</h2>

            <p class="text-muted mb-0">
                <?= e($workplace['name']) ?>

                <?php if (!empty($workplace['location_name'])): ?>
                    — <?= e($workplace['location_name']) ?>
                <?php endif; ?>

                <?php if (!empty($workplace['type_name'])): ?>
                    — <?= e($workplace['type_name']) ?>
                <?php endif; ?>
            </p>
        </div>

        <div class="d-flex flex-wrap gap-2">

            <a
                href="edit.php?id=<?= $workplaceId ?>"
                class="btn btn-outline-secondary">

                <i class="bi bi-pencil me-1"></i>
                Upravit pracoviště
            </a>

            <a
                href="list.php"
                class="btn btn-outline-secondary">

                <i class="bi bi-arrow-left me-1"></i>
                Zpět na pracoviště
            </a>

            <a
                href="page_add.php?id=<?= $workplaceId ?>"
                class="btn btn-success">

                <i class="bi bi-plus-lg me-1"></i>
                Přidat stránku
            </a>

        </div>

    </div>

    <?php if (isset($_GET['created'])): ?>
        <div class="alert alert-success">
            Stránka byla vytvořena.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success">
            Stránka byla upravena.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">
            Stránka byla smazána.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['moved'])): ?>
        <div class="alert alert-success">
            Pořadí stránek bylo změněno.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['delete_error'])): ?>
        <div class="alert alert-danger">
            <?php if ($_GET['delete_error'] === 'main_page'): ?>
                Hlavní stránku pracoviště nelze samostatně smazat.
            <?php else: ?>
                Stránku se nepodařilo smazat.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">

        <?php if (!$pages || $pages->num_rows === 0): ?>

            <div class="text-center text-muted py-5">

                <i class="bi bi-file-earmark-text fs-1 d-block mb-3"></i>

                <p class="mb-3">
                    Pracoviště zatím nemá žádné stránky.
                </p>

                <a
                    href="page_add.php?id=<?= $workplaceId ?>"
                    class="btn btn-success">

                    <i class="bi bi-plus-lg me-1"></i>
                    Přidat první stránku
                </a>

            </div>

        <?php else: ?>

            <div class="table-responsive">

                <table class="table table-bordered table-striped table-hover align-middle mb-0 bg-white">

                    <thead>
                        <tr>
                            <th style="width: 90px;">
                                Pořadí
                            </th>

                            <th>
                                Název
                            </th>

                            <th>
                                Typ stránky
                            </th>

                            <th class="text-center">
                                Bloky
                            </th>

                            <th class="text-center">
                                V navigaci
                            </th>

                            <th class="text-center">
                                Stav
                            </th>

                            <th class="text-end" style="width: 470px;">
                                Akce
                            </th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php while ($page = $pages->fetch_assoc()): ?>

                            <?php
                            $pageId = (int)$page['page_id'];
                            $workplacePageId =
                                (int)$page['workplace_page_id'];

                            $isMainPage = $pageId === $mainPageId;
                            ?>

                            <tr>

                                <td class="text-center">
                                    <?= (int)$page['sort_order'] ?>
                                </td>

                                <td>

                                    <div class="d-flex align-items-center gap-2">

                                        <strong>
                                            <?= e($page['title']) ?>
                                        </strong>

                                        <?php if ($isMainPage): ?>
                                            <span class="badge bg-primary">
                                                Hlavní stránka
                                            </span>
                                        <?php endif; ?>

                                    </div>

                                    <div class="small mt-1">
                                        <code><?= e($page['slug']) ?></code>
                                    </div>

                                    <?php if (!empty($page['updated_at'])): ?>
                                        <div class="text-muted small mt-1">
                                            Upraveno:
                                            <?= e(date(
                                                'j. n. Y H:i',
                                                strtotime($page['updated_at'])
                                            )) ?>
                                        </div>
                                    <?php endif; ?>

                                </td>

                                <td>
                                    <?php
                                    $templateLabels = [
                                        'universal' => 'Univerzální',
                                        'default' => 'Obecná',
                                        'articles' => 'Výpis článků',
                                        'gallery' => 'Fotogalerie',
                                        'contact' => 'Kontaktní formulář',
                                        'home' => 'Homepage',
                                    ];
                                    ?>

                                    <?= e(
                                        $templateLabels[$page['template']]
                                        ?? $page['template']
                                        ?? 'Neuvedeno'
                                    ) ?>
                                </td>

                                <td class="text-center">

                                    <span class="badge bg-light text-dark">
                                        <?= (int)$page['active_blocks_count'] ?>
                                        /
                                        <?= (int)$page['blocks_count'] ?>
                                    </span>

                                </td>

                                <td class="text-center">

                                    <?php if (
                                        (int)$page['workplace_show_in_menu'] === 1
                                    ): ?>

                                        <span class="badge bg-success">
                                            Ano
                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-secondary">
                                            Ne
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td class="text-center">

                                    <?php if (
                                        $page['status'] === 'published'
                                        && (int)$page['workplace_page_active'] === 1
                                    ): ?>

                                        <span class="badge bg-success">
                                            Publikováno
                                        </span>

                                    <?php elseif (
                                        $page['status'] === 'draft'
                                    ): ?>

                                        <span class="badge bg-warning text-dark">
                                            Koncept
                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-secondary">
                                            Neaktivní
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td class="text-end text-nowrap">

                                    <a
                                        href="page_move.php?id=<?= $workplacePageId ?>&workplace_id=<?= $workplaceId ?>&dir=up"
                                        class="btn btn-sm btn-outline-secondary"
                                        title="Posunout nahoru">

                                        <i class="bi bi-arrow-up"></i>
                                    </a>

                                    <a
                                        href="page_move.php?id=<?= $workplacePageId ?>&workplace_id=<?= $workplaceId ?>&dir=down"
                                        class="btn btn-sm btn-outline-secondary"
                                        title="Posunout dolů">

                                        <i class="bi bi-arrow-down"></i>
                                    </a>

                                    <a
                                        href="page_blocks.php?page_id=<?= $pageId ?>"
                                        class="btn btn-sm btn-outline-primary">

                                        <i class="bi bi-grid me-1"></i>
                                        Bloky
                                    </a>

                                    <a
                                        href="page_edit.php?id=<?= $workplacePageId ?>"
                                        class="btn btn-sm btn-outline-secondary">

                                        Upravit
                                    </a>

                                    <?php if (!$isMainPage): ?>

                                        <a
                                            href="page_delete.php?id=<?= $workplacePageId ?>"
                                            class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm(
                                                'Opravdu chcete stránku smazat? Smažou se také všechny její bloky.'
                                            );">

                                            Smazat
                                        </a>

                                    <?php else: ?>

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            disabled
                                            title="Hlavní stránku nelze samostatně smazat">

                                            Smazat
                                        </button>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</div>

<?php include "../includes/footer.php"; ?>