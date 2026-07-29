<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('team');

$id = (int)($_GET['id'] ?? 0);

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

if (!$stmt) {
    header("Location: list.php");
    exit;
}

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
    $slug        = trim($_POST['slug'] ?? '');
    $position    = trim($_POST['position'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $photo       = trim($_POST['photo'] ?? '');

    $sort_order = (int)($_POST['sort_order'] ?? 10);

    if ($sort_order <= 0) {
        $sort_order = 10;
    }

    $show_detail = isset($_POST['show_detail']) ? 1 : 0;
    $is_active   = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '') {
        $msg = "Jméno člena týmu je povinné.";
    }

    if (
        $msg === ''
        && $email !== ''
        && !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $msg = "Zadaný e-mail není platný.";
    }

    /*
     * Pokud není slug vyplněný, vytvoří se automaticky ze jména.
     */
    if ($slug === '' && $name !== '') {
        $slug = slugify($name);
    } else {
        $slug = slugify($slug);
    }

    if ($msg === '' && $slug === '') {
        $msg = "Nepodařilo se vytvořit platnou URL adresu člena týmu.";
    }

    /*
     * Kontrola unikátního slugu.
     * Aktuálně upravovaný člen se z kontroly vyloučí.
     */
    if ($msg === '') {
        $stmt = $conn->prepare("
            SELECT id
            FROM team_members
            WHERE slug = ?
              AND id != ?
            LIMIT 1
        ");

        if (!$stmt) {
            $msg = "Nepodařilo se ověřit URL adresu člena týmu.";
        } else {
            $stmt->bind_param("si", $slug, $id);
            $stmt->execute();

            $existingMember = $stmt->get_result()->fetch_assoc();

            $stmt->close();

            if ($existingMember) {
                $msg = "Jiný člen týmu už používá tuto URL adresu.";
            }
        }
    }

    /*
     * Uložení změn.
     */
    if ($msg === '') {
        $stmt = $conn->prepare("
            UPDATE team_members
            SET
                name = ?,
                slug = ?,
                position = ?,
                description = ?,
                email = ?,
                phone = ?,
                photo = ?,
                sort_order = ?,
                show_detail = ?,
                is_active = ?,
                updated_at = NOW()
            WHERE id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            $msg = "Člena týmu se nepodařilo uložit.";
        } else {
            $stmt->bind_param(
                "sssssssiiii",
                $name,
                $slug,
                $position,
                $description,
                $email,
                $phone,
                $photo,
                $sort_order,
                $show_detail,
                $is_active,
                $id
            );

            if ($stmt->execute()) {
                $stmt->close();

                logAction("Upraven člen týmu '$name'");

                header("Location: list.php?updated=1");
                exit;
            }

            $stmt->close();

            $msg = "Při ukládání člena týmu došlo k chybě.";
        }
    }

    /*
     * Zachování zadaných hodnot při chybě formuláře.
     */
    $member['name']        = $name;
    $member['slug']        = $slug;
    $member['position']    = $position;
    $member['description'] = $description;
    $member['email']       = $email;
    $member['phone']       = $phone;
    $member['photo']       = $photo;
    $member['sort_order']  = $sort_order;
    $member['show_detail'] = $show_detail;
    $member['is_active']   = $is_active;
}

include "../includes/header.php";
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-12">

            <h2 class="mb-4">Upravit člena týmu</h2>

            <?php if ($msg !== ''): ?>
                <div class="alert alert-danger">
                    <?= e($msg) ?>
                </div>
            <?php endif; ?>

            <form method="post" class="bg-white p-4 rounded shadow-sm">

                <div class="row mb-3">
                    <div class="col-md-8">
                        <label for="name" class="form-label">
                            Jméno *
                        </label>

                        <input
                            type="text"
                            name="name"
                            id="name"
                            class="form-control"
                            required
                            value="<?= e($member['name'] ?? '') ?>"
                        >
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-8">
                        <label for="slug" class="form-label">
                            URL adresa
                        </label>

                        <input
                            type="text"
                            name="slug"
                            id="slug"
                            class="form-control"
                            placeholder="například jan-novak"
                            value="<?= e($member['slug'] ?? '') ?>"
                        >

                        <div class="form-text">
                            Slug se používá v URL detailu člena týmu.
                            Pokud pole ponecháte prázdné, vytvoří se automaticky ze jména.
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-8">
                        <label for="position" class="form-label">
                            Pozice
                        </label>

                        <input
                            type="text"
                            name="position"
                            id="position"
                            class="form-control"
                            value="<?= e($member['position'] ?? '') ?>"
                        >
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-8">
                        <label for="email" class="form-label">
                            E-mail
                        </label>

                        <input
                            type="email"
                            name="email"
                            id="email"
                            class="form-control"
                            value="<?= e($member['email'] ?? '') ?>"
                        >
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-8">
                        <label for="phone" class="form-label">
                            Telefon
                        </label>

                        <input
                            type="text"
                            name="phone"
                            id="phone"
                            class="form-control"
                            value="<?= e($member['phone'] ?? '') ?>"
                        >
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-8">
                        <label for="photo" class="form-label">
                            Fotografie
                        </label>

                        <div class="input-group">
                            <input
                                type="text"
                                name="photo"
                                id="photo"
                                class="form-control"
                                placeholder="URL fotografie"
                                value="<?= e($member['photo'] ?? '') ?>"
                                readonly
                            >

                            <button
                                type="button"
                                class="btn btn-outline-secondary open-picker"
                                data-bs-toggle="modal"
                                data-bs-target="#imagePickerModal"
                                data-iframe-src="/pictures/list.php?picker=thumb"
                                data-target-input="#photo"
                                data-target-preview="#photo-preview"
                            >
                                Vybrat…
                            </button>
                        </div>

                        <div
                            class="mt-2"
                            id="photo-preview"
                            <?= empty($member['photo']) ? 'style="display:none;"' : '' ?>
                        >
                            <img
                                src="<?= e($member['photo'] ?? '') ?>"
                                alt="Náhled fotografie"
                                class="img-fluid mt-2 rounded border"
                                style="max-height:150px;"
                            >
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-12">
                        <label for="description" class="form-label">
                            Popis
                        </label>

                        <textarea
                            name="description"
                            id="description"
                            class="form-control editor"
                            rows="10"
                        ><?= e($member['description'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-8">
                        <label for="sort_order" class="form-label">
                            Pořadí
                        </label>

                        <input
                            type="number"
                            name="sort_order"
                            id="sort_order"
                            class="form-control"
                            min="1"
                            step="1"
                            value="<?= e($member['sort_order'] ?? '10') ?>"
                        >
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-8">
                        <div class="form-check form-switch">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="show_detail"
                                id="show_detail"
                                value="1"
                                <?= ((int)($member['show_detail'] ?? 1) === 1) ? 'checked' : '' ?>
                            >

                            <label class="form-check-label" for="show_detail">
                                Povolit detail člena týmu
                            </label>
                        </div>

                        <div class="form-text">
                            Pokud je detail povolený, může karta člena odkazovat
                            na jeho samostatný profil.
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
                                <?= ((int)($member['is_active'] ?? 1) === 1) ? 'checked' : '' ?>
                            >

                            <label class="form-check-label" for="is_active">
                                Aktivní
                            </label>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-save me-1"></i>
                        Uložit změny
                    </button>

                    <a href="list.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i>
                        Zpět
                    </a>
                </div>

            </form>

        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>