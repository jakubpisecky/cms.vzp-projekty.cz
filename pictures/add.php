<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php"; // image helpers
requirePermission('pictures');

$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['picture'])) {
    $file  = $_FILES['picture'];
    $title = trim($_POST['title'] ?? '');

    if ($title === '') {
        $msg = "Titulek je povinný.";
    } elseif ($file['error'] === UPLOAD_ERR_OK) {

        // přijímáme běžné obrazové formáty, ale uložíme vždy JPG
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (!in_array($ext, $allowed, true)) {
            $msg = "Nepovolený typ souboru. Povolené jsou JPG, PNG, GIF, WEBP (ukládané jako JPG).";
        } else {
            // KOŘEN /uploads/pictures/
            $docRoot      = rtrim($_SERVER['DOCUMENT_ROOT'] ?: realpath(__DIR__ . "/.."), '/');
            $uploadDirAbs = $docRoot . "/uploads/pictures/";
            if (!is_dir($uploadDirAbs)) {
                @mkdir($uploadDirAbs, 0775, true);
            }

            // název podle titulku (slug) – výsledný master bude VŽDY .jpg
            $slug     = slugify($title) ?: ('obrazek-' . date('Ymd-His'));
            $baseName = $slug;

            // nejdřív dočasně ulož příchozí soubor pod jeho původní příponou
            $tmpName = $baseName . '.' . $ext;
            $i = 1;
            while (is_file($uploadDirAbs . $tmpName)) {
                $tmpName = $baseName . '-' . $i++ . '.' . $ext;
            }
            $absTmp = $uploadDirAbs . $tmpName;

            if (!move_uploaded_file($file['tmp_name'], $absTmp)) {
                $msg = "Chyba při ukládání souboru na server.";
            } else {
                // načti s korekcí orientace, pak ULOŽ VŽDY JPG (ať byl vstup jakýkoli)
                $src0 = gd_load_with_orientation($absTmp);
                if (!$src0) {
                    @unlink($absTmp);
                    $msg = "Nahraný soubor nelze zpracovat jako obrázek.";
                } else {
                    // master JPG (unikátní jméno)
                    $jpgName = $baseName . '.jpg';
                    $k = 1;
                    while (is_file($uploadDirAbs . $jpgName)) {
                        $jpgName = $baseName . '-' . $k++ . '.jpg';
                    }
                    $absMaster = $uploadDirAbs . $jpgName;

                    // případná redukce velmi velkých originálů (např. nad 5000 px)
                    [$ow, $oh] = @getimagesize($absTmp) ?: [0,0];
                    $maxMaster = 5000;
                    if (max($ow, $oh) > $maxMaster) {
                        [$srcReduced] = gd_resize_fit($src0, $maxMaster, $maxMaster);
                        save_jpeg($srcReduced, $absMaster, 88);
                        imagedestroy($srcReduced);
                    } else {
                        // jen převod do JPG
                        save_jpeg($src0, $absMaster, 90);
                    }
                    imagedestroy($src0);
                    @unlink($absTmp); // původní soubor už nepotřebujeme

                    // vytvoř varianty (JPG only)
                    $src = gd_load_with_orientation($absMaster);
                    if ($src) {
                        $baseNoExtAbs = $uploadDirAbs . pathinfo($absMaster, PATHINFO_FILENAME);

                        // medium (max 1600 px)
                        [$medImg] = gd_resize_fit($src, 1600, 1600);
                        $medJpg = $baseNoExtAbs . '_medium.jpg';
                        save_jpeg($medImg, $medJpg, 82);
                        imagedestroy($medImg);

                        // thumb (čtverec 300)
                        [$thImg] = gd_crop_square($src, 300);
                        $thJpg = $baseNoExtAbs . '_thumb.jpg';
                        save_jpeg($thImg, $thJpg, 82);
                        imagedestroy($thImg);

                        imagedestroy($src);
                    } else {
                        error_log("pictures/add.php: cannot reload master: " . $absMaster);
                    }

                    // zápis do DB – ukládáme SKUTEČNÉ jméno JPG masteru
                    $newName = pathinfo($absMaster, PATHINFO_BASENAME);
                    $stmt = $conn->prepare("
                        INSERT INTO pictures (filename, title, uploaded_by, uploaded_at)
                        VALUES (?,?,?,NOW())
                    ");
                    $stmt->bind_param("ssi", $newName, $title, $_SESSION['admin_id']);
                    $stmt->execute();

                    header("Location: list.php");
                    exit;
                }
            }
        }
    } else {
        $msg = "Nebyl vybrán žádný soubor, nebo došlo k chybě při nahrávání.";
    }
}

include "../includes/header.php";
?>
<div class="container py-4">
  <div class="row">
    <div class="col-12 col-lg-12">
      <h2 class="mb-4">Nahrát nový obrázek</h2>

      <?php if (!empty($msg)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" class="bg-white p-4 rounded shadow-sm">
        <div class="mb-3">
          <label class="form-label">Soubor obrázku</label>
          <input type="file" name="picture" class="form-control" accept=".jpg,.jpeg,.png,.gif,.webp,image/*" required>
          <div class="form-text">
            Přijmeme JPG/PNG/GIF/WEBP, ale vše uložíme jako JPG. Vytvoří se <code>_medium.jpg</code> a <code>_thumb.jpg</code>.
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Titulek (povinný)</label>
          <input type="text" name="title" class="form-control" required>
        </div>

        <div class="d-flex justify-content-between">
          <button class="btn btn-success">
            <i class="bi bi-upload me-1"></i> Nahrát
          </button>
          <a href="list.php" class="btn btn-secondary me-2">
            <i class="bi bi-arrow-left me-1"></i> Zpět
          </a>
        </div>
      </form>
    </div>
  </div>
</div>
<?php include "../includes/footer.php"; ?>
