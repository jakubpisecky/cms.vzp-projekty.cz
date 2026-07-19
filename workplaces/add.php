<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('workplaces');

/*
 * Výchozí hodnoty formuláře
 */
$errors = [];

$name = '';
$shortName = '';
$slug = '';

$locationId = 0;
$typeId = 0;

$perex = '';
$thumbnail = '';
$logo = '';

$email = '';
$phone = '';

$building = '';
$floor = '';
$address = '';

$latitude = '';
$longitude = '';

$metaTitle = '';
$metaDescription = '';

$status = 'draft';
$sortOrder = 10;
$isActive = 1;

$selectedCategories = [];
$selectedSpecializations = [];

/*
 * Lokality – Motol / Homolka
 */
$locations = [];

$res = $conn->query("
    SELECT id, name
    FROM workplace_locations
    WHERE is_active = 1
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
 * Uložení nového pracoviště
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

    $selectedCategories = array_values(array_unique(array_filter(
        array_map('intval', $selectedCategories)
    )));

    $selectedSpecializations = array_values(array_unique(array_filter(
        array_map('intval', $selectedSpecializations)
    )));

    /*
     * Slug vytvoříme automaticky, pokud není zadaný.
     */
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
        $errors[] = 'Vyberte pracoviště nemocnice – Motol nebo Homolka.';
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
     * Ověření lokality.
     */
    if (!$errors) {
        $stmt = $conn->prepare("
            SELECT id
            FROM workplace_locations
            WHERE id = ?
              AND is_active = 1
            LIMIT 1
        ");
        $stmt->bind_param("i", $locationId);
        $stmt->execute();
        $locationExists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$locationExists) {
            $errors[] = 'Vybraná lokalita neexistuje nebo není aktivní.';
        }
    }

    /*
     * Ověření typu.
     */
    if (!$errors && $typeId > 0) {
        $stmt = $conn->prepare("
            SELECT id
            FROM workplace_types
            WHERE id = ?
              AND is_active = 1
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
     * Kontrola duplicity slugu pracoviště.
     */
    if (!$errors) {
        $stmt = $conn->prepare("
            SELECT id
            FROM workplaces
            WHERE slug = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($exists) {
            $errors[] = 'Pracoviště s tímto slugem už existuje.';
        }
    }

    /*
     * Uložení pracoviště a automatické vytvoření hlavní stránky.
     */
    if (!$errors) {

        $latitudeDb = $latitude !== ''
            ? (float)str_replace(',', '.', $latitude)
            : null;

        $longitudeDb = $longitude !== ''
            ? (float)str_replace(',', '.', $longitude)
            : null;

        /*
         * Tabulka pages zatím podporuje jen draft/published.
         * Archivované pracoviště dostane stránku ve stavu draft.
         */
        $pageStatus = $status === 'published'
            ? 'published'
            : 'draft';

        $publishedAt = $pageStatus === 'published'
            ? date('Y-m-d H:i:s')
            : null;

        /*
         * Interní slug stránky vytvoříme s prefixem,
         * aby se nemíchal s běžnými stránkami.
         *
         * Veřejná URL pracoviště se bude později skládat
         * podle workplaces.slug.
         */
        $pageSlug = 'pracoviste-' . $slug;

        $conn->begin_transaction();

        try {
            /*
             * 1. Vytvoření pracoviště.
             *
             * main_page_id zatím NULL, doplníme ho po vytvoření stránky.
             */
            $mainPageId = null;
$typeIdDb = $typeId > 0 ? $typeId : null;

$stmt = $conn->prepare("
    INSERT INTO workplaces
    (
        location_id,
        type_id,
        main_page_id,
        name,
        short_name,
        slug,
        perex,
        thumbnail,
        logo,
        email,
        phone,
        building,
        floor,
        address,
        latitude,
        longitude,
        meta_title,
        meta_description,
        status,
        sort_order,
        is_active
    )
    VALUES
    (
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?
    )
");

$stmt->bind_param(
    "iiisssssssssssddsssii",
    $locationId,
    $typeIdDb,
    $mainPageId,
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
    $isActive
);

$stmt->execute();
$workplaceId = (int)$stmt->insert_id;
$stmt->close();

            if ($workplaceId <= 0) {
                throw new RuntimeException(
                    'Nepodařilo se vytvořit pracoviště.'
                );
            }

            /*
             * 2. Vytvoření hlavní stránky v tabulce pages.
             */
            $parentId = 0;
            $pageContent = null;
            $showInMenu = 0;
            $showBreadcrumbs = 1;
            $menuOrder = 0;
            $template = 'universal';

            $stmt = $conn->prepare("
                INSERT INTO pages
                (
                    parent_id,
                    title,
                    slug,
                    content,
                    meta_title,
                    meta_description,
                    show_in_menu,
                    show_breadcrumbs,
                    menu_order,
                    status,
                    template,
                    published_at
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            $stmt->bind_param(
                "isssssiissss",
                $parentId,
                $name,
                $pageSlug,
                $pageContent,
                $metaTitle,
                $metaDescription,
                $showInMenu,
                $showBreadcrumbs,
                $menuOrder,
                $pageStatus,
                $template,
                $publishedAt
            );

            $stmt->execute();
            $pageId = (int)$stmt->insert_id;
            $stmt->close();

            if ($pageId <= 0) {
                throw new RuntimeException(
                    'Nepodařilo se vytvořit hlavní stránku pracoviště.'
                );
            }

            /*
             * 3. Doplnění main_page_id.
             */
            $stmt = $conn->prepare("
                UPDATE workplaces
                SET main_page_id = ?
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->bind_param(
                "ii",
                $pageId,
                $workplaceId
            );
            $stmt->execute();
            $stmt->close();

            /*
             * 4. Hlavní stránku zařadíme také do navigace pracoviště.
             */
            $workplacePageParentId = null;
            $workplacePageOrder = 10;
            $workplacePageActive = 1;

            $stmt = $conn->prepare("
                INSERT INTO workplace_pages
                (
                    workplace_id,
                    page_id,
                    parent_id,
                    show_in_menu,
                    sort_order,
                    is_active
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    1,
                    ?,
                    ?
                )
            ");

            $stmt->bind_param(
                "iiiii",
                $workplaceId,
                $pageId,
                $workplacePageParentId,
                $workplacePageOrder,
                $workplacePageActive
            );

            $stmt->execute();
            $stmt->close();

            /*
             * 5. Kategorie pracoviště.
             */
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
                        $workplaceId,
                        $categoryId
                    );
                    $stmt->execute();
                }

                $stmt->close();
            }

            /*
             * 6. Odbornosti pracoviště.
             */
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
                        $workplaceId,
                        $specializationId
                    );
                    $stmt->execute();
                }

                $stmt->close();
            }

            $conn->commit();

            logAction(
                "Vytvořeno pracoviště '$name'"
            );

            /*
             * Po vytvoření přejdeme rovnou do správy bloků
             * hlavní stránky pracoviště.
             */
            header(
                "Location: ../pages/blocks.php?page_id="
                . $pageId
                . "&created=1"
            );
            exit;

        } catch (Throwable $e) {
            $conn->rollback();

            error_log(
                'Workplace create error: ' . $e->getMessage()
            );

            $errors[] = 'Pracoviště se nepodařilo vytvořit: '
                . $e->getMessage();
        }
    }
}

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="admin-page-header-content mb-4">

        <div>
            <h2 class="mb-1">Přidat pracoviště</h2>

            <p class="text-muted mb-0">
                Klinika, oddělení, centrum nebo jiné pracoviště
            </p>
        </div>

        <a
            href="list.php"
            class="btn btn-outline-secondary">

            <i class="bi bi-arrow-left me-1"></i>
            Zpět na pracoviště

        </a>

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
                                    value="<?= e($shortName) ?>"
                                    placeholder="Například NOC">

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

                                <div class="form-text">
                                    Pokud zůstane prázdný, vytvoří se z názvu.
                                </div>

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
                                        — Vyberte Motol nebo Homolku —
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

                                <label class="form-label">
                                    E-mail
                                </label>

                                <input
                                    type="email"
                                    name="email"
                                    class="form-control"
                                    value="<?= e($email) ?>">

                            </div>

                            <div class="col-md-6 mb-3">

                                <label class="form-label">
                                    Telefon
                                </label>

                                <input
                                    type="text"
                                    name="phone"
                                    class="form-control"
                                    value="<?= e($phone) ?>">

                            </div>

                        </div>

                        <div class="row">

                            <div class="col-md-4 mb-3">

                                <label class="form-label">
                                    Budova
                                </label>

                                <input
                                    type="text"
                                    name="building"
                                    class="form-control"
                                    value="<?= e($building) ?>">

                            </div>

                            <div class="col-md-4 mb-3">

                                <label class="form-label">
                                    Patro
                                </label>

                                <input
                                    type="text"
                                    name="floor"
                                    class="form-control"
                                    value="<?= e($floor) ?>">

                            </div>

                            <div class="col-md-4 mb-3">

                                <label class="form-label">
                                    Adresa
                                </label>

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
                                    value="<?= e($latitude) ?>"
                                    placeholder="50.073658">

                            </div>

                            <div class="col-md-6 mb-3">

                                <label class="form-label">
                                    Zeměpisná délka
                                </label>

                                <input
                                    type="text"
                                    name="longitude"
                                    class="form-control"
                                    value="<?= e($longitude) ?>"
                                    placeholder="14.340129">

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
    <label class="form-label">Náhledový obrázek</label>

    <div class="input-group">
        <input
            type="text"
            name="thumbnail"
            id="workplace_thumbnail"
            class="form-control"
            placeholder="URL obrázku"
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
        <?= $thumbnail === '' ? 'style="display:none;"' : '' ?>>

        <img
            src="<?= e($thumbnail) ?>"
            alt="Náhled"
            class="img-fluid mt-2 rounded border"
            style="max-height:150px;">
    </div>
</div>

<div class="mb-0">
    <label class="form-label">Logo pracoviště</label>

    <div class="input-group">
        <input
            type="text"
            name="logo"
            id="workplace_logo"
            class="form-control"
            placeholder="URL obrázku"
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
        <?= $logo === '' ? 'style="display:none;"' : '' ?>>

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

                            <label class="form-label">
                                Stav
                            </label>

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

                            <label class="form-label">
                                Pořadí
                            </label>

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
                    Uložit a přejít na bloky

                </button>

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