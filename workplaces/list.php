<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('workplaces');

$locationId = intval($_GET['location_id'] ?? 0);
$typeId = intval($_GET['type_id'] ?? 0);
$status = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

$allowedStatuses = ['', 'draft', 'published', 'archived'];

if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

/*
 * Lokality pro filtr.
 */
$locations = [];

$res = $conn->query("
    SELECT id, name
    FROM workplace_locations
    WHERE is_active = 1
    ORDER BY sort_order ASC, name ASC
");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $locations[] = $row;
    }
}

/*
 * Typy pracovišť pro filtr.
 */
$types = [];

$res = $conn->query("
    SELECT id, name
    FROM workplace_types
    WHERE is_active = 1
    ORDER BY sort_order ASC, name ASC
");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $types[] = $row;
    }
}

/*
 * Sestavení filtru.
 */
$where = [];
$params = [];
$bindTypes = '';

if ($locationId > 0) {
    $where[] = 'w.location_id = ?';
    $params[] = $locationId;
    $bindTypes .= 'i';
}

if ($typeId > 0) {
    $where[] = 'w.type_id = ?';
    $params[] = $typeId;
    $bindTypes .= 'i';
}

if ($status !== '') {
    $where[] = 'w.status = ?';
    $params[] = $status;
    $bindTypes .= 's';
}

if ($search !== '') {
    $where[] = "
        (
            w.name LIKE ?
            OR w.short_name LIKE ?
            OR w.slug LIKE ?
            OR w.email LIKE ?
            OR w.phone LIKE ?
        )
    ";

    $searchLike = '%' . $search . '%';

    for ($i = 0; $i < 5; $i++) {
        $params[] = $searchLike;
        $bindTypes .= 's';
    }
}

$whereSql = $where
    ? 'WHERE ' . implode(' AND ', $where)
    : '';

$sql = "
    SELECT
        w.*,
        wl.name AS location_name,
        wt.name AS type_name,

        (
            SELECT COUNT(*)
            FROM workplace_category wc
            WHERE wc.workplace_id = w.id
        ) AS categories_count,

        (
            SELECT COUNT(*)
            FROM workplace_specialization ws
            WHERE ws.workplace_id = w.id
        ) AS specializations_count,

        (
            SELECT COUNT(*)
            FROM workplace_pages wp
            WHERE wp.workplace_id = w.id
              AND wp.is_active = 1
        ) AS pages_count

    FROM workplaces w

    INNER JOIN workplace_locations wl
        ON wl.id = w.location_id

    LEFT JOIN workplace_types wt
        ON wt.id = w.type_id

    $whereSql

    ORDER BY
        w.sort_order ASC,
        w.name ASC,
        w.id ASC
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($bindTypes, ...$params);
}

