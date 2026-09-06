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
    FROM workplace_categories
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$category = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$category) {
    header("Location: list.php");
    exit;
}

$msg = "";

$name = (string)($category['name'] ?? '');
$slug = (string)($category['slug'] ?? '');
$sort_order = (int)($category['sort_order'] ?? 10);
$is_active = (int)($category['is_active'] ?? 1);

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

        $msg = "Název kategorie je povinný.";

    } elseif ($slug === '') {

        $msg = "Z názvu se nepodařilo vygenerovat slug.";

    } else {

        /*
         * Kontrola duplicity slugu.
         */
        $check = $conn->prepare("
            SELECT id
            FROM workplace_categories
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

        $checkRes = $check->get_result();

        $exists = $checkRes
            && $checkRes->num_rows > 0;

        $check->close();

        if ($exists) {

            $msg = "Kategorie s tímto názvem už existuje.";

        } else {

            try {

                $stmt = $conn->prepare("
                    UPDATE workplace_categories
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
                    "Upravena kategorie pracovišť '$name'"
                );

                header(
                    "Location: list.php?updated=1"
                );

                exit;

            } catch (mysqli_sql_exception $e) {

                if ($e->getCode() === 1062) {

                    $msg = "Kategorie s tímto názvem už existuje.";

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
                        Upravit kategorii pracovišť
                    </h2>

                    <p class="text-muted mb-0">
                        <?= htmlspecialchars($category['name'] ?? '') ?>
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
                    <?= htmlspecialchars($msg) ?>
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
                            Název kategorie *
                        </label>

                        <input
                            type="text"
                            name="name"
                            id="name"
                            class="form-control"
                            required
                            value="<?= htmlspecialchars($name) ?>"
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
                            value="<?= htmlspecialchars($slug) ?>"
                            disabled
                        >

                        <div class="form-text">
                            Slug není potřeba upravovat ručně.
                        </div>

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