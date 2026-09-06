<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('workplaces');

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: list.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT
        id,
        name,
        slug,
        sort_order,
        is_active
    FROM workplace_specializations
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$specialization = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$specialization) {
    header("Location: list.php");
    exit;
}

$msg = "";

$name = (string)($specialization['name'] ?? '');
$slug = (string)($specialization['slug'] ?? '');
$sort_order = (int)($specialization['sort_order'] ?? 10);
$is_active = (int)($specialization['is_active'] ?? 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $slug = slugify($name);

    $sort_order = (int)(
        $_POST['sort_order'] ?? 10
    );

    if ($sort_order <= 0) {
        $sort_order = 10;
    }

    $is_active = isset($_POST['is_active'])
        ? 1
        : 0;

    if ($name === '') {

        $msg = "Název odbornosti je povinný.";

    } elseif ($slug === '') {

        $msg = "Z názvu se nepodařilo vygenerovat slug.";

    } else {

        $check = $conn->prepare("
            SELECT id
            FROM workplace_specializations
            WHERE slug = ?
              AND id <> ?
            LIMIT 1
        ");

        $check->bind_param(
            "si",
            $slug,
            $id
        );

        $check->execute();

        $exists = $check
            ->get_result()
            ->fetch_assoc();

        $check->close();

        if ($exists) {

            $msg = "Odbornost s tímto názvem už existuje.";

        } else {

            try {

                $stmt = $conn->prepare("
                    UPDATE workplace_specializations
                    SET
                        name = ?,
                        slug = ?,
                        sort_order = ?,
                        is_active = ?
                    WHERE id = ?
                    LIMIT 1
                ");

                $stmt->bind_param(
                    "ssiii",
                    $name,
                    $slug,
                    $sort_order,
                    $is_active,
                    $id
                );

                $stmt->execute();
                $stmt->close();

                logAction(
                    "Upravena odbornost pracovišť '$name'"
                );

                header(
                    "Location: list.php?updated=1"
                );

                exit;

            } catch (mysqli_sql_exception $e) {

                if ($e->getCode() === 1062) {

                    $msg = "Odbornost s tímto názvem už existuje.";

                } else {

                    $msg = "Chyba při ukládání do databáze.";
                }
            }
        }
    }
}

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="row justify-content-center">

        <div class="col-md-12">

            <div class="admin-page-header-content mb-4">

                <div>
                    <h2 class="mb-1">
                        Upravit odbornost
                    </h2>

                    <p class="text-muted mb-0">
                        <?= e($specialization['name'] ?? '') ?>
                    </p>
                </div>

                <div class="responsive-actions">

                    <a
                        href="list.php"
                        class="btn btn-outline-secondary"
                    >
                        <i class="bi bi-arrow-left me-1"></i>
                        Zpět
                    </a>

                </div>

            </div>

            <?php if (!empty($msg)): ?>

                <div class="alert alert-danger">
                    <?= e($msg) ?>
                </div>

            <?php endif; ?>

            <form
                method="post"
                class="bg-white p-4 rounded shadow-sm"
                novalidate
            >

                <div class="row mb-4">

                    <div class="col-md-8">

                        <label
                            for="name"
                            class="form-label"
                        >
                            Název odbornosti *
                        </label>

                        <input
                            type="text"
                            name="name"
                            id="name"
                            class="form-control"
                            required
                            value="<?= e($name) ?>"
                        >

                        <div class="form-text">
                            Slug se automaticky přegeneruje podle názvu.
                        </div>

                    </div>

                </div>

                <div class="row mb-4">

                    <div class="col-md-8">

                        <label class="form-label">
                            Slug
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            value="<?= e($slug) ?>"
                            disabled
                        >

                    </div>

                </div>

                <div class="row mb-4">

                    <div class="col-md-8">

                        <label
                            for="sort_order"
                            class="form-label"
                        >
                            Pořadí
                        </label>

                        <input
                            type="number"
                            name="sort_order"
                            id="sort_order"
                            class="form-control"
                            min="1"
                            step="1"
                            value="<?= (int)$sort_order ?>"
                        >

                    </div>

                </div>

                <div class="row mb-4">

                    <div class="col-md-8">

                        <div class="form-check form-switch">

                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="is_active"
                                id="is_active"
                                value="1"
                                <?= $is_active === 1
                                    ? 'checked'
                                    : '' ?>
                            >

                            <label
                                class="form-check-label"
                                for="is_active"
                            >
                                Aktivní
                            </label>

                        </div>

                    </div>

                </div>

                <div class="d-flex justify-content-between">

                    <button
                        type="submit"
                        class="btn btn-success"
                    >
                        <i class="bi bi-save me-1"></i>
                        Uložit změny
                    </button>

                    <a
                        href="list.php"
                        class="btn btn-secondary"
                    >
                        <i class="bi bi-arrow-left me-1"></i>
                        Zpět
                    </a>

                </div>

            </form>

        </div>

    </div>

</div>

<?php include "../includes/footer.php"; ?>