<?php 
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('articles');
include "../includes/header.php";

$q       = trim($_GET['q'] ?? '');
$isAdmin = isAdmin();
$uid     = intval($_SESSION['admin_id'] ?? 0);

// --- Stránkování ---
$perPage = 20;
$page    = max(1, intval($_GET['page'] ?? 1));

// --- Společné SQL části ---
$joins = "
LEFT JOIN article_category ac ON a.id = ac.article_id
LEFT JOIN categories c ON ac.category_id = c.id
LEFT JOIN users u ON a.author_id = u.id
";

$where  = [];
$types  = '';
$params = [];

if (!$isAdmin) {
    $where[] = "a.author_id = ?";
    $types   .= "i";
    $params[] = $uid;
}
if ($q !== '') {
    $where[] = "a.title LIKE ?";
    $types   .= "s";
    $like     = "%{$q}%";
    $params[] = $like;
}
$whereSql = $where ? (" WHERE " . implode(" AND ", $where)) : "";

// --- 1) Počet záznamů (COUNT) ---
$sqlCount = "SELECT COUNT(DISTINCT a.id) AS cnt FROM articles a {$joins} {$whereSql}";
$stmt = $conn->prepare($sqlCount);
if ($types !== '') {
    $bind = []; $bind[] = &$types;
    foreach ($params as $k => $v) { $bind[] = &$params[$k]; }
    call_user_func_array([$stmt, 'bind_param'], $bind);
}
$stmt->execute();
$total = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt->close();

$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

// --- 2) Data (SELECT s GROUP_CONCAT + LIMIT) ---
$sqlData = "
SELECT a.*, GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') AS categories, u.email AS author
FROM articles a
{$joins}
{$whereSql}
GROUP BY a.id
ORDER BY a.created_at DESC
LIMIT ?, ?
";
$stmt = $conn->prepare($sqlData);

// bind paramy: původní + offset/limit
$typesData  = $types . "ii";
$paramsData = $params;
$paramsData[] = $offset;
$paramsData[] = $perPage;

$bind = []; $bind[] = &$typesData;
foreach ($paramsData as $k => $v) { $bind[] = &$paramsData[$k]; }
call_user_func_array([$stmt, 'bind_param'], $bind);

$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

