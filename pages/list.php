<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('pages');

include "../includes/header.php";

$q               = trim($_GET['q'] ?? '');
$perPageDefault  = 20;
$page            = max(1, (int)($_GET['page'] ?? 1));
$toggled         = $_GET['toggled'] ?? null;
$errorParam      = $_GET['error'] ?? null;

$created = isset($_GET['created']);
$updated = isset($_GET['updated']);
$deleted = isset($_GET['deleted']);

/** ===== badge/stav ===== */
function pageStatusBadge(string $status): string {
    return $status === 'published'
        ? '<span class="badge bg-success">Publikováno</span>'
        : '<span class="badge bg-secondary">Koncept</span>';
}

/** ===== render uzlu stromu ===== */
function renderNode(array $n, int $level = 0): void {
    $pad   = str_repeat("&nbsp;&nbsp;&nbsp;", $level);
    $badge = pageStatusBadge($n['status']);

    $id        = (int)$n['id'];
    $parent_id = (int)$n['parent_id'];
    $order     = (int)$n['menu_order'];
    $title     = htmlspecialchars($n['title'] ?? '');
    $slug      = htmlspecialchars($n['slug'] ?? '');
    $isPublished = ($n['status'] === 'published');

    $toggleBtn = $isPublished
        ? "<a href='status.php?id={$id}&to=draft' class='btn btn-sm btn-outline-secondary me-1' title='Přepnout na koncept'><i class=\"bi bi-eye-slash\"></i></a>"
        : "<a href='status.php?id={$id}&to=published' class='btn btn-sm btn-success me-1' title='Publikovat'><i class=\"bi bi-check2-circle\"></i></a>";

    echo "<tr class='page-row'
              data-id='{$id}'
              data-parent='{$parent_id}'
              data-level='{$level}'>
        <td width='60' class='text-center'>{$id}</td>
        <td>{$pad}<i class='bi bi-folder2-open me-1'></i>{$title} <small class='text-muted'>/{$slug}</small></td>
        <td class='text-center' width='110'>{$badge}</td>
        <td class='text-center' width='110'>{$order}</td>
        <td width='260' class='text-end text-nowrap'>
            <div class='btn-group me-2' role='group' aria-label='Move'>
                <button type='button' class='btn btn-sm btn-outline-secondary btn-move' data-id='{$id}' data-dir='up' title='Posunout nahoru'>↑</button>
                <button type='button' class='btn btn-sm btn-outline-secondary btn-move' data-id='{$id}' data-dir='down' title='Posunout dolů'>↓</button>
            </div>
            {$toggleBtn}
            <a href='add.php?parent_id={$id}' class='btn btn-sm btn-success'><i class='bi bi-plus-circle me-1'></i>Podstránka</a>
            <a href='edit.php?id={$id}' class='btn btn-sm btn-primary'><i class='bi bi-pencil'></i></a>
            <a href='delete.php?id={$id}' class='btn btn-sm btn-danger' onclick=\"return confirm('Smazat stránku? Podstránky budou přemístěny na úroveň rodiče.');\"><i class='bi bi-trash'></i></a>
        </td>
    </tr>";

    foreach ($n['children'] as $c) renderNode($c, $level + 1);
}

/** ===== celkové počty pro hlavičku (sjednoceno jako u jiných modulů) ===== */
$totalAll = (int)$conn->query("SELECT COUNT(*) FROM pages")->fetch_row()[0];                 // všechny stránky
$totalRoots = (int)$conn->query("SELECT COUNT(*) FROM pages WHERE parent_id = 0")->fetch_row()[0]; // rubriky (kořeny)

