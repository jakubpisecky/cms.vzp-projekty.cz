<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php"; // image helpers

requirePermission('pictures');

$msg = "";

// Kategorie obrázků
$categories = [];
$resCat = $conn->query("
    SELECT id, name
    FROM picture_categories
    ORDER BY sort_order ASC, name ASC
");

if ($resCat) {
    while ($row = $resCat->fetch_assoc()) {
        $categories[] = $row;
    }
}

$title = '';
$category_id = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['picture'])) {
    $file = $_FILES['picture'];

    $title = trim($_POST['title'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);

    if ($category_id <= 0) {
        $category_id = 0;
    }

    if ($title === '') {
        $msg = "Titulek je povinný.";
    } elseif ($file['error'] === UPLOAD_ERR_OK) {

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($ext, $allowed, true)) {
            $msg = "Nepovolený typ souboru. Povolené jsou JPG, PNG, GIF, WEBP.";
        } else {
            $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?: realpath(__DIR__ . "/.."), '/');
            $uploadDirAbs = $docRoot . "/uploads/pictures/";

            if (!is_dir($uploadDirAbs)) {
                @mkdir($uploadDirAbs, 0775, true);
            }

            $slug = slugify($title) ?: ('obrazek-' . date('Ymd-His'));
            $baseName = $slug;

            $tmpName = $baseName . '.' . $ext;
            $i = 1;

            while (is_file($uploadDirAbs . $tmpName)) {
                $tmpName = $baseName . '-' . $i++ . '.' . $ext;
            }

            $absTmp = $uploadDirAbs . $tmpName;

            if (!move_uploaded_file($file['tmp_name'], $absTmp)) {
                $msg = "Chyba při ukládání souboru na server.";
            } else {
                $src0 = gd_load_with_orientation($absTmp);

                if (!$src0) {
                    @unlink($absTmp);
                    $msg = "Nahraný soubor nelze zpracovat jako obrázek.";
                } else {
                    $jpgName = $baseName . '.jpg';
                    $k = 1;

                    while (is_file($uploadDirAbs . $jpgName)) {
                        $jpgName = $baseName . '-' . $k++ . '.jpg';
                    }

                    $absMaster = $uploadDirAbs . $jpgName;

                    [$ow, $oh] = @getimagesize($absTmp) ?: [0, 0];
                    $maxMaster = 5000;

                    if (max($ow, $oh) > $maxMaster) {
                        [$srcReduced] = gd_resize_fit($src0, $maxMaster, $maxMaster);
                        save_jpeg($srcReduced, $absMaster, 88);
                        imagedestroy($srcReduced);
                    } else {
                        save_jpeg($src0, $absMaster, 90);
                    }

                    imagedestroy($src0);
                    @unlink($absTmp);

                    $src = gd_load_with_orientation($absMaster);

                    if ($src) {
                        $baseNoExtAbs = $uploadDirAbs . pathinfo($absMaster, PATHINFO_FILENAME);

                        [$medImg] = gd_resize_fit($src, 1600, 1600);
                        $medJpg = $baseNoExtAbs . '_medium.jpg';
                        save_jpeg($medImg, $medJpg, 82);
                        imagedestroy($medImg);

                        [$thImg] = gd_crop_square($src, 300);
                        $thJpg = $baseNoExtAbs . '_thumb.jpg';
                        save_jpeg($thImg, $thJpg, 82);
                        imagedestroy($thImg);

                        imagedestroy($src);
                    } else {
                        error_log("pictures/add.php: cannot reload master: " . $absMaster);
                    }

                    $newName = pathinfo($absMaster, PATHINFO_BASENAME);

                    if ($category_id > 0) {
                        $stmt = $conn->prepare("
                            INSERT INTO pictures
                                (filename, title, category_id, uploaded_by, uploaded_at)
                            VALUES
                                (?, ?, ?, ?, NOW())
                        ");

                        $uploadedBy = (int)($_SESSION['admin_id'] ?? 0);

                        $stmt->bind_param(
                            "ssii",
                            $newName,
                            $title,
                            $category_id,
                            $uploadedBy
                        );
                    } else {
                        $stmt = $conn->prepare("
                            INSERT INTO pictures
                                (filename, title, category_id, uploaded_by, uploaded_at)
                            VALUES
                                (?, ?, NULL, ?, NOW())
                        ");

                        $uploadedBy = (int)($_SESSION['admin_id'] ?? 0);

                        $stmt->bind_param(
                            "ssi",
                            $newName,
                            $title,
                            $uploadedBy
                        );
                    }

                    if ($stmt->execute()) {
                        $stmt->close();
                        header("Location: list.php?created=1");
                        exit;
                    }

                    $msg = "Obrázek se nepodařilo uložit do databáze: " . $stmt->error;
                    $stmt->close();
                }
            }
        }
    } else {
        $msg = "Nebyl vybrán žádný soubor, nebo došlo k chybě při nahrávání.";
    }
}

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="mb-1">Nahrát nový obrázek</h2>
            <p class="text-muted mb-0">Nahrání obrázku do knihovny médií</p>
        </div>

        <div class="responsive-actions">
            <a href="list.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Zpět
            </a>
        </div>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-danger"><?= e($msg) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">

                <div class="mb-3">
                    <label class="form-label">Soubor obrázku</label>
                    <input type="file"
                           name="picture"
                           class="form-control"
                           accept=".jpg,.jpeg,.png,.gif,.webp,image/*"
                           required>

                    <div class="form-text">
                        Přijmeme JPG, PNG, GIF nebo WEBP. Obrázek se uloží jako JPG a vytvoří se varianty
                        <code>_medium.jpg</code> a <code>_thumb.jpg</code>.
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Titulek <span class="text-danger">*</span></label>
                    <input type="text"
                           name="title"
                           class="form-control"
                           value="<?= e($title) ?>"
                           required>
                </div>

                <div class="mb-4">
                    <label class="form-label">Kategorie</label>
                    <select name="category_id" class="form-select">
                        <option value="0">-- Bez kategorie --</option>

                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>"
                                <?= (int)$category_id === (int)$cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if (empty($categories)): ?>
                        <div class="form-text text-warning">
                            Zatím nejsou vytvořené žádné kategorie obrázků.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="responsive-actions">
                    <button class="btn btn-success">
                        <i class="bi bi-upload me-1"></i> Nahrát obrázek
                    </button>

                    <a href="list.php" class="btn btn-outline-secondary">
                        Zrušit
                    </a>
                </div>

            </form>
        </div>
    </div>

</div>

<?php include "../includes/footer.php"; ?>