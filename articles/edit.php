<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('articles');

function makeUniqueArticleSlugEdit(mysqli $conn, string $baseSlug, int $currentId): string {
    $slug = $baseSlug !== '' ? $baseSlug : 'clanek';
    $i = 1;
    $stmt = $conn->prepare("SELECT COUNT(*) FROM articles WHERE slug = ? AND id <> ?");
    while (true) {
        $check = $slug;
        $stmt->bind_param("si", $check, $currentId);
        $stmt->execute();
        $stmt->bind_result($cnt);
        $stmt->fetch();
        $stmt->free_result();
        if ((int)$cnt === 0) { $stmt->close(); return $slug; }
        $i++;
        $slug = $baseSlug . '-' . $i;
    }
}

$id = intval($_GET['id'] ?? 0);

// Admin může vše, autor jen své články
$where = isAdmin() ? "id=?" : "id=? AND author_id=" . intval($_SESSION['admin_id']);
$stmt = $conn->prepare("SELECT * FROM articles WHERE $where");
$stmt->bind_param("i", $id);
$stmt->execute();
$article = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$article) {
    header("Location: list.php");
    exit;
}

// Načtené kategorie
$selected = [];
$res = $conn->query("SELECT category_id FROM article_category WHERE article_id=" . intval($id));
while($r = $res->fetch_assoc()) {
    $selected[] = (int)$r['category_id'];
}

$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title        = trim($_POST['title'] ?? '');
    $baseSlug     = slugify($title);
    $slug         = makeUniqueArticleSlugEdit($conn, $baseSlug, $id);
    $content      = $_POST['content'] ?? '';
    $categories   = $_POST['categories'] ?? [];
    $thumbnail    = trim($_POST['thumbnail'] ?? '');
    $publish_date = !empty($_POST['publish_date']) ? $_POST['publish_date'] : NULL;
    $status       = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';

    if ($title && $content) {
        $stmt = $conn->prepare("UPDATE articles SET title=?, slug=?, content=?, thumbnail=?, publish_date=?, status=? WHERE id=?");
        $stmt->bind_param("ssssssi", $title, $slug, $content, $thumbnail, $publish_date, $status, $id);
        $stmt->execute();
        $stmt->close();

        // Přemapování kategorií
        $conn->query("DELETE FROM article_category WHERE article_id=" . intval($id));
        if (!empty($categories)) {
            $stmt2 = $conn->prepare("INSERT INTO article_category (article_id, category_id) VALUES (?,?)");
            foreach ($categories as $cid) {
                $cid = intval($cid);
                $stmt2->bind_param("ii", $id, $cid);
                $stmt2->execute();
            }
            $stmt2->close();
        }

        header("Location: list.php?updated=1");
        exit;
    } else {
        $msg = "Název a obsah článku jsou povinné.";
    }

    // propsání zpět při chybě
    $article['title']        = $title;
    $article['content']      = $content;
    $article['thumbnail']    = $thumbnail;
    $article['publish_date'] = $publish_date;
    $article['status']       = $status;
    $selected                = array_map('intval', $categories);
}

include "../includes/header.php";
$st = $article['status'] ?? 'draft';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <h2 class="mb-4">Upravit článek</h2>

            <?php if ($msg): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <form method="post" class="bg-white p-4 rounded shadow-sm">
                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label">Název článku</label>
                        <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($article['title']) ?>" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label">Datum publikace</label>
                        <input type="date" name="publish_date" class="form-control" value="<?= htmlspecialchars($article['publish_date'] ?? '') ?>">
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
                            while($c = $cats->fetch_assoc()):
                                $isSel = in_array((int)$c['id'], $selected, true) ? 'selected' : '';
                            ?>
                                <option value="<?= $c['id'] ?>" <?= $isSel ?>><?= htmlspecialchars($c['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label">Náhledový obrázek</label>
                        <div class="input-group">
                            <input type="text" name="thumbnail" id="thumbnail" class="form-control" placeholder="URL obrázku" value="<?= htmlspecialchars($article['thumbnail'] ?? '') ?>" readonly>
                            <button type="button"
                                class="btn btn-outline-secondary open-picker"
                                data-bs-toggle="modal"
                                data-bs-target="#imagePickerModal"
                                data-iframe-src="/pictures/list.php?picker=thumb"
                                data-target-input="#thumbnail"
                                data-target-preview="#thumbnail-preview">
                                Vybrat...
                            </button>
                        </div>
                        <div class="mt-2" id="thumbnail-preview" style="<?= !empty($article['thumbnail']) ? '' : 'display:none;' ?>">
                            <?php if (!empty($article['thumbnail'])): ?>
                                <img src="<?= htmlspecialchars($article['thumbnail']) ?>" alt="Náhled" class="img-fluid mt-2 rounded border" style="max-height:150px;">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-12">
                        <label class="form-label">Obsah</label>
                        <textarea name="content" class="form-control editor" rows="10"><?= htmlspecialchars($article['content']) ?></textarea>
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
