<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('pages');

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
    $where[] = "n.name LIKE ?";
    $types .= "s";

    $params[] = '%' . $q . '%';
}

$whereSql = $where
    ? " WHERE " . implode(" AND ", $where)
    : "";

/*
 * Počet navigací.
 */
$sqlCount = "
    SELECT COUNT(*) AS cnt
    FROM navigation n
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
    $stmt->get_result()->fetch_assoc()['cnt']
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
 * Výpis navigací.
 */
$sqlData = "
    SELECT
        n.id,
        n.name,
        n.is_active,
        n.created_at,
        n.updated_at,
        COUNT(ni.id) AS items_count

    FROM navigation n

    LEFT JOIN navigation_items ni
        ON ni.navigation_id = n.id

    {$whereSql}

    GROUP BY
        n.id,
        n.name,
        n.is_active,
        n.created_at,
        n.updated_at

    ORDER BY
        n.name ASC,
        n.id ASC

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
                Navigace
                <small class="text-muted">
                    (<?= (int)$total ?>)
                </small>
            </h2>

            <p class="text-muted mb-0">
                Správa vlastních navigací a levých menu
            </p>

        </div>

        <div class="responsive-actions">

            <a
                href="add.php"
                class="btn btn-success"
            >
                <i class="bi bi-plus-circle me-1"></i>
                Přidat navigaci
            </a>

        </div>

    </div>


    <?php if ($created): ?>

        <div class="alert alert-success">
            Navigace byla vytvořena.
        </div>

    <?php endif; ?>


    <?php if ($updated): ?>

        <div class="alert alert-success">
            Navigace byla upravena.
        </div>

    <?php endif; ?>


    <?php if ($deleted): ?>

        <div class="alert alert-success">
            Navigace byla odstraněna.
        </div>

    <?php endif; ?>


    <!-- Vyhledávání -->
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
                        placeholder="Hledat podle názvu navigace..."
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


    <!-- Výpis -->
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

                        <th
                            width="140"
                            class="text-center"
                        >
                            Položek
                        </th>

                        <th
                            width="120"
                            class="text-center"
                        >
                            Stav
                        </th>

                        <th
                            width="190"
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
                                    ID navigace:
                                    <?= (int)$row['id'] ?>
                                </div>

                            </td>

                            <td class="text-center">

                                <span class="badge bg-light text-dark border">
                                    <?= (int)$row['items_count'] ?>
                                </span>

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

                                <a
                                    href="items.php?id=<?= (int)$row['id'] ?>"
                                    class="btn btn-sm btn-outline-primary"
                                    title="Položky navigace"
                                >
                                    <i class="bi bi-list-nested"></i>
                                </a>

                                <a
                                    href="edit.php?id=<?= (int)$row['id'] ?>"
                                    class="btn btn-sm btn-primary"
                                    title="Upravit navigaci"
                                >
                                    <i class="bi bi-pencil"></i>
                                </a>

                                <a
                                    href="delete.php?id=<?= (int)$row['id'] ?>"
                                    class="btn btn-sm btn-danger"
                                    onclick="return confirm('Opravdu chcete tuto navigaci smazat včetně všech jejích položek?');"
                                    title="Smazat navigaci"
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
                                Žádné navigace nebyly nalezeny.
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