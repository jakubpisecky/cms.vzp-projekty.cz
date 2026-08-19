<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('pages');

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
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

$stmt->bind_param("i", $id);
$stmt->execute();

$navigation = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$navigation) {
    header("Location: list.php");
    exit;
}

$msg = '';

$name = (string)$navigation['name'];
$isActive = (int)$navigation['is_active'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '') {

        $msg = 'Název navigace je povinný.';

    } else {

        $stmt = $conn->prepare("
            UPDATE navigation
            SET
                name = ?,
                is_active = ?,
                updated_at = NOW()
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "sii",
            $name,
            $isActive,
            $id
        );

        if ($stmt->execute()) {

            $stmt->close();

            logAction(
                "Upravena navigace '$name'"
            );

            header("Location: list.php?updated=1");
            exit;
        }

        $stmt->close();

        $msg = 'Navigaci se nepodařilo uložit.';
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
                        Upravit navigaci
                    </h2>

                    <p class="text-muted mb-0">
                        <?= e($navigation['name']) ?>
                    </p>
                </div>

                <div class="responsive-actions">

                    <a
                        href="items.php?id=<?= (int)$id ?>"
                        class="btn btn-outline-primary"
                    >
                        <i class="bi bi-list-nested me-1"></i>
                        Položky navigace
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

            <?php if ($msg !== ''): ?>

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

                        <label
                            for="name"
                            class="form-label"
                        >
                            Název *
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
                            Interní název navigace používaný v administraci.
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
                                <?= $isActive === 1 ? 'checked' : '' ?>
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
                        Zrušit
                    </a>

                </div>

            </form>

        </div>

    </div>

</div>

<?php include "../includes/footer.php"; ?>