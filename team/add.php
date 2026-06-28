<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('team');

$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name        = trim($_POST['name'] ?? '');
    $position    = trim($_POST['position'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $photo       = trim($_POST['photo'] ?? '');

    $res = $conn->query("
        SELECT COALESCE(MAX(sort_order), 0) + 10 AS next_order
        FROM team_members
    ");

    $row = $res ? $res->fetch_assoc() : null;

    $sort_order = (int)($row['next_order'] ?? 10);

    if ($sort_order <= 0) {
        $sort_order = 10;
    }

    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($name !== '') {

        $stmt = $conn->prepare("
            INSERT INTO team_members
            (
                name,
                position,
                description,
                email,
                phone,
                photo,
                sort_order,
                is_active
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?, ?, ?
            )
        ");

        $stmt->bind_param(
            "ssssssii",
            $name,
            $position,
            $description,
            $email,
            $phone,
            $photo,
            $sort_order,
            $is_active
        );

        $stmt->execute();
        $stmt->close();

        logAction("Vytvořen pracovník '$name'");

        header("Location: list.php?created=1");
        exit;

    } else {
        $msg = "Jméno pracovníka je povinné.";
    }
}

include "../includes/header.php";
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-12">

            <h2 class="mb-4">Přidat pracovníka</h2>

            <?php if ($msg): ?>
                <div class="alert alert-danger"><?= e($msg) ?></div>
            <?php endif; ?>

            <form method="post" class="bg-white p-4 rounded shadow-sm">

                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label">Jméno *</label>
                        <input type="text"
                               name="name"
                               class="form-control"
                               required
                               value="<?= e($_POST['name'] ?? '') ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label">Pozice</label>
                        <input type="text"
                               name="position"
                               class="form-control"
                               value="<?= e($_POST['position'] ?? '') ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label">E-mail</label>
                        <input type="email"
                               name="email"
                               class="form-control"
                               value="<?= e($_POST['email'] ?? '') ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label">Telefon</label>
                        <input type="text"
                               name="phone"
                               class="form-control"
                               value="<?= e($_POST['phone'] ?? '') ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label">Fotografie</label>

                        <div class="input-group">
                            <input type="text"
                                   name="photo"
                                   id="photo"
                                   class="form-control"
                                   placeholder="URL fotografie"
                                   value="<?= e($_POST['photo'] ?? '') ?>"
                                   readonly>

                            <button type="button"
                                    class="btn btn-outline-secondary open-picker"
                                    data-bs-toggle="modal"
                                    data-bs-target="#imagePickerModal"
                                    data-iframe-src="/pictures/list.php?picker=thumb"
                                    data-target-input="#photo"
                                    data-target-preview="#photo-preview">
                                Vybrat...
                            </button>
                        </div>

                        <div class="mt-2" id="photo-preview" style="display:none;">
                            <img src=""
                                 alt="Náhled"
                                 class="img-fluid mt-2 rounded border"
                                 style="max-height:150px;">
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-12">
                        <label class="form-label">Popis</label>
                        <textarea name="description"
                                  class="form-control"
                                  rows="6"><?= e($_POST['description'] ?? '') ?></textarea>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="form-check form-switch">
                            <input class="form-check-input"
                                   type="checkbox"
                                   name="is_active"
                                   id="is_active"
                                   value="1"
                                   checked>

                            <label class="form-check-label" for="is_active">
                                Aktivní
                            </label>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between">
                    <button class="btn btn-success">
                        <i class="bi bi-save me-1"></i> Uložit
                    </button>

                    <a href="list.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Zpět
                    </a>
                </div>

            </form>

        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>