<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('menus');

include "../includes/header.php";

// --- filtry ---
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 14;
$from    = $_GET['from'] ?? '';
$to      = $_GET['to']   ?? '';
$qWhere  = [];
$bind    = []; $types='';

if ($from !== '') { $qWhere[] = "menu_date >= ?"; $bind[]=$from; $types.='s'; }
if ($to   !== '') { $qWhere[] = "menu_date <= ?"; $bind[]=$to;   $types.='s'; }
$whereSql = $qWhere ? ('WHERE '.implode(' AND ',$qWhere)) : '';

// celkový počet
$sqlCount = "SELECT COUNT(*) c FROM menu_days $whereSql";
$stmt = $conn->prepare($sqlCount);
if ($types) $stmt->bind_param($types, ...$bind);
$stmt->execute(); $total = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0); $stmt->close();

$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page-1)*$perPage;

// data
$sql = "SELECT id, menu_date, status, note
        FROM menu_days
        $whereSql
        ORDER BY menu_date DESC
        LIMIT ?, ?";
$stmt = $conn->prepare($sql);
if ($types) {
  $typesAll = $types . 'ii';
  $bindAll  = $bind;
  $bindAll[] = $offset;
  $bindAll[] = $perPage;
  $stmt->bind_param($typesAll, ...$bindAll);
} else {
  $stmt->bind_param('ii', $offset, $perPage);
}
$stmt->execute(); $res = $stmt->get_result();

$base = buildBaseUrl();
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Jídelníčky</h2>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-outline-secondary" id="btnExportSelectedDays">
        <i class="bi bi-filetype-pdf"></i> PDF (vybrané dny)
      </button>
      <a href="add_day.php" class="btn btn-success"><i class="bi bi-plus-circle me-1"></i> Nový den</a>
      <a href="add_day.php?mode=week" class="btn btn-outline-success"><i class="bi bi-calendar-week me-1"></i> Vytvořit týden</a>
    </div>
  </div>

  <form class="row g-2 mb-3" method="get">
    <div class="col-auto"><input type="date" class="form-control" name="from" value="<?= e($from) ?>"></div>
    <div class="col-auto"><input type="date" class="form-control" name="to"   value="<?= e($to) ?>"></div>
    <div class="col-auto"><button class="btn btn-secondary"><i class="bi bi-search me-1"></i> Filtrovat</button></div>
    <?php if ($from || $to): ?><div class="col-auto"><a href="list.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle me-1"></i> Zrušit</a></div><?php endif; ?>
  </form>

  <div class="table-responsive">
    <table class="table table-bordered table-striped align-middle bg-white">
      <thead class="table-light">
        <tr>
          <th width="34"><input type="checkbox" class="form-check-input" id="checkAllDays" title="Vybrat vše na této stránce"></th>
          <th width="140">Datum</th>
          <th class="text-center" width="120">Stav</th>
          <th class="text-end" width="360">Akce</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($d = $res->fetch_assoc()): ?>
          <?php $badge = $d['status']==='published' ? '<span class="badge bg-success">Publikováno</span>' : '<span class="badge bg-secondary">Koncept</span>'; ?>
          <tr>
            <td><input type="checkbox" class="form-check-input day-select" value="<?= (int)$d['id'] ?>"></td>
            <td><strong><?= e(date('j. n. Y', strtotime($d['menu_date']))) ?></strong></td>
            <td class="text-center"><?= $badge ?></td>
            <td class="text-end">
              <a href="edit.php?id=<?= (int)$d['id'] ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil"></i> Upravit</a>
              <a href="duplicate.php?id=<?= (int)$d['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-files"></i> Duplikovat</a>
              <?php
                $pdfDayHref  = "../export/menu_pdf.php?id=".(int)$d['id'];
                [$wStart,$wEnd] = week_range($d['menu_date']);
                $pdfWeekHref = "../export/menu_pdf.php?week_of=".urlencode($wStart);
              ?>
              <a href="<?= $pdfDayHref ?>"  class="btn btn-sm btn-outline-secondary"><i class="bi bi-filetype-pdf"></i> PDF (den)</a>
              <a href="<?= $pdfWeekHref ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-calendar-week"></i> PDF (týden)</a>
              <a href="../emails/menu_send.php?id=<?= (int)$d['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-envelope"></i> E-mail</a>
              <?php if ($d['status']==='published'): ?>
                <a href="status.php?id=<?= (int)$d['id'] ?>&to=draft" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye-slash"></i></a>
              <?php else: ?>
                <a href="status.php?id=<?= (int)$d['id'] ?>&to=published" class="btn btn-sm btn-success"><i class="bi bi-check2-circle"></i></a>
              <?php endif; ?>
              <a href="delete.php?id=<?= (int)$d['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Smazat jídelníček pro tento den?');"><i class="bi bi-trash"></i></a>
            </td>
          </tr>
        <?php endwhile; ?>
        <?php if ($res->num_rows === 0): ?>
          <tr><td colspan="5" class="text-center text-muted">Zatím nic.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php renderPagination($totalPages, $page, $base); ?>
</div>

<!-- skrytý formulář pro PDF z vybraných dnů -->
<form method="post" action="../export/menu_pdf.php" target="_blank" id="exportDaysForm" class="d-none">
  <input type="hidden" name="mode" value="days">
  <div id="exportDaysContainer"></div>
</form>

<script>
document.getElementById('checkAllDays')?.addEventListener('change', function() {
  document.querySelectorAll('.day-select').forEach(cb => cb.checked = this.checked);
});

function exportSelectedDays() {
  const checked = Array.from(document.querySelectorAll('.day-select:checked'));
  if (checked.length === 0) { alert('Vyber alespoň jeden den.'); return; }
  const form = document.getElementById('exportDaysForm');
  const container = document.getElementById('exportDaysContainer');
  container.innerHTML = '';
  checked.forEach(cb => {
    const inp = document.createElement('input');
    inp.type = 'hidden';
    inp.name = 'day_id[]';
    inp.value = cb.value;
    container.appendChild(inp);
  });
  form.submit();
}
document.getElementById('btnExportSelectedDays')?.addEventListener('click', exportSelectedDays);
</script>

<?php include "../includes/footer.php"; ?>
