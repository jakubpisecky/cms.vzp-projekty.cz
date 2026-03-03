<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('pages');

$msg = "";

// CSRF
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
            http_response_code(400); exit("Neplatný CSRF token.");
        }
    }
}
csrf_check();

$id = intval($_GET['id'] ?? 0);
$s = $conn->prepare("SELECT * FROM pages WHERE id=?");
$s->bind_param("i", $id);
$s->execute();
$page = $s->get_result()->fetch_assoc();
if (!$page) { http_response_code(404); exit("Stránka nenalezena."); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $parent_id     = intval($_POST['parent_id'] ?? 0);
    $title         = trim($_POST['title'] ?? '');
    $slug          = trim($_POST['slug'] ?? '');
    $content       = $_POST['content'] ?? '';
    $meta_title    = trim($_POST['meta_title'] ?? '');
    $meta_desc     = trim($_POST['meta_description'] ?? '');
    $show_in_menu  = isset($_POST['show_in_menu']) ? 1 : 0;
    $menu_order    = intval($_POST['menu_order'] ?? 0);
    $status        = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $template = normalizePageTemplate(trim($_POST['template'] ?? 'page'), $PAGE_TEMPLATES);

    if ($title && $content !== null) {
        if ($slug === '') {
            if (function_exists('slugify')) $slug = slugify($title);
            else {
                $slug = iconv('UTF-8','ASCII//TRANSLIT',$title);
                $slug = strtolower(preg_replace('~[^a-z0-9]+~','-',$slug));
                $slug = trim($slug,'-') ?: 'stranka';
            }
        }
        if ($parent_id === $id) $parent_id = 0; // ochrana proti cyklu

        // unikátnost slugu v rámci parenta (s výjimkou sebe)
        $chk = $conn->prepare("SELECT id FROM pages WHERE parent_id=? AND slug=? AND id<>? LIMIT 1");
        $chk->bind_param("isi", $parent_id, $slug, $id);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) $slug .= '-'.time();

        $u = $conn->prepare("
            UPDATE pages
            SET parent_id=?, title=?, slug=?, content=?, meta_title=?, meta_description=?, show_in_menu=?, menu_order=?, status=?, template=?,
                published_at = IF(?='published' AND published_at IS NULL, NOW(), published_at)
            WHERE id=?
        ");
        $u->bind_param("issssssisssi",
            $parent_id, $title, $slug, $content, $meta_title, $meta_desc, $show_in_menu, $menu_order, $status, $template, $status, $id
        );
        $u->execute();

        header("Location: list.php?updated=1"); exit;
    } else {
        $msg = "Titulek je povinný.";
    }
}

include "../includes/header.php";
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <h2 class="mb-4">Upravit stránku</h2>

            <?php if ($msg): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <form method="post" class="bg-white p-4 rounded shadow-sm">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">

  <!-- Titulek -->
  <div class="row mb-3">
    <div class="col-md-8">
      <label class="form-label">Titulek</label>
      <input type="text" name="title" class="form-control"
             value="<?= htmlspecialchars($page['title'] ?? '') ?>" required>
    </div>
  </div>

  <!-- Nadřazená stránka -->
  <div class="row mb-3">
    <div class="col-md-8">
      <label class="form-label">Nadřazená stránka</label>
      <select name="parent_id" class="form-select">
        <option value="0" <?= $page['parent_id']==0?'selected':'' ?>>— První úroveň —</option>
        <?php
        $r = $conn->query("SELECT id, title FROM pages WHERE id<>".$id." ORDER BY title ASC");
        while ($p = $r->fetch_assoc()):
        ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)$page['parent_id']===(int)$p['id']?'selected':'' ?>>
            <?= htmlspecialchars($p['title']) ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>
  </div>

  <!-- URL (slug) -->
  <div class="row mb-3">
    <div class="col-md-8">
      <label class="form-label">URL (slug)</label>
      <input type="text" name="slug" class="form-control"
             value="<?= htmlspecialchars($page['slug'] ?? '') ?>">
    </div>
  </div>

  <!-- Stav -->
  <div class="row mb-3">
    <div class="col-md-8">
      <label class="form-label">Stav</label>
      <select name="status" class="form-select">
        <option value="draft" <?= $page['status']==='draft'?'selected':'' ?>>Koncept</option>
        <option value="published" <?= $page['status']==='published'?'selected':'' ?>>Publikováno</option>
      </select>
    </div>
  </div>

  <!-- Pořadí v menu -->
  <div class="row mb-3">
    <div class="col-md-8">
      <label class="form-label">Pořadí v menu</label>
      <input type="number" name="menu_order" class="form-control"
             value="<?= (int)$page['menu_order'] ?>">
    </div>
  </div>

  <!-- Zobrazit v menu (switch) -->
  <div class="row mb-3">
    <div class="col-md-8">
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="show_in_menu" id="show_in_menu"
               <?= $page['show_in_menu'] ? 'checked' : '' ?>>
        <label class="form-check-label" for="show_in_menu">Zobrazit v menu</label>
      </div>
    </div>
  </div>

  <!-- Obsah -->
  <div class="row mb-4">
    <div class="col-md-12">
      <label class="form-label">Obsah</label>
      <textarea name="content" class="form-control editor" rows="12"><?= htmlspecialchars($page['content'] ?? '') ?></textarea>
    </div>
  </div>

  <!-- SEO titulek -->
  <div class="row mb-3">
    <div class="col-md-8">
      <label class="form-label">SEO titulek</label>
      <input type="text" name="meta_title" class="form-control"
             value="<?= htmlspecialchars($page['meta_title'] ?? '') ?>">
    </div>
  </div>

  <!-- SEO popis -->
  <div class="row mb-4">
    <div class="col-md-8">
      <label class="form-label">SEO popis</label>
      <input type="text" name="meta_description" class="form-control"
             value="<?= htmlspecialchars($page['meta_description'] ?? '') ?>">
    </div>
  </div>

  <!-- Šablona -->
  <div class="row mb-4">
    <div class="col-md-8">
      <label class="form-label">Šablona</label>
      <select name="template" class="form-select">
        <?php
        $currentTpl = normalizePageTemplate($page['template'] ?? 'page', $PAGE_TEMPLATES);
        foreach ($PAGE_TEMPLATES as $value => $label):
        ?>
          <option value="<?= htmlspecialchars($value) ?>" <?= $currentTpl===$value ? 'selected' : '' ?>>
            <?= htmlspecialchars($label) ?>
          </option>
        <?php endforeach; ?>
      </select>
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