// --- Základ URL pro odkazy (zachová q) ---
$paramsUrl = $_GET; unset($paramsUrl['page']);
$base = http_build_query($paramsUrl);
$base = $base ? ('?' . $base . '&') : '?';
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Články <small class="text-muted">(<?= $total ?>)</small></h2>
    <a href="add.php" class="btn btn-success"><i class="bi bi-plus-circle me-1"></i> Přidat článek</a>
  </div>

  <!-- Hledání -->
  <form method="get" class="mb-3">
    <div class="row g-2">
      <div class="col-lg-6">
        <input type="text" name="q" class="form-control" placeholder="Hledat podle názvu…"
               value="<?= htmlspecialchars($q) ?>">
      </div>
      <div class="col-auto">
        <button class="btn btn-secondary"><i class="bi bi-search me-1"></i> Hledat</button>
      </div>
      <?php if ($q !== ''): ?>
        <div class="col-auto">
          <a class="btn btn-outline-secondary" href="list.php"><i class="bi bi-x-circle me-1"></i> Zrušit</a>
        </div>
      <?php endif; ?>
    </div>
  </form>

  <?php if (isset($_GET['created'])): ?><div class="alert alert-success">Článek byl vytvořen.</div><?php endif; ?>
  <?php if (isset($_GET['updated'])): ?><div class="alert alert-success">Článek byl upraven.</div><?php endif; ?>
  <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Článek byl smazán.</div><?php endif; ?>

  <?php if (isset($_GET['toggled'])): ?>
    <?php
      $msg = ($_GET['toggled'] === 'published') ? 'Článek byl publikován.' : 'Článek byl přepnut na koncept.';
    ?>
    <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <?php if (isset($_GET['error']) && $_GET['error'] === 'not_found'): ?>
    <div class="alert alert-danger">Článek nebyl nalezen nebo k němu nemáte přístup.</div>
  <?php endif; ?>

  <div class="table-responsive">
    <table class="table table-bordered table-striped table-hover bg-white">
      <thead class="table-light text-center">
        <tr>
          <th>ID</th>
          <th>Název</th>
          <th>Kategorie</th>
          <th>Autor</th>
          <th>Datum publikace</th>
          <th>Stav</th>
          <th>Akce</th>
        </tr>
      </thead>
      <tbody>
      <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
          <td class="text-center"><?= (int)$row['id'] ?></td>
          <td><?= htmlspecialchars($row['title'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['categories'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['author'] ?? '') ?></td>
          <td class="text-nowrap"><?= formatDateCz($row['publish_date']) ?></td>
          <td class="text-center">
            <?php
              $status = $row['status'] ?? 'draft';
              $isPublished = ($status === 'published');
              $badgeClass = $isPublished ? 'bg-success' : 'bg-secondary';
              $label = $isPublished ? 'Publikováno' : 'Koncept';
            ?>
            <span class="badge <?= $badgeClass ?>"><?= $label ?></span>
          </td>
          <td class="text-center text-nowrap">
            <?php if ($isPublished): ?>
              <a href="status.php?id=<?= (int)$row['id'] ?>&to=draft"
                 class="btn btn-sm btn-outline-secondary me-1" title="Přepnout na koncept">
                 <i class="bi bi-eye-slash"></i>
              </a>
            <?php else: ?>
              <a href="status.php?id=<?= (int)$row['id'] ?>&to=published"
                 class="btn btn-sm btn-success me-1" title="Publikovat">
                 <i class="bi bi-check2-circle"></i>
              </a>
            <?php endif; ?>

            <a href="edit.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-primary me-1">
              <i class="bi bi-pencil-square"></i>
            </a>
            <a href="delete.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-danger"
               onclick="return confirm('Opravdu chcete článek smazat?')">
              <i class="bi bi-trash"></i>
            </a>
          </td>
        </tr>
      <?php endwhile; ?>
      <?php if ($result->num_rows === 0): ?>
        <tr><td colspan="7" class="text-center text-muted">Žádné články nenalezeny.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Stránkování -->
  <?php if ($totalPages > 1): ?>
    <?php
      $window = 2;
      $pages = [1];
      $start = max(2, $page - $window);
      $end   = min($totalPages - 1, $page + $window);
      if ($start > 2) $pages[] = '...';
      for ($i = $start; $i <= $end; $i++) $pages[] = $i;
      if ($end < $totalPages - 1) $pages[] = '...';
      if ($totalPages > 1) $pages[] = $totalPages;
      $plink = fn(int $p) => $base . 'page=' . $p;
    ?>
    <nav aria-label="Stránkování">
      <ul class="pagination justify-content-center mt-3">
        <li class="page-item <?= $page===1 ? 'disabled':'' ?>">
          <a class="page-link" href="<?= $page===1 ? '#' : $plink(1) ?>" aria-label="První">«</a>
        </li>
        <li class="page-item <?= $page===1 ? 'disabled':'' ?>">
          <a class="page-link" href="<?= $page===1 ? '#' : $plink($page-1) ?>" aria-label="Předchozí">‹</a>
        </li>
        <?php foreach ($pages as $p): ?>
          <?php if ($p === '...'): ?>
            <li class="page-item disabled"><span class="page-link">…</span></li>
          <?php else: ?>
            <li class="page-item <?= $p===$page ? 'active':'' ?>">
              <a class="page-link" href="<?= $plink($p) ?>"><?= $p ?></a>
            </li>
          <?php endif; ?>
        <?php endforeach; ?>
        <li class="page-item <?= $page===$totalPages ? 'disabled':'' ?>">
          <a class="page-link" href="<?= $page===$totalPages ? '#' : $plink($page+1) ?>" aria-label="Další">›</a>
        </li>
        <li class="page-item <?= $page===$totalPages ? 'disabled':'' ?>">
          <a class="page-link" href="<?= $page===$totalPages ? '#' : $plink($totalPages) ?>" aria-label="Poslední">»</a>
        </li>
      </ul>
    </nav>
  <?php endif; ?>
</div>

<?php include "../includes/footer.php"; ?>
