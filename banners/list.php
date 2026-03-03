<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('banners');

include "../includes/header.php";

$q       = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$created = isset($_GET['created']);
$updated = isset($_GET['updated']);
$deleted = isset($_GET['deleted']);
$toggled = $_GET['toggled'] ?? null;

// celkový počet
$sqlCount = "SELECT COUNT(*) AS c FROM banners";
if ($q !== '') $sqlCount .= " WHERE (title LIKE ? OR slug LIKE ?)";
$stmt = $conn->prepare($sqlCount);
if ($q !== '') { $like = "%{$q}%"; $stmt->bind_param("ss", $like, $like); }
$stmt->execute();
$total = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;
$base = buildBaseUrl();
// data
$sql = "
  SELECT id, title, slug, image, status, sort_order, created_at
  FROM banners
";
if ($q !== '') $sql .= " WHERE (title LIKE ? OR slug LIKE ?)";
$sql .= " ORDER BY sort_order ASC, id ASC LIMIT ?, ?";

$stmt = $conn->prepare($sql);
if ($q !== '') {
  $stmt->bind_param("ssii", $like, $like, $offset, $perPage);
} else {
  $stmt->bind_param("ii", $offset, $perPage);
}
$stmt->execute();
$res = $stmt->get_result();
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Bannery <small class="text-muted">(<?= $total ?>)</small></h2>
    <a href="add.php" class="btn btn-success"><i class="bi bi-plus-circle me-1"></i> Nový banner</a>
  </div>

  <?php if ($created): ?><div class="alert alert-success">Banner byl vytvořen.</div><?php endif; ?>
  <?php if ($updated): ?><div class="alert alert-success">Banner byl upraven.</div><?php endif; ?>
  <?php if ($deleted): ?><div class="alert alert-success">Banner byl smazán.</div><?php endif; ?>
  <?php if ($toggled): ?>
    <div class="alert alert-success">
      <?= $toggled === 'published' ? 'Banner publikován.' : 'Banner přepnut na koncept.' ?>
    </div>
  <?php endif; ?>

  <form method="get" class="mb-3">
    <div class="row g-2">
      <div class="col-lg-6"><input class="form-control" name="q" placeholder="Hledat v názvu nebo slugu…" value="<?= e($q) ?>"></div>
      <div class="col-auto"><button class="btn btn-secondary"><i class="bi bi-search me-1"></i> Hledat</button></div>
      <?php if ($q !== ''): ?><div class="col-auto"><a class="btn btn-outline-secondary" href="list.php"><i class="bi bi-x-circle me-1"></i> Zrušit</a></div><?php endif; ?>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-bordered table-striped align-middle bg-white">
      <thead class="table-light">
        <tr>
          <th width="70">ID</th>
          <th>Název / Slug</th>
          <th width="160">Obrázek</th>
          <th class="text-center" width="120">Stav</th>
          <th class="text-center" width="110">Pořadí</th>
          <th class="text-center" width="260">Akce</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($r = $res->fetch_assoc()): ?>
          <?php
            $badge = $r['status']==='published'
              ? '<span class="badge bg-success">Publikováno</span>'
              : '<span class="badge bg-secondary">Koncept</span>';
            $toggleBtn = $r['status']==='published'
              ? "<a href='status.php?id={$r['id']}&to=draft' class='btn btn-sm btn-outline-secondary me-1' title='Přepnout na koncept'><i class='bi bi-eye-slash'></i></a>"
              : "<a href='status.php?id={$r['id']}&to=published' class='btn btn-sm btn-success me-1' title='Publikovat'><i class='bi bi-check2-circle'></i></a>";
          ?>
          <tr>
            <td class="text-center"><?= (int)$r['id'] ?></td>
            <td>
              <div class="fw-semibold"><?= e($r['title']) ?></div>
              <div class="text-muted small">/<?= e($r['slug']) ?></div>
            </td>
            <td>
              <?php if (!empty($r['image'])): ?>
                <img src="<?= e($r['image']) ?>" alt="" class="img-fluid rounded border" style="max-height:60px">
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td class="text-center"><?= $badge ?></td>
            <td class="text-center"><?= (int)$r['sort_order'] ?></td>
            <td class="text-end text-nowrap">
              <div class="btn-group me-2" role="group">
                <button type="button" class="btn btn-sm btn-outline-secondary btn-move" data-id="<?= (int)$r['id'] ?>" data-dir="up">↑</button>
                <button type="button" class="btn btn-sm btn-outline-secondary btn-move" data-id="<?= (int)$r['id'] ?>" data-dir="down">↓</button>
              </div>
              <?= $toggleBtn ?>
              <a href="edit.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil"></i></a>
              <a href="delete.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Smazat banner?');"><i class="bi bi-trash"></i></a>
            </td>
          </tr>
        <?php endwhile; ?>
        <?php if ($res->num_rows === 0): ?>
          <tr><td colspan="6" class="text-center text-muted">Nic nenalezeno.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

<?php renderPagination($totalPages, $page, $base); ?>
</div>
<script>
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.btn-move');
  if (!btn) return;

  btn.disabled = true;
  try {
    const r = await fetch('reorder.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
      body: 'id=' + encodeURIComponent(btn.dataset.id) + '&dir=' + encodeURIComponent(btn.dataset.dir)
    });

    const text = await r.text(); // nejdřív text
    let j;
    try { j = JSON.parse(text); }
    catch { throw new Error('Server nevrátil JSON:\n' + text.slice(0,300)); }

    if (!j.ok) throw new Error(j.msg || 'Chyba při změně pořadí');
    location.reload();
  } catch(err) {
    alert(err.message);
    btn.disabled = false;
  }
});
</script>


<?php include "../includes/footer.php"; ?>
