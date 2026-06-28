<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('team');

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: list.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT *
    FROM team_members
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$member) {
    header("Location: list.php");
    exit;
}

$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name        = trim($_POST['name'] ?? '');
    $position    = trim($_POST['position'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $photo       = trim($_POST['photo'] ?? '');

    $sort_order = (int)($_POST['sort_order'] ?? 10);

    if ($sort_order <= 0) {
        $sort_order = 10;
    }

    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($name !== '') {

        $stmt = $conn->prepare("
            UPDATE team_members
            SET name = ?,
                position = ?,
                description = ?,
                email = ?,
                phone = ?,
                photo = ?,
                sort_order = ?,
                is_active = ?,
                updated_at = NOW()
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "ssssssiii",
            $name,
            $position,
            $description,
            $email,
            $phone,
            $photo,
            $sort_order,
            $is_active,
            $id
        );

        $stmt->execute();
        $stmt->close();

        logAction("Upraven pracovník '$name'");

        header("Location: list.php?updated=1");
        exit;

    } else {
        $msg = "Jméno pracovníka je povinné.";
    }

    $member['name']        = $name;
    $member['position']    = $position;
    $member['description'] = $description;
    $member['email']       = $email;
    $member['phone']       = $phone;
    $member['photo']       = $photo;
    $member['sort_order']  = $sort_order;
    $member['is_active']   = $is_active;
}

include "../includes/header.php";
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-12">

            <h2 class="mb-4">Upravit pracovníka</h2>

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
                               value="<?= e($member['name'] ?? '') ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label">Pozice</label>
                        <input type="text"
                               name="position"
                               class="form-control"
                               value="<?= e($member['position'] ?? '') ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label">E-mail</label>
                        <input type="email"
                               name="email"
                               class="form-control"
                               value="<?= e($member['email'] ?? '') ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label">Telefon</label>
                        <input type="text"
                               name="phone"
                               class="form-control"
                               value="<?= e($member['phone'] ?? '') ?>">
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
                                   value="<?= e($member['photo'] ?? '') ?>"
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

                        <div class="mt-2"
                             id="photo-preview"
                             style="<?= !empty($member['photo']) ? '' : 'display:none;' ?>">
                            <img src="<?= e($member['photo'] ?? '') ?>"
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
                                  rows="6"><?= e($member['description'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label">Pořadí</label>
                        <input type="number"
                               name="sort_order"
                               class="form-control"
                               value="<?= e($member['sort_order'] ?? '10') ?>">
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
                                   <?= ((int)($member['is_active'] ?? 1) === 1) ? 'checked' : '' ?>>

                            <label class="form-check-label" for="is_active">
                                Aktivní
                            </label>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between">
                    <button class="btn btn-success">
                        <i class="bi bi-save me-1"></i> Uložit změny
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