<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('pages');

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '') {

        $msg = 'Název navigace je povinný.';

    } else {

        $stmt = $conn->prepare("
            INSERT INTO navigation
            (
                name,
                is_active
            )
            VALUES (?, ?)
        ");

        $stmt->bind_param(
            "si",
            $name,
            $is_active
        );

        $stmt->execute();

        $navigationId = $stmt->insert_id;

        $stmt->close();

        logAction(
            "Vytvořena navigace '$name'"
        );

        /*
         * Rovnou přejdeme na správu položek.
         */
        header(
            "Location: items.php?id="
            . (int)$navigationId
            . "&created=1"
        );

        exit;
    }
}

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="row justify-content-center">

        <div class="col-md-12">

            <h2 class="mb-4">
                Přidat navigaci
            </h2>

            <?php if ($msg): ?>

                <div class="alert alert-danger">
                    <?= e($msg) ?>
                </div>

            <?php endif; ?>

            <form
                method="post"
                class="bg-white p-4 rounded shadow-sm"
            >

                <div class="row mb-4">

                    <div class="col-md-8">

                        <label class="form-label">
                            Název *
                        </label>

                        <input
                            type="text"
                            name="name"
                            class="form-control"
                            required
                            value="<?= e($_POST['name'] ?? '') ?>"
                            placeholder="např. Chirurgická klinika"
                        >

                        <div class="form-text">
                            Interní název navigace pro administraci.
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
                                checked
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

                    <button class="btn btn-success">
                        <i class="fa fa-save me-1"></i>
                        Uložit a přidat položky
                    </button>

                    <a
                        href="list.php"
                        class="btn btn-secondary"
                    >
                        Zpět
                    </a>

                </div>

            </form>

        </div>

    </div>

</div>

<?php include "../includes/footer.php"; ?>