<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('workplaces');

include "../includes/header.php";

$q = trim($_GET['q'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$created = isset($_GET['created']);
$updated = isset($_GET['updated']);
$deleted = isset($_GET['deleted']);

$where = [];
$types = '';
$params = [];

if ($q !== '') {
    $where[] = "(name LIKE ? OR slug LIKE ?)";
    $types .= "ss";

    $like = '%' . $q . '%';

    $params[] = $like;
    $params[] = $like;
}

$whereSql = $where
    ? " WHERE " . implode(" AND ", $where)
    : "";

/*
 * Počet kategorií.
 */
$sqlCount = "
    SELECT COUNT(*) AS cnt
    FROM workplace_categories
    {$whereSql}
";

$stmt = $conn->prepare($sqlCount);

if ($types !== '') {
    $stmt->bind_param(
        $types,
        ...$params
    );
}

$stmt->execute();

$total = (int)(
    $stmt->get_result()
        ->fetch_assoc()['cnt']
    ?? 0
);

$stmt->close();

/*
 * Stránkování.
 */
$totalPages = max(
    1,
    (int)ceil($total / $perPage)
);

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

$paramsUrl = $_GET;
unset($paramsUrl['page']);

$base = http_build_query($paramsUrl);

$base = $base
    ? ('?' . $base . '&')
    : '?';

/*
 * Data.
 */
$sqlData = "
    SELECT
        id,
        name,
        slug,
        sort_order,
        is_active
    FROM workplace_categories
    {$whereSql}
    ORDER BY
        sort_order ASC,
        id ASC
    LIMIT ?, ?
";

$stmt = $conn->prepare($sqlData);

$typesData = $types . "ii";

$paramsData = $params;
$paramsData[] = $offset;
$paramsData[] = $perPage;

$stmt->bind_param(
    $typesData,
    ...$paramsData
);

$stmt->execute();

$result = $stmt->get_result();

$stmt->close();
?>

<div class="container-fluid py-4">

    <div class="admin-page-header-content mb-4">

        <div>

            <h2 class="mb-1">
                Kategorie pracovišť
                <small class="text-muted">
                    (<?= (int)$total ?>)
                </small>
            </h2>

            <p class="text-muted mb-0">
                Správa kategorií pracovišť
            </p>

        </div>

        <div class="responsive-actions">

            <a
                href="add.php"
                class="btn btn-success"
            >
                <i class="bi bi-plus-circle me-1"></i>
                Přidat kategorii
            </a>

        </div>

    </div>


    <?php if ($created): ?>

        <div class="alert alert-success">
            Kategorie byla vytvořena.
        </div>

    <?php endif; ?>


    <?php if ($updated): ?>

        <div class="alert alert-success">
            Kategorie byla upravena.
        </div>

    <?php endif; ?>


    <?php if ($deleted): ?>

        <div class="alert alert-success">
            Kategorie byla odstraněna.
        </div>

    <?php endif; ?>


    <div class="card shadow-sm border-0 mb-4">

        <div class="card-body">

            <form
                method="get"
                class="row g-3"
            >

                <div class="col-lg-7">

                    <input
                        type="text"
                        name="q"
                        class="form-control"
                        placeholder="Hledat podle názvu nebo slugu..."
                        value="<?= e($q) ?>"
                    >

                </div>

                <div class="col-auto">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        <i class="bi bi-search me-1"></i>
                        Hledat
                    </button>

                </div>

                <?php if ($q !== ''): ?>

                    <div class="col-auto">

                        <a
                            href="list.php"
                            class="btn btn-outline-secondary"
                        >
                            <i class="bi bi-x-circle me-1"></i>
                            Zrušit
                        </a>

                    </div>

                <?php endif; ?>

            </form>

        </div>

    </div>


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
                            Název / slug
                        </th>

                        <th
                            width="100"
                            class="text-center"
                        >
                            Pořadí
                        </th>

                        <th
                            width="110"
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

                    <?php while ($row = $result->fetch_assoc()): ?>

                        <tr>

                            <td class="text-center">
                                <?= (int)$row['id'] ?>
                            </td>

                            <td>

                                <div class="fw-semibold">
                                    <?= e($row['name']) ?>
                                </div>

                                <div class="text-muted small">
                                    <?= e($row['slug']) ?>
                                </div>

                            </td>

                            <td class="text-center">
                                <?= (int)$row['sort_order'] ?>
                            </td>

                            <td class="text-center">

                                <?php if ((int)$row['is_active'] === 1): ?>

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

                                    <a
                                        href="move.php?id=<?= (int)$row['id'] ?>&dir=up"
                                        class="btn btn-sm btn-outline-secondary"
                                        title="Posunout nahoru"
                                    >
                                        ↑
                                    </a>

                                    <a
                                        href="move.php?id=<?= (int)$row['id'] ?>&dir=down"
                                        class="btn btn-sm btn-outline-secondary"
                                        title="Posunout dolů"
                                    >
                                        ↓
                                    </a>

                                </div>

                                <a
                                    href="edit.php?id=<?= (int)$row['id'] ?>"
                                    class="btn btn-sm btn-primary"
                                    title="Upravit"
                                >
                                    <i class="bi bi-pencil"></i>
                                </a>

                                <a
                                    href="delete.php?id=<?= (int)$row['id'] ?>"
                                    class="btn btn-sm btn-danger"
                                    onclick="return confirm('Opravdu chcete tuto kategorii smazat?');"
                                    title="Smazat"
                                >
                                    <i class="bi bi-trash"></i>
                                </a>

                            </td>

                        </tr>

                    <?php endwhile; ?>


                    <?php if ($result->num_rows === 0): ?>

                        <tr>

                            <td
                                colspan="5"
                                class="text-center text-muted p-4"
                            >
                                Žádné kategorie nebyly nalezeny.
                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


    <?php if (function_exists('renderPagination')): ?>

        <?php
        renderPagination(
            $totalPages,
            $page,
            $base
        );
        ?>

    <?php endif; ?>

</div>

<?php include "../includes/footer.php"; ?>