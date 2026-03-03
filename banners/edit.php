<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('banners');

$id = max(1, (int)($_GET['id'] ?? 0));
$stmt = $conn->prepare("SELECT * FROM banners WHERE id=?");
$stmt->bind_param("i",$id); $stmt->execute();
$banner = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$banner) { header("Location: list.php"); exit; }

$msg = "";
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $title  = trim($_POST['title'] ?? '');  
  $subtitle  = trim($_POST['subtitle'] ?? '');
  $slugRaw  = trim($_POST['slug'] ?? '');
  $slugBase = ($slugRaw !== '') ? $slugRaw : $title;
  $slug     = slugify($slugBase);
  $chk = $conn->prepare("SELECT COUNT(*) c FROM banners WHERE slug=? AND id<>?");
  $chk->bind_param("si", $slug, $id);
  $chk->execute();
  $dup = (int)($chk->get_result()->fetch_assoc()['c'] ?? 0);
  $chk->close();
if ($dup > 0) $slug .= '-' . $id;
  $image  = trim($_POST['image'] ?? '');
  $link   = trim($_POST['link_url'] ?? '');
  $status = ($_POST['status'] ?? 'draft')==='published' ? 'published' : 'draft';
  $order  = is_numeric($_POST['sort_order'] ?? '') ? (int)$_POST['sort_order'] : $banner['sort_order'];

  if ($title==='') $msg = "Název je povinný.";
  else {
    // ošetření duplicity slugu (kromě aktuálního záznamu)
    $chk = $conn->prepare("SELECT COUNT(*) c FROM banners WHERE slug=? AND id<>?");
    $chk->bind_param("si", $slug, $id); $chk->execute();
    $dup = (int)($chk->get_result()->fetch_assoc()['c'] ?? 0); $chk->close();
    if ($dup>0) $slug .= '-'.($id); // jednoduché dorovnání

    $stmt = $conn->prepare("UPDATE banners SET title=?, subtitle=?, slug=?, image=?, link_url=?, status=?, sort_order=?, updated_at=NOW() WHERE id=?");
    $stmt->bind_param("sssssssi", $title,$subtitle,$slug,$image,$link,$status,$order,$id);
    $stmt->execute(); $stmt->close();

    header("Location: list.php?updated=1"); exit;
  }

  // refill
  $banner['title']=$title; $banner['slug']=$slug; $banner['image']=$image;
  $banner['link_url']=$link; $banner['status']=$status; $banner['sort_order']=$order;
}

include "../includes/header.php";
$st = $banner['status'] ?? 'draft';
?>
<div class="container py-4">
  <h2 class="mb-4">Upravit banner</h2>
  <?php if ($msg): ?><div class="alert alert-danger"><?= e($msg) ?></div><?php endif; ?>

  <form method="post" class="bg-white p-4 rounded shadow-sm">
    <div class="row mb-3"><div class="col-md-8">
      <label class="form-label">Název</label>
      <input type="text" name="title" class="form-control" required value="<?= e($banner['title'] ?? '') ?>">
    </div></div>

    <div class="row mb-3"><div class="col-md-8">
      <label class="form-label">Druhý titulek</label>
      <input type="text" name="subtitle" class="form-control" required value="<?= e($banner['subtitle'] ?? '') ?>">
    </div></div>

    <div class="row mb-3"><div class="col-md-8">
      <label class="form-label">URL (slug)</label>
      <input type="text" name="slug" class="form-control" value="<?= e($banner['slug'] ?? '') ?>">
    </div></div>

    <div class="row mb-3"><div class="col-md-8">
      <label class="form-label">Obrázek</label>
      <div class="input-group">
        <input type="text" name="image" id="image" class="form-control" placeholder="URL obrázku" value="<?= e($banner['image'] ?? '') ?>" readonly>
        <button type="button" class="btn btn-outline-secondary open-picker"
          data-bs-toggle="modal" data-bs-target="#imagePickerModal"
          data-iframe-src="/pictures/list.php?picker=thumb"
          data-target-input="#image" data-target-preview="#image-preview">Vybrat…</button>
      </div>
      <div class="mt-2" id="image-preview" style="<?= !empty($banner['image']) ? '' : 'display:none;' ?>">
        <?php if (!empty($banner['image'])): ?><img src="<?= e($banner['image']) ?>" alt="" class="img-fluid rounded border" style="max-height:150px"><?php endif; ?>
      </div>
    </div></div>

    <div class="row mb-3"><div class="col-md-8">
      <label class="form-label">Odkaz (po kliknutí)</label>
      <input type="url" name="link_url" class="form-control" value="<?= e($banner['link_url'] ?? '') ?>">
    </div></div>

    <div class="row mb-3"><div class="col-md-4">
      <label class="form-label">Stav</label>
      <select name="status" class="form-select">
        <option value="draft" <?= $st==='published'?'':'selected' ?>>Koncept</option>
        <option value="published" <?= $st==='published'?'selected':'' ?>>Publikováno</option>
      </select>
    </div><div class="col-md-4">
      <label class="form-label">Pořadí</label>
      <input type="number" name="sort_order" class="form-control" value="<?= (int)($banner['sort_order'] ?? 10) ?>">
    </div></div>

    <div class="d-flex justify-content-between">
      <button class="btn btn-success"><i class="bi bi-save me-1"></i> Uložit změny</button>
      <a href="list.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i> Zpět</a>
    </div>
  </form>
</div>
<?php include "../includes/footer.php"; ?>