/* ========================= REŽIM 1: HLEDÁNÍ ========================= */
if ($q !== '') {
    $perPage = $perPageDefault;
    $like    = "%{$q}%";

    $sqlCount = "SELECT COUNT(*) AS cnt FROM pages p WHERE (p.title LIKE ? OR p.slug LIKE ?)";
    $stmt = $conn->prepare($sqlCount);
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $total = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $stmt->close();

    $totalPages = max(1, (int)ceil($total / $perPage));
    if ($page > $totalPages) $page = $totalPages;
    $offset = (int)(($page - 1) * $perPage);
    $limit  = (int)$perPage;

    $sqlData = "
        SELECT p.*, parent.title AS parent_title
        FROM pages p
        LEFT JOIN pages parent ON parent.id = p.parent_id
        WHERE (p.title LIKE ? OR p.slug LIKE ?)
        ORDER BY p.parent_id ASC, p.menu_order ASC, p.id ASC
        LIMIT ?, ?
    ";
    $stmt = $conn->prepare($sqlData);
    $stmt->bind_param("ssii", $like, $like, $offset, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $base = buildBaseUrl();
    ?>
    <div class="container py-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">
          Stránky <small class="text-muted">(<?= $totalAll ?>)</small>
          <small class="text-muted ms-2">Vyhledáno: <?= $total ?></small>
        </h2>
        <a href="add.php" class="btn btn-success"><i class="bi bi-plus-circle me-1"></i> Nová stránka</a>
      </div>

      <?php if ($toggled): ?>
        <?php $msg = ($toggled === 'published') ? 'Stránka byla publikována.' : 'Stránka byla přepnuta na koncept.'; ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>
      <?php if ($errorParam === 'not_found'): ?>
        <div class="alert alert-danger">Stránka nebyla nalezena.</div>
      <?php endif; ?>

      <?php if ($created): ?>
        <div class="alert alert-success">Stránka byla vytvořena.</div>
      <?php endif; ?>
      <?php if ($updated): ?>
        <div class="alert alert-success">Stránka byla upravena.</div>
      <?php endif; ?>
      <?php if ($deleted): ?>
        <div class="alert alert-success">Stránka byla smazána.</div>
      <?php endif; ?>



      <form method="get" class="mb-3">
        <div class="row g-2">
          <div class="col-lg-6">
            <input type="text" name="q" class="form-control" placeholder="Hledat v názvu nebo URL…" value="<?= htmlspecialchars($q) ?>">
          </div>
          <div class="col-auto"><button class="btn btn-secondary"><i class="bi bi-search me-1"></i> Hledat</button></div>
          <div class="col-auto"><a class="btn btn-outline-secondary" href="list.php"><i class="bi bi-x-circle me-1"></i> Zrušit</a></div>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-bordered table-striped bg-white align-middle">
          <thead class="table-light">
            <tr>
              <th width="60">ID</th>
              <th>Název / URL</th>
              <th width="220">Nadřazená</th>
              <th class="text-center" width="110">Stav</th>
              <th class="text-center" width="110">Pořadí</th>
              <th class="text-center" width="260">Akce</th>
            </tr>
          </thead>
          <tbody>
          <?php while ($n = $result->fetch_assoc()): ?>
            <?php
              $badge = pageStatusBadge($n['status']);
              $isPublished = ($n['status'] === 'published');
              $toggleBtn = $isPublished
                ? "<a href='status.php?id=".(int)$n['id']."&to=draft' class='btn btn-sm btn-outline-secondary me-1' title='Přepnout na koncept'><i class=\"bi bi-eye-slash\"></i></a>"
                : "<a href='status.php?id=".(int)$n['id']."&to=published' class='btn btn-sm btn-success me-1' title='Publikovat'><i class=\"bi bi-check2-circle\"></i></a>";
            ?>
            <tr>
              <td class="text-center"><?= (int)$n['id'] ?></td>
              <td><?= htmlspecialchars($n['title']) ?> <small class="text-muted">/<?= htmlspecialchars($n['slug']) ?></small></td>
              <td><?= htmlspecialchars($n['parent_title'] ?: '—') ?></td>
              <td class="text-center"><?= $badge ?></td>
              <td class="text-center"><?= (int)$n['menu_order'] ?></td>
              <td class="text-end text-nowrap">
                <?= $toggleBtn ?>
                <a href="add.php?parent_id=<?= (int)$n['id'] ?>" class="btn btn-sm btn-success"><i class="bi bi-plus-circle me-1"></i>Podstránka</a>
                <a href="edit.php?id=<?= (int)$n['id'] ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil"></i></a>
                <a href="delete.php?id=<?= (int)$n['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Smazat stránku? Podstránky budou přemístěny na úroveň rodiče.');"><i class="bi bi-trash"></i></a>
              </td>
            </tr>
          <?php endwhile; ?>
          <?php if ($result->num_rows === 0): ?>
            <tr><td colspan="6" class="text-center text-muted">Nic nenalezeno.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php renderPagination($totalPages, $page, $base = buildBaseUrl()); ?>
    </div>
    <?php
    include "../includes/footer.php";
    exit;
}

/* ====================== REŽIM 2: STROM + řazení kořenů ====================== */

// načti všechny stránky, slož strom, stránkuj jen kořeny
$res  = $conn->query("SELECT * FROM pages ORDER BY parent_id ASC, menu_order ASC, id ASC");
$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;

$tree = []; $byId = [];
foreach ($rows as $r) { $r['children'] = []; $byId[$r['id']] = $r; }
foreach ($byId as $id => &$node) {
    if ((int)$node['parent_id'] === 0) $tree[] = &$node;
    else $byId[$node['parent_id']]['children'][] = &$node;
}
unset($node);

$perPage    = $perPageDefault;
$totalRoots = count($tree); // pro stránkování kořenů (počet rubrik máme už v $totalRoots z DB – sedět to bude)
$totalPages = max(1, (int)ceil($totalRoots / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset     = ($page - 1) * $perPage;
$rootsPage  = array_slice($tree, $offset, $perPage);
$base = buildBaseUrl();
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">
      Stránky <small class="text-muted">(<?= $totalAll ?>)</small>
    </h2>
    <a href="add.php" class="btn btn-success"><i class="bi bi-plus-circle me-1"></i> Nová stránka</a>
  </div>

  <?php if ($toggled): ?>
    <?php $msg = ($toggled === 'published') ? 'Stránka byla publikována.' : 'Stránka byla přepnuta na koncept.'; ?>
    <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>
  <?php if ($errorParam === 'not_found'): ?>
    <div class="alert alert-danger">Stránka nebyla nalezena.</div>
  <?php endif; ?>

        <?php if ($created): ?>
        <div class="alert alert-success">Stránka byla vytvořena.</div>
      <?php endif; ?>
      <?php if ($updated): ?>
        <div class="alert alert-success">Stránka byla upravena.</div>
      <?php endif; ?>
      <?php if ($deleted): ?>
        <div class="alert alert-success">Stránka byla smazána.</div>
      <?php endif; ?>

  <form method="get" class="mb-3">
    <div class="row g-2">
      <div class="col-lg-6">
        <input type="text" name="q" class="form-control" placeholder="Hledat v názvu nebo URL…" value="">
      </div>
      <div class="col-auto"><button class="btn btn-secondary"><i class="bi bi-search me-1"></i> Hledat</button></div>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-bordered table-striped table-hover bg-white">
      <thead class="table-light">
        <tr>
          <th width="60">ID</th>
          <th>Název / URL</th>
          <th class="text-center" width="110">Stav</th>
          <th class="text-center" width="110">Pořadí</th>
          <th class="text-center" width="260">Akce</th>
        </tr>
      </thead>
      <tbody id="pagesTableBody">
        <?php foreach ($rootsPage as $n) renderNode($n); ?>
      </tbody>
    </table>
  </div>

  <?php renderPagination($totalPages, $page, $base); ?>
</div>

<script>
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.btn-move');
  if (!btn) return;
  const id  = btn.getAttribute('data-id');
  const dir = btn.getAttribute('data-dir');

  btn.disabled = true;

  try {
    const r = await fetch('reorder.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
      body: 'id=' + encodeURIComponent(id) + '&dir=' + encodeURIComponent(dir)
    });
    const j = await r.json();
    if (!j.ok) throw new Error(j.msg || 'Chyba při změně pořadí');
    location.reload();
  } catch(err) {
    alert(err.message);
    btn.disabled = false;
  }
});
</script>

<?php include "../includes/footer.php"; ?>
