<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('articles');

function makeUniqueArticleSlug(mysqli $conn, string $baseSlug): string {
    $slug = $baseSlug !== '' ? $baseSlug : 'clanek';
    $i = 1;
    $stmt = $conn->prepare("SELECT COUNT(*) FROM articles WHERE slug=?");
    while (true) {
        $check = $slug;
        $stmt->bind_param("s", $check);
        $stmt->execute();
        $stmt->bind_result($cnt);
        $stmt->fetch();
        $stmt->free_result();
        if ((int)$cnt === 0) { $stmt->close(); return $slug; }
        $i++;
        $slug = $baseSlug . '-' . $i;
    }
}


$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title     = trim($_POST['title'] ?? '');
    $slugInput = trim($_POST['slug'] ?? '');
    $baseSlug  = $slugInput !== '' ? slugify($slugInput) : slugify($title);
    $slug      = makeUniqueArticleSlug($conn, $baseSlug);
    $content      = $_POST['content'] ?? '';
    $author_id    = intval($_SESSION['admin_id']);
    $categories   = $_POST['categories'] ?? [];
    $thumbnail    = trim($_POST['thumbnail'] ?? '');
    $publish_date = !empty($_POST['publish_date']) ? $_POST['publish_date'] : NULL;
    $status       = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    
    if ($title && $content) {
        $stmt = $conn->prepare("INSERT INTO articles (title, slug, content, author_id, thumbnail, publish_date, status) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("sssisss", $title, $slug, $content, $author_id, $thumbnail, $publish_date, $status);
        $stmt->execute();
        $article_id = $conn->insert_id;

        if (!empty($categories)) {
            $stmt2 = $conn->prepare("INSERT INTO article_category (article_id, category_id) VALUES (?,?)");
            foreach ($categories as $cid) {
                $cid = intval($cid);
                $stmt2->bind_param("ii", $article_id, $cid);
                $stmt2->execute();
            }
            $stmt2->close();
        }

        header("Location: list.php?created=1");
        exit;
    } else {
        $msg = "Název a obsah článku jsou povinné.";
    }
}

include "../includes/header.php";
$st = $_POST['status'] ?? 'draft';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <h2 class="mb-4">Přidat článek</h2>

            <?php if ($msg): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <form method="post" class="bg-white p-4 rounded shadow-sm">
                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label">Název článku</label>
                        <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label">Datum publikace</label>
                        <input type="date" name="publish_date" class="form-control" value="<?= htmlspecialchars($_POST['publish_date'] ?? date('Y-m-d')) ?>">
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label">Stav</label>
                        <select name="status" class="form-select">
                            <option value="draft" <?= $st === 'published' ? '' : 'selected' ?>>Koncept</option>
                            <option value="published" <?= $st === 'published' ? 'selected' : '' ?>>Publikováno</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label">Kategorie</label>
                        <select name="categories[]" class="form-select select2" multiple>
                            <?php
                            $cats = $conn->query("SELECT id, name FROM categories ORDER BY name");
                            $sel = array_map('intval', $_POST['categories'] ?? []);
                            while($c = $cats->fetch_assoc()):
                                $selected = in_array((int)$c['id'], $sel, true) ? 'selected' : '';
                            ?>
                                <option value="<?= $c['id'] ?>" <?= $selected ?>><?= htmlspecialchars($c['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label">Náhledový obrázek</label>
                        <div class="input-group">
                            <input type="text" name="thumbnail" id="thumbnail" class="form-control" placeholder="URL obrázku" value="<?= htmlspecialchars($_POST['thumbnail'] ?? '') ?>" readonly>
                            <button type="button"
                                class="btn btn-outline-secondary open-picker"
                                data-bs-toggle="modal"
                                data-bs-target="#imagePickerModal"
                                data-iframe-src="/pictures/list.php?picker=thumb"
                                data-target-input="#thumbnail"
                                data-target-preview="#thumbnail-preview">
                                Vybrat...
                            </button>
                        </div><div class="mt-2" id="thumbnail-preview" style="display:none;"> <img src="" alt="Náhled" class="img-fluid mt-2 rounded border" style="max-height:150px;"> </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-12">
                        <label class="form-label">Obsah</label>
                        <textarea name="content" class="form-control editor" rows="10"><?= htmlspecialchars($_POST['content'] ?? '') ?></textarea>
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
