<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('banners');

$msg = "";

// helpers
function nextOrder(mysqli $conn): int {
  $row = $conn->query("SELECT COALESCE(MAX(sort_order),0)+10 AS n FROM banners")->fetch_assoc();
  return (int)($row['n'] ?? 10);
}
function makeUniqueSlug(mysqli $conn, string $base): string {
  $base = $base !== '' ? $base : 'polozka';
  $slug = $base; $i=1;
  $stmt = $conn->prepare("SELECT COUNT(*) FROM banners WHERE slug=?");
  while(true){
    $stmt->bind_param("s", $slug); $stmt->execute(); $stmt->bind_result($c); $stmt->fetch(); $stmt->free_result();
    if ((int)$c===0) { $stmt->close(); return $slug; }
    $i++; $slug = $base.'-'.$i;
  }
}

$orderDefault = nextOrder($conn);

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $title  = trim($_POST['title'] ?? '');
  $subtitle  = trim($_POST['subtitle'] ?? '');
  $slugRaw  = trim($_POST['slug'] ?? '');
  $slugBase = ($slugRaw !== '') ? $slugRaw : $title;  // vezmi ručně zadaný slug, jinak title
  $slugIn   = slugify($slugBase);
  $slug     = makeUniqueSlug($conn, $slugIn);
  $image  = trim($_POST['image'] ?? '');
  $link   = trim($_POST['link_url'] ?? '');
  $status = ($_POST['status'] ?? 'draft')==='published' ? 'published' : 'draft';
  $order  = is_numeric($_POST['sort_order'] ?? '') ? (int)$_POST['sort_order'] : nextOrder($conn);

  if ($title==='') {
    $msg = "Název je povinný.";
  } else {
    $stmt = $conn->prepare("INSERT INTO banners (title,subtitle,slug,image,link_url,status,sort_order,created_at) VALUES (?,?,?,?,?,?,?,NOW())");
    $stmt->bind_param("ssssssi", $title,$subtitle,$slug,$image,$link,$status,$order);
    $stmt->execute(); $stmt->close();
    header("Location: list.php?created=1"); exit;
  }
}

include "../includes/header.php";
?>
<div class="container py-4">
  <h2 class="mb-4">Nový banner</h2>

  <?php if ($msg): ?><div class="alert alert-danger"><?= e($msg) ?></div><?php endif; ?>

  <form method="post" class="bg-white p-4 rounded shadow-sm">
    <div class="row mb-3"><div class="col-md-8">
      <label class="form-label">Název</label>
      <input type="text" name="title" class="form-control" required value="<?= e($_POST['title'] ?? '') ?>">
    </div></div>

    <div class="row mb-3"><div class="col-md-8">
      <label class="form-label">Druhý titulek</label>
      <input type="text" name="subtitle" class="form-control" required value="<?= e($_POST['subtitle'] ?? '') ?>">
    </div></div>

    <div class="row mb-3"><div class="col-md-8">
      <label class="form-label">URL (slug)</label>
      <input type="text" name="slug" class="form-control" placeholder="prázdné = z názvu" value="<?= e($_POST['slug'] ?? '') ?>">
    </div></div>

    <div class="row mb-3"><div class="col-md-8">
      <label class="form-label">Obrázek</label>
      <div class="input-group">
        <input type="text" name="image" id="image" class="form-control" placeholder="URL obrázku" value="<?= e($_POST['image'] ?? '') ?>" readonly>
        <button type="button" class="btn btn-outline-secondary open-picker"
          data-bs-toggle="modal" data-bs-target="#imagePickerModal"
          data-iframe-src="/pictures/list.php?picker=thumb"
          data-target-input="#image" data-target-preview="#image-preview">Vybrat…</button>
      </div>
      <div class="mt-2" id="image-preview" style="<?= !empty($_POST['image']) ? '' : 'display:none;' ?>">
        <?php if (!empty($_POST['image'])): ?><img src="<?= e($_POST['image']) ?>" alt="" class="img-fluid rounded border" style="max-height:150px"><?php endif; ?>
      </div>
    </div></div>

    <div class="row mb-3"><div class="col-md-8">
      <label class="form-label">Odkaz (po kliknutí)</label>
      <input type="url" name="link_url" class="form-control" value="<?= e($_POST['link_url'] ?? '') ?>">
    </div></div>

    <div class="row mb-3"><div class="col-md-4">
      <label class="form-label">Stav</label>
      <?php $st = $_POST['status'] ?? 'draft'; ?>
      <select name="status" class="form-select">
        <option value="draft" <?= $st==='published'?'':'selected' ?>>Koncept</option>
        <option value="published" <?= $st==='published'?'selected':'' ?>>Publikováno</option>
      </select>
    </div><div class="col-md-4">
      <label class="form-label">Pořadí</label>
      <input type="number" name="sort_order" class="form-control" value="<?= (int)($orderDefault) ?>">
    </div></div>

    <div class="d-flex justify-content-between">
      <button class="btn btn-success"><i class="bi bi-save me-1"></i> Uložit</button>
      <a href="list.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i> Zpět</a>
    </div>
  </form>
</div>
<?php include "../includes/footer.php"; ?>
