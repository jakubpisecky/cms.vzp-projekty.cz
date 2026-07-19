<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('workplaces');

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: list.php");
    exit;
}

/*
 * Načtení pracoviště
 */
$stmt = $conn->prepare("
    SELECT *
    FROM workplaces
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$workplace = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$workplace) {
    header("Location: list.php");
    exit;
}

$mainPageId = (int)($workplace['main_page_id'] ?? 0);
$errors = [];

/*
 * Výchozí hodnoty
 */
$name = $workplace['name'] ?? '';
$shortName = $workplace['short_name'] ?? '';
$slug = $workplace['slug'] ?? '';

$locationId = (int)($workplace['location_id'] ?? 0);
$typeId = (int)($workplace['type_id'] ?? 0);

$perex = $workplace['perex'] ?? '';
$thumbnail = $workplace['thumbnail'] ?? '';
$logo = $workplace['logo'] ?? '';

$email = $workplace['email'] ?? '';
$phone = $workplace['phone'] ?? '';

$building = $workplace['building'] ?? '';
$floor = $workplace['floor'] ?? '';
$address = $workplace['address'] ?? '';

$latitude = $workplace['latitude'] ?? '';
$longitude = $workplace['longitude'] ?? '';

$metaTitle = $workplace['meta_title'] ?? '';
$metaDescription = $workplace['meta_description'] ?? '';

$status = $workplace['status'] ?? 'draft';
$sortOrder = (int)($workplace['sort_order'] ?? 10);
$isActive = (int)($workplace['is_active'] ?? 1);

/*
 * Lokality
 */
$locations = [];

$res = $conn->query("
    SELECT id, name
    FROM workplace_locations
    WHERE is_active = 1
       OR id = " . (int)$locationId . "
    ORDER BY sort_order ASC, name ASC
");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $locations[] = $row;
    }
}

/*
 * Typy pracovišť
 */
$types = [];

$res = $conn->query("
    SELECT id, name
    FROM workplace_types
    WHERE is_active = 1
       OR id = " . (int)$typeId . "
    ORDER BY sort_order ASC, name ASC
");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $types[] = $row;
    }
}

/*
 * Kategorie
 */
$categories = [];

$res = $conn->query("
    SELECT id, name
    FROM workplace_categories
    WHERE is_active = 1
    ORDER BY sort_order ASC, name ASC
");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $categories[] = $row;
    }
}

/*
 * Odbornosti
 */
$specializations = [];

$res = $conn->query("
    SELECT id, name
    FROM workplace_specializations
    WHERE is_active = 1
    ORDER BY sort_order ASC, name ASC
");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $specializations[] = $row;
    }
}

/*
 * Aktuálně vybrané kategorie
 */
$selectedCategories = [];

$stmt = $conn->prepare("
    SELECT category_id
    FROM workplace_category
    WHERE workplace_id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $selectedCategories[] = (int)$row['category_id'];
}

$stmt->close();

/*
 * Aktuálně vybrané odbornosti
 */
$selectedSpecializations = [];

$stmt = $conn->prepare("
    SELECT specialization_id
    FROM workplace_specialization
    WHERE workplace_id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $selectedSpecializations[] = (int)$row['specialization_id'];
}

$stmt->close();

