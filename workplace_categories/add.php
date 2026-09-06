<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('workplaces');

$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $slug = slugify($name);

    $is_active = isset($_POST['is_active']) ? 1 : 0;

    /*
     * Automatické pořadí.
     */
    $res = $conn->query("
        SELECT
            COALESCE(MAX(sort_order), 0) + 10 AS next_order
        FROM workplace_categories
    ");

    $row = $res
        ? $res->fetch_assoc()
        : null;

    $sort_order = (int)(
        $row['next_order'] ?? 10
    );

    if ($sort_order <= 0) {
        $sort_order = 10;
    }

    /*
     * Validace.
     */
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
            LIMIT 1
        ");

        $check->bind_param(
            "s",
            $slug
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
                    INSERT INTO workplace_categories
                    (
                        name,
                        slug,
                        sort_order,
                        is_active
                    )
                    VALUES
                    (
                        ?, ?, ?, ?
                    )
                ");

                $stmt->bind_param(
                    "ssii",
                    $name,
                    $slug,
                    $sort_order,
                    $is_active
                );

                $stmt->execute();
                $stmt->close();

                logAction(
                    "Vytvořena kategorie pracovišť '$name'"
                );

                header(
                    "Location: list.php?created=1"
                );

                exit;

            } catch (mysqli_sql_exception $e) {

                /*
                 * Duplicate entry.
                 */
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
                        Přidat kategorii pracovišť
                    </h2>

                    <p class="text-muted mb-0">
                        Nová kategorie pro třídění pracovišť
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
                            value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                        >

                        <div class="form-text">
                            Slug se vygeneruje automaticky z názvu.
                        </div>

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
                                <?= !isset($_POST['is_active']) && $_SERVER['REQUEST_METHOD'] !== 'POST'
                                    ? 'checked'
                                    : (isset($_POST['is_active']) ? 'checked' : '') ?>
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
                        Uložit
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