$stmt->execute();
$workplaces = $stmt->get_result();
$stmt->close();

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="admin-page-header-content mb-4">

        <div>
            <h2 class="mb-1">Pracoviště</h2>

            <p class="text-muted mb-0">
                Kliniky, oddělení, centra a další pracoviště
            </p>
        </div>

        <a href="add.php" class="btn btn-success">
            <i class="bi bi-plus-lg me-1"></i>
            Přidat pracoviště
        </a>

    </div>

    <?php if (isset($_GET['created'])): ?>
        <div class="alert alert-success">
            Pracoviště bylo vytvořeno.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success">
            Pracoviště bylo upraveno.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">
            Pracoviště bylo smazáno.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['delete_error'])): ?>
        <div class="alert alert-danger">
            Pracoviště se nepodařilo smazat.
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 mb-4">

        <div class="card-body">

            <form method="get">

                <div class="row g-3 align-items-end">

                    <div class="col-lg-3 col-md-6">

                        <label class="form-label">
                            Pracoviště nemocnice
                        </label>

                        <select
                            name="location_id"
                            class="form-select">

                            <option value="">
                                Všechna pracoviště
                            </option>

                            <?php foreach ($locations as $location): ?>
                                <option
                                    value="<?= (int)$location['id'] ?>"
                                    <?= $locationId === (int)$location['id']
                                        ? 'selected'
                                        : '' ?>>

                                    <?= e($location['name']) ?>

                                </option>
                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="col-lg-3 col-md-6">

                        <label class="form-label">
                            Typ
                        </label>

                        <select
                            name="type_id"
                            class="form-select">

                            <option value="">
                                Všechny typy
                            </option>

                            <?php foreach ($types as $type): ?>
                                <option
                                    value="<?= (int)$type['id'] ?>"
                                    <?= $typeId === (int)$type['id']
                                        ? 'selected'
                                        : '' ?>>

                                    <?= e($type['name']) ?>

                                </option>
                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="col-lg-2 col-md-6">

                        <label class="form-label">
                            Stav
                        </label>

                        <select
                            name="status"
                            class="form-select">

                            <option value="">
                                Všechny stavy
                            </option>

                            <option
                                value="published"
                                <?= $status === 'published'
                                    ? 'selected'
                                    : '' ?>>

                                Publikováno

                            </option>

                            <option
                                value="draft"
                                <?= $status === 'draft'
                                    ? 'selected'
                                    : '' ?>>

                                Koncept

                            </option>

                            <option
                                value="archived"
                                <?= $status === 'archived'
                                    ? 'selected'
                                    : '' ?>>

                                Archivováno

                            </option>

                        </select>

                    </div>

                    <div class="col-lg-3 col-md-6">

                        <label class="form-label">
                            Vyhledávání
                        </label>

                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            value="<?= e($search) ?>"
                            placeholder="Název, slug, telefon nebo e-mail">

                    </div>

                    <div class="col-lg-1 col-md-12">

                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                            title="Filtrovat">

                            <i class="bi bi-search"></i>

                        </button>

                    </div>

                </div>

                <?php if (
                    $locationId > 0
                    || $typeId > 0
                    || $status !== ''
                    || $search !== ''
                ): ?>

                    <div class="mt-3">

                        <a
                            href="list.php"
                            class="btn btn-sm btn-outline-secondary">

                            <i class="bi bi-x-lg me-1"></i>
                            Zrušit filtry

                        </a>

                    </div>

                <?php endif; ?>

            </form>

        </div>

    </div>

    <div class="card shadow-sm border-0">

        <?php if (!$workplaces || $workplaces->num_rows === 0): ?>

            <div class="text-center text-muted py-5">

                <i class="bi bi-hospital fs-1 d-block mb-3"></i>

                Nebyla nalezena žádná pracoviště.

            </div>

        <?php else: ?>

            <div class="table-responsive">

                <table class="table table-bordered table-striped table-hover align-middle mb-0 bg-white">

                    <thead>

                        <tr>
                            <th style="width: 70px;">
                                Pořadí
                            </th>

                            <th>
                                Název
                            </th>

                            <th>
                                Lokalita
                            </th>

                            <th>
                                Typ
                            </th>

                            <th class="text-center">
                                Kategorie
                            </th>

                            <th class="text-center">
                                Odbornosti
                            </th>

                            <th class="text-center">
                                Stránky
                            </th>

                            <th class="text-center">
                                Stav
                            </th>

                            <th
                                class="text-end"
                                style="width: 390px;">

                                Akce

                            </th>
                        </tr>

                    </thead>
                

                    <tbody>

                        <?php while ($row = $workplaces->fetch_assoc()): ?>

                            <tr>

                                <td class="text-center">
                                    <?= (int)$row['sort_order'] ?>
                                </td>

                                <td>

                                    <strong>
                                        <?= e($row['name']) ?>
                                    </strong>

                                    <?php if (!empty($row['short_name'])): ?>

                                        <div class="text-muted small">
                                            <?= e($row['short_name']) ?>
                                        </div>

                                    <?php endif; ?>

                                    <div class="small mt-1">
                                        <code><?= e($row['slug']) ?></code>
                                    </div>

                                </td>

                                <td>

                                    <span class="badge bg-primary">
                                        <?= e($row['location_name']) ?>
                                    </span>

                                </td>

                                <td>

                                    <?php if (!empty($row['type_name'])): ?>

                                        <?= e($row['type_name']) ?>

                                    <?php else: ?>

                                        <span class="text-muted">
                                            Neuvedeno
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td class="text-center">

                                    <span class="badge bg-light text-dark">
                                        <?= (int)$row['categories_count'] ?>
                                    </span>

                                </td>

                                <td class="text-center">

                                    <span class="badge bg-light text-dark">
                                        <?= (int)$row['specializations_count'] ?>
                                    </span>

                                </td>

                                <td class="text-center">

                                    <span class="badge bg-light text-dark">
                                        <?= (int)$row['pages_count'] ?>
                                    </span>

                                </td>

                                <td class="text-center">

                                    <?php if (
                                        $row['status'] === 'published'
                                        && (int)$row['is_active'] === 1
                                    ): ?>

                                        <span class="badge bg-success">
                                            Publikováno
                                        </span>

                                    <?php elseif (
                                        $row['status'] === 'draft'
                                    ): ?>

                                        <span class="badge bg-warning text-dark">
                                            Koncept
                                        </span>

                                    <?php elseif (
                                        $row['status'] === 'archived'
                                    ): ?>

                                        <span class="badge bg-secondary">
                                            Archivováno
                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-secondary">
                                            Neaktivní
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td class="text-end text-nowrap">

                                    <a
                                        href="pages.php?id=<?= (int)$row['id'] ?>"
                                        class="btn btn-sm btn-outline-primary">

                                        <i class="bi bi-file-earmark-text me-1"></i>
                                        Stránky

                                    </a>

                                    <a
                                        href="categories.php?id=<?= (int)$row['id'] ?>"
                                        class="btn btn-sm btn-outline-secondary">

                                        Kategorie

                                    </a>

                                    <a
                                        href="specializations.php?id=<?= (int)$row['id'] ?>"
                                        class="btn btn-sm btn-outline-secondary">

                                        Odbornosti

                                    </a>

                                    <a
                                        href="edit.php?id=<?= (int)$row['id'] ?>"
                                        class="btn btn-sm btn-outline-secondary">

                                        Upravit

                                    </a>

                                    <a
                                        href="delete.php?id=<?= (int)$row['id'] ?>"
                                        class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm(
                                            'Opravdu chcete pracoviště smazat?'
                                        );">

                                        Smazat

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

<?php include "../includes/footer.php"; ?>