/*
 * Uložení
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $shortName = trim($_POST['short_name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');

    $locationId = intval($_POST['location_id'] ?? 0);
    $typeId = intval($_POST['type_id'] ?? 0);

    $perex = trim($_POST['perex'] ?? '');
    $thumbnail = trim($_POST['thumbnail'] ?? '');
    $logo = trim($_POST['logo'] ?? '');

    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    $building = trim($_POST['building'] ?? '');
    $floor = trim($_POST['floor'] ?? '');
    $address = trim($_POST['address'] ?? '');

    $latitude = trim($_POST['latitude'] ?? '');
    $longitude = trim($_POST['longitude'] ?? '');

    $metaTitle = trim($_POST['meta_title'] ?? '');
    $metaDescription = trim($_POST['meta_description'] ?? '');

    $status = trim($_POST['status'] ?? 'draft');
    $sortOrder = intval($_POST['sort_order'] ?? 10);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $selectedCategories = $_POST['category_ids'] ?? [];
    $selectedSpecializations = $_POST['specialization_ids'] ?? [];

    if (!is_array($selectedCategories)) {
        $selectedCategories = [];
    }

    if (!is_array($selectedSpecializations)) {
        $selectedSpecializations = [];
    }

    $selectedCategories = array_values(
        array_unique(
            array_filter(
                array_map('intval', $selectedCategories)
            )
        )
    );

    $selectedSpecializations = array_values(
        array_unique(
            array_filter(
                array_map('intval', $selectedSpecializations)
            )
        )
    );

    if ($slug === '' && $name !== '') {
        $slug = slugify($name);
    } else {
        $slug = slugify($slug);
    }

    if (!in_array($status, ['draft', 'published', 'archived'], true)) {
        $status = 'draft';
    }

    if ($sortOrder <= 0) {
        $sortOrder = 10;
    }

    /*
     * Validace
     */
    if ($name === '') {
        $errors[] = 'Vyplňte název pracoviště.';
    }

    if ($slug === '') {
        $errors[] = 'Nepodařilo se vytvořit platný slug.';
    }

    if ($locationId <= 0) {
        $errors[] = 'Vyberte lokalitu Motol nebo Homolka.';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'E-mail nemá platný formát.';
    }

    if (
        $latitude !== ''
        && !is_numeric(str_replace(',', '.', $latitude))
    ) {
        $errors[] = 'Zeměpisná šířka musí být číslo.';
    }

    if (
        $longitude !== ''
        && !is_numeric(str_replace(',', '.', $longitude))
    ) {
        $errors[] = 'Zeměpisná délka musí být číslo.';
    }

    /*
     * Kontrola lokality
     */
    if (!$errors) {
        $stmt = $conn->prepare("
            SELECT id
            FROM workplace_locations
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $locationId);
        $stmt->execute();
        $locationExists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$locationExists) {
            $errors[] = 'Vybraná lokalita neexistuje.';
        }
    }

    /*
     * Kontrola typu
     */
    if (!$errors && $typeId > 0) {
        $stmt = $conn->prepare("
            SELECT id
            FROM workplace_types
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $typeId);
        $stmt->execute();
        $typeExists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$typeExists) {
            $errors[] = 'Vybraný typ pracoviště neexistuje.';
        }
    }

    /*
     * Kontrola duplicity slugu pracoviště
     */
    if (!$errors) {
        $stmt = $conn->prepare("
            SELECT id
            FROM workplaces
            WHERE slug = ?
              AND id != ?
            LIMIT 1
        ");
        $stmt->bind_param("si", $slug, $id);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($exists) {
            $errors[] = 'Jiné pracoviště už tento slug používá.';
        }
    }

    /*
     * Kontrola interního slugu stránky
     */
    $pageSlug = 'pracoviste-' . $slug;

    if (!$errors && $mainPageId > 0) {
        $stmt = $conn->prepare("
            SELECT id
            FROM pages
            WHERE slug = ?
              AND id != ?
            LIMIT 1
        ");
        $stmt->bind_param("si", $pageSlug, $mainPageId);
        $stmt->execute();
        $pageExists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($pageExists) {
            $errors[] = 'Interní slug hlavní stránky už používá jiná stránka.';
        }
    }

    if (!$errors) {
        $latitudeDb = $latitude !== ''
            ? (float)str_replace(',', '.', $latitude)
            : null;

        $longitudeDb = $longitude !== ''
            ? (float)str_replace(',', '.', $longitude)
            : null;

        $typeIdDb = $typeId > 0
            ? $typeId
            : null;

        $pageStatus = $status === 'published'
            ? 'published'
            : 'draft';

        $template = 'universal';

        $conn->begin_transaction();

        try {
            /*
             * 1. Pracoviště
             */
            $stmt = $conn->prepare("
                UPDATE workplaces
                SET location_id = ?,
                    type_id = ?,
                    name = ?,
                    short_name = ?,
                    slug = ?,
                    perex = ?,
                    thumbnail = ?,
                    logo = ?,
                    email = ?,
                    phone = ?,
                    building = ?,
                    floor = ?,
                    address = ?,
                    latitude = ?,
                    longitude = ?,
                    meta_title = ?,
                    meta_description = ?,
                    status = ?,
                    sort_order = ?,
                    is_active = ?,
                    updated_at = NOW()
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->bind_param(
                "iisssssssssssddsssiii",
                $locationId,
                $typeIdDb,
                $name,
                $shortName,
                $slug,
                $perex,
                $thumbnail,
                $logo,
                $email,
                $phone,
                $building,
                $floor,
                $address,
                $latitudeDb,
                $longitudeDb,
                $metaTitle,
                $metaDescription,
                $status,
                $sortOrder,
                $isActive,
                $id
            );

            $stmt->execute();
            $stmt->close();

            /*
             * 2. Hlavní stránka
             */
            if ($mainPageId > 0) {
                $stmt = $conn->prepare("
                    UPDATE pages
                    SET title = ?,
                        slug = ?,
                        meta_title = ?,
                        meta_description = ?,
                        status = ?,
                        template = ?,
                        published_at = CASE
                            WHEN ? = 'published'
                                AND published_at IS NULL
                            THEN NOW()

                            WHEN ? = 'draft'
                            THEN NULL

                            ELSE published_at
                        END
                    WHERE id = ?
                    LIMIT 1
                ");

                $stmt->bind_param(
                    "ssssssssi",
                    $name,
                    $pageSlug,
                    $metaTitle,
                    $metaDescription,
                    $pageStatus,
                    $template,
                    $pageStatus,
                    $pageStatus,
                    $mainPageId
                );

                $stmt->execute();
                $stmt->close();
            }

            /*
             * 3. Kategorie
             */
            $stmt = $conn->prepare("
                DELETE FROM workplace_category
                WHERE workplace_id = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            if (!empty($selectedCategories)) {
                $stmt = $conn->prepare("
                    INSERT INTO workplace_category
                        (workplace_id, category_id)
                    VALUES
                        (?, ?)
                ");

                foreach ($selectedCategories as $categoryId) {
                    $stmt->bind_param(
                        "ii",
                        $id,
                        $categoryId
                    );
                    $stmt->execute();
                }

                $stmt->close();
            }

            /*
             * 4. Odbornosti
             */
            $stmt = $conn->prepare("
                DELETE FROM workplace_specialization
                WHERE workplace_id = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            if (!empty($selectedSpecializations)) {
                $stmt = $conn->prepare("
                    INSERT INTO workplace_specialization
                        (workplace_id, specialization_id)
                    VALUES
                        (?, ?)
                ");

                foreach ($selectedSpecializations as $specializationId) {
                    $stmt->bind_param(
                        "ii",
                        $id,
                        $specializationId
                    );
                    $stmt->execute();
                }

                $stmt->close();
            }

            $conn->commit();

            logAction("Upraveno pracoviště '$name'");

            header("Location: list.php?updated=1");
            exit;

        } catch (Throwable $e) {
            $conn->rollback();

            error_log(
                'Workplace update error: ' . $e->getMessage()
            );

            $errors[] = 'Pracoviště se nepodařilo uložit: '
                . $e->getMessage();
        }
    }
}

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="admin-page-header-content mb-4">

        <div>
            <h2 class="mb-1">Upravit pracoviště</h2>

            <p class="text-muted mb-0">
                <?= e($workplace['name']) ?>
            </p>
        </div>

        <div class="d-flex gap-2">

            <?php if ($mainPageId > 0): ?>
                <a
                    href="../pages/blocks.php?page_id=<?= $mainPageId ?>"
                    class="btn btn-outline-primary">

                    <i class="bi bi-grid me-1"></i>
                    Správa bloků
                </a>
            <?php endif; ?>

            <a
                href="list.php"
                class="btn btn-outline-secondary">

                <i class="bi bi-arrow-left me-1"></i>
                Zpět na pracoviště
            </a>

        </div>

    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <div><?= e($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post">

        <div class="row g-4">

            <div class="col-xl-8">

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white">
                        <strong>Základní údaje</strong>
                    </div>

                    <div class="card-body">

                        <div class="mb-3">
                            <label class="form-label">
                                Název pracoviště *
                            </label>

                            <input
                                type="text"
                                name="name"
                                class="form-control"
                                required
                                value="<?= e($name) ?>">
                        </div>

                        <div class="row">

                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Zkrácený název
                                </label>

                                <input
                                    type="text"
                                    name="short_name"
                                    class="form-control"
                                    value="<?= e($shortName) ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Slug
                                </label>

                                <input
                                    type="text"
                                    name="slug"
                                    class="form-control"
                                    value="<?= e($slug) ?>">
                            </div>

                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                Krátký popis do výpisu
                            </label>

                            <textarea
                                name="perex"
                                class="form-control"
                                rows="4"><?= e($perex) ?></textarea>
                        </div>

                        <div class="row">

                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Lokalita *
                                </label>

                                <select
                                    name="location_id"
                                    class="form-select"
                                    required>

                                    <option value="">
                                        — Vyberte lokalitu —
                                    </option>

                                    <?php foreach ($locations as $location): ?>
                                        <option
                                            value="<?= (int)$location['id'] ?>"
                                            <?= $locationId === (int)$location['id']
                                                ? 'selected'
                                                : '' ?>>

                                            <?= e($location['name']) ?>
                                        </option>
                                    <?php endforeach; ?>

                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Typ pracoviště
                                </label>

                                <select
                                    name="type_id"
                                    class="form-select">

                                    <option value="">
                                        — Bez určení typu —
                                    </option>

                                    <?php foreach ($types as $type): ?>
                                        <option
                                            value="<?= (int)$type['id'] ?>"
                                            <?= $typeId === (int)$type['id']
                                                ? 'selected'
                                                : '' ?>>

                                            <?= e($type['name']) ?>
                                        </option>
                                    <?php endforeach; ?>

                                </select>
                            </div>

                        </div>

                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white">
                        <strong>Kontakty a umístění</strong>
                    </div>

                    <div class="card-body">

                        <div class="row">

                            <div class="col-md-6 mb-3">
                                <label class="form-label">E-mail</label>

                                <input
                                    type="email"
                                    name="email"
                                    class="form-control"
                                    value="<?= e($email) ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Telefon</label>

                                <input
                                    type="text"
                                    name="phone"
                                    class="form-control"
                                    value="<?= e($phone) ?>">
                            </div>

                        </div>

                        <div class="row">

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Budova</label>

                                <input
                                    type="text"
                                    name="building"
                                    class="form-control"
                                    value="<?= e($building) ?>">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Patro</label>

                                <input
                                    type="text"
                                    name="floor"
                                    class="form-control"
                                    value="<?= e($floor) ?>">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Adresa</label>

                                <input
                                    type="text"
                                    name="address"
                                    class="form-control"
                                    value="<?= e($address) ?>">
                            </div>

                        </div>

                        <div class="row">

                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Zeměpisná šířka
                                </label>

                                <input
                                    type="text"
                                    name="latitude"
                                    class="form-control"
                                    value="<?= e((string)$latitude) ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Zeměpisná délka
                                </label>

                                <input
                                    type="text"
                                    name="longitude"
                                    class="form-control"
                                    value="<?= e((string)$longitude) ?>">
                            </div>

                        </div>

                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white">
                        <strong>Obrázky</strong>
                    </div>

                    <div class="card-body">

                        <div class="mb-3">
                            <label class="form-label">
                                Náhledový obrázek
                            </label>

                            <div class="input-group">

                                <input
                                    type="text"
                                    name="thumbnail"
                                    id="workplace_thumbnail"
                                    class="form-control"
                                    value="<?= e($thumbnail) ?>"
                                    readonly>

                                <button
                                    type="button"
                                    class="btn btn-outline-secondary open-picker"
                                    data-bs-toggle="modal"
                                    data-bs-target="#imagePickerModal"
                                    data-iframe-src="/pictures/list.php?picker=thumb"
                                    data-target-input="#workplace_thumbnail"
                                    data-target-preview="#workplace-thumbnail-preview">

                                    Vybrat...
                                </button>

                            </div>

                            <div
                                class="mt-2"
                                id="workplace-thumbnail-preview"
                                <?= $thumbnail === ''
                                    ? 'style="display:none;"'
                                    : '' ?>>

                                <img
                                    src="<?= e($thumbnail) ?>"
                                    alt="Náhled"
                                    class="img-fluid mt-2 rounded border"
                                    style="max-height:150px;">
                            </div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label">
                                Logo pracoviště
                            </label>

                            <div class="input-group">

                                <input
                                    type="text"
                                    name="logo"
                                    id="workplace_logo"
                                    class="form-control"
                                    value="<?= e($logo) ?>"
                                    readonly>

                                <button
                                    type="button"
                                    class="btn btn-outline-secondary open-picker"
                                    data-bs-toggle="modal"
                                    data-bs-target="#imagePickerModal"
                                    data-iframe-src="/pictures/list.php?picker=thumb"
                                    data-target-input="#workplace_logo"
                                    data-target-preview="#workplace-logo-preview">

                                    Vybrat...
                                </button>

                            </div>

                            <div
                                class="mt-2"
                                id="workplace-logo-preview"
                                <?= $logo === ''
                                    ? 'style="display:none;"'
                                    : '' ?>>

                                <img
                                    src="<?= e($logo) ?>"
                                    alt="Logo"
                                    class="img-fluid mt-2 rounded border"
                                    style="max-height:150px;">
                            </div>
                        </div>

                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white">
                        <strong>SEO</strong>
                    </div>

                    <div class="card-body">

                        <div class="mb-3">
                            <label class="form-label">
                                Meta title
                            </label>

                            <input
                                type="text"
                                name="meta_title"
                                class="form-control"
                                value="<?= e($metaTitle) ?>">
                        </div>

                        <div class="mb-0">
                            <label class="form-label">
                                Meta description
                            </label>

                            <textarea
                                name="meta_description"
                                class="form-control"
                                rows="3"><?= e($metaDescription) ?></textarea>
                        </div>

                    </div>
                </div>

            </div>

            <div class="col-xl-4">

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white">
                        <strong>Publikace</strong>
                    </div>

                    <div class="card-body">

                        <div class="mb-3">
                            <label class="form-label">Stav</label>

                            <select
                                name="status"
                                class="form-select">

                                <option
                                    value="draft"
                                    <?= $status === 'draft'
                                        ? 'selected'
                                        : '' ?>>

                                    Koncept
                                </option>

                                <option
                                    value="published"
                                    <?= $status === 'published'
                                        ? 'selected'
                                        : '' ?>>

                                    Publikováno
                                </option>

                                <option
                                    value="archived"
                                    <?= $status === 'archived'
                                        ? 'selected'
                                        : '' ?>>

                                    Archivováno
                                </option>

                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Pořadí</label>

                            <input
                                type="number"
                                name="sort_order"
                                class="form-control"
                                value="<?= (int)$sortOrder ?>">
                        </div>

                        <div class="form-check form-switch">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="is_active"
                                id="is_active"
                                value="1"
                                <?= $isActive ? 'checked' : '' ?>>

                            <label
                                class="form-check-label"
                                for="is_active">

                                Aktivní
                            </label>
                        </div>

                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white">
                        <strong>Kategorie</strong>
                    </div>

                    <div class="card-body">

                        <?php if (!$categories): ?>
                            <div class="text-muted">
                                Nejsou vytvořené žádné kategorie.
                            </div>
                        <?php else: ?>

                            <?php foreach ($categories as $category): ?>
                                <?php
                                $categoryId = (int)$category['id'];
                                ?>

                                <div class="form-check mb-2">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="category_ids[]"
                                        id="category_<?= $categoryId ?>"
                                        value="<?= $categoryId ?>"
                                        <?= in_array(
                                            $categoryId,
                                            $selectedCategories,
                                            true
                                        ) ? 'checked' : '' ?>>

                                    <label
                                        class="form-check-label"
                                        for="category_<?= $categoryId ?>">

                                        <?= e($category['name']) ?>
                                    </label>

                                </div>
                            <?php endforeach; ?>

                        <?php endif; ?>

                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white">
                        <strong>Odbornosti</strong>
                    </div>

                    <div class="card-body">

                        <?php if (!$specializations): ?>
                            <div class="text-muted">
                                Odbornosti zatím nejsou vytvořené.
                            </div>
                        <?php else: ?>

                            <?php foreach ($specializations as $specialization): ?>
                                <?php
                                $specializationId =
                                    (int)$specialization['id'];
                                ?>

                                <div class="form-check mb-2">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="specialization_ids[]"
                                        id="specialization_<?= $specializationId ?>"
                                        value="<?= $specializationId ?>"
                                        <?= in_array(
                                            $specializationId,
                                            $selectedSpecializations,
                                            true
                                        ) ? 'checked' : '' ?>>

                                    <label
                                        class="form-check-label"
                                        for="specialization_<?= $specializationId ?>">

                                        <?= e($specialization['name']) ?>
                                    </label>

                                </div>
                            <?php endforeach; ?>

                        <?php endif; ?>

                    </div>
                </div>

            </div>

        </div>

        <div class="card shadow-sm border-0">

            <div class="card-body">

                <button
                    type="submit"
                    class="btn btn-success">

                    <i class="bi bi-save me-1"></i>
                    Uložit změny
                </button>

                <?php if ($mainPageId > 0): ?>
                    <a
                        href="../pages/blocks.php?page_id=<?= $mainPageId ?>"
                        class="btn btn-outline-primary">

                        Správa bloků
                    </a>
                <?php endif; ?>

                <a
                    href="list.php"
                    class="btn btn-outline-secondary">

                    Zrušit
                </a>

            </div>

        </div>

    </form>

</div>

<?php include "../includes/footer.php"; ?>