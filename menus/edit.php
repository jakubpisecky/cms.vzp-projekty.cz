<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('menus');

$id = (int)($_GET['id'] ?? 0);

// den
$stmt = $conn->prepare("SELECT * FROM menu_days WHERE id=?");
$stmt->bind_param("i",$id); $stmt->execute();
$day = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$day) { header("Location: list.php"); exit; }

// sekce
$sections = [];
$rs = $conn->query("SELECT id, name FROM menu_sections ORDER BY sort_order ASC, id ASC");
while ($s = $rs->fetch_assoc()) $sections[] = $s;

// zprávy
$msg = "";

// Přidání položky (jednoduchý POST na stejnou stránku)
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='add_item') {
  $section_id = (int)($_POST['section_id'] ?? 0);
  $title      = trim($_POST['title'] ?? '');
  $price      = ($_POST['price'] ?? '') !== '' ? (float)$_POST['price'] : null;
  $quantity   = ($_POST['quantity'] ?? '') !== '' ? (int)$_POST['quantity'] : null;
  $allergens  = trim($_POST['allergens'] ?? '');

  if ($title==='') $msg = "Název položky je povinný.";
  else {
    // další pořadí v sekci
    $stmt = $conn->prepare("SELECT COALESCE(MAX(sort_order),0)+10 AS n FROM menu_items WHERE day_id=? AND section_id=?");
    $stmt->bind_param("ii",$id,$section_id);
    $stmt->execute(); $next = (int)($stmt->get_result()->fetch_assoc()['n'] ?? 10); $stmt->close();

    $stmt = $conn->prepare("INSERT INTO menu_items (day_id,section_id,title,price,quantity,allergens,sort_order,created_at)
                            VALUES (?,?,?,?,?,?,?, NOW())");
    // i i s d i s i
    $stmt->bind_param("iisdisi", $id,$section_id,$title,$price,$quantity,$allergens,$next);
    $stmt->execute(); $stmt->close();

    header("Location: edit.php?id=".$id); exit;
  }
}

// smazání položky (GET)
if (($_GET['del'] ?? '') !== '') {
  $iid = (int)$_GET['del'];
  $stmt = $conn->prepare("DELETE FROM menu_items WHERE id=? AND day_id=?");
  $stmt->bind_param("ii",$iid,$id); $stmt->execute(); $stmt->close();
  header("Location: edit.php?id=".$id); exit;
}

// data položek
$itemsBySection = [];
$q = $conn->prepare("SELECT * FROM menu_items WHERE day_id=? ORDER BY section_id ASC, sort_order ASC, id ASC");
$q->bind_param("i",$id); $q->execute();
$r = $q->get_result();
while ($row = $r->fetch_assoc()) $itemsBySection[$row['section_id']][] = $row;
$q->close();

include "../includes/header.php";
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">
      Jídelníček &middot; <?= e(date('j. n. Y', strtotime($day['menu_date']))) ?>
      <?php if ($day['status']==='published'): ?>
        <span class="badge bg-success ms-2">Publikováno</span>
      <?php else: ?>
        <span class="badge bg-secondary ms-2">Koncept</span>
      <?php endif; ?>
    </h2>
    <div class="d-flex gap-2">
      <!-- PDF pro tento den -->
      <a class="btn btn-outline-secondary" href="../export/menu_pdf.php?id=<?= (int)$id ?>" target="_blank">
        <i class="bi bi-filetype-pdf"></i> PDF (den)
      </a>

      <?php if ($day['status']==='published'): ?>
        <a class="btn btn-outline-secondary" href="status.php?id=<?= (int)$id ?>&to=draft" title="Přepnout na koncept">
          <i class="bi bi-eye-slash"></i>
        </a>
      <?php else: ?>
        <a class="btn btn-success" href="status.php?id=<?= (int)$id ?>&to=published" title="Publikovat">
          <i class="bi bi-check2-circle"></i>
        </a>
      <?php endif; ?>
      <a href="list.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i> Zpět</a>
    </div>
  </div>

  <?php if ($msg): ?><div class="alert alert-danger"><?= e($msg) ?></div><?php endif; ?>

  <?php foreach ($sections as $sec): ?>
    <?php $sItems = $itemsBySection[$sec['id']] ?? []; ?>
    <div class="card mb-4">
      <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <strong><?= e($sec['name']) ?></strong>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-striped align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Název položky</th>
                <th width="120">Cena</th>
                <th width="110">Množství</th>
                <th width="100">Alergeny</th>
                <th width="180" class="text-end">Akce</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($sItems as $it): ?>
                <tr>
                  <td><?= e($it['title']) ?></td>
                  <td><?= $it['price']!==null ? e(number_format((float)$it['price'], 2, ',', ' ')).' Kč' : '—' ?></td>
                  <td><?= $it['quantity']!==null ? (int)$it['quantity'] : '—' ?></td>
                  <td><?= $it['allergens'] ? e($it['allergens']) : '—' ?></td>
                  <td class="text-end">
                    <div class="btn-group me-2" role="group">
                      <button type="button" class="btn btn-sm btn-outline-secondary btn-move" data-id="<?= (int)$it['id'] ?>" data-dir="up">↑</button>
                      <button type="button" class="btn btn-sm btn-outline-secondary btn-move" data-id="<?= (int)$it['id'] ?>" data-dir="down">↓</button>
                    </div>
                    <a href="?id=<?= (int)$id ?>&del=<?= (int)$it['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Smazat položku?');">
                      <i class="bi bi-trash"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>

              <!-- Přidání nové položky -->
              <tr class="table-secondary">
                <form method="post">
                  <input type="hidden" name="action" value="add_item">
                  <input type="hidden" name="section_id" value="<?= (int)$sec['id'] ?>">
                  <td><input type="text" class="form-control form-control-sm" name="title" placeholder="Název" required></td>
                  <td><input type="number" step="0.01" class="form-control form-control-sm" name="price" placeholder="Cena"></td>
                  <td><input type="number" class="form-control form-control-sm" name="quantity" placeholder="Porce"></td>
                  <td><input type="text" class="form-control form-control-sm" name="allergens" placeholder="např. 1,3,7"></td>
                  <td class="text-end"><button class="btn btn-sm btn-success"><i class="bi bi-plus-lg"></i> Přidat</button></td>
                </form>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Skrytý formulář pro export vybraných položek do PDF (naplní se JS) -->
<form method="post" action="../export/menu_pdf.php" target="_blank" id="exportSelectedForm" class="d-none">
  <input type="hidden" name="mode" value="items">
  <input type="hidden" name="day_id" value="<?= (int)$id ?>">
  <div id="exportSelectedContainer"></div>
</form>

<script>
// Přesun položky v rámci dne (server přeuspořádá v rámci téže sekce)
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.btn-move'); if (!btn) return;
  btn.disabled = true;
  try {
    const r = await fetch('reorder_item.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
      body: 'id=' + encodeURIComponent(btn.dataset.id) + '&dir=' + encodeURIComponent(btn.dataset.dir) + '&day_id=<?= (int)$id ?>'
    });
    const t = await r.text(); let j;
    try { j = JSON.parse(t); } catch { throw new Error('Server nevrátil JSON:\\n' + t.slice(0,200)); }
    if (!j.ok) throw new Error(j.msg || 'Chyba při změně pořadí');
    location.reload();
  } catch(err) {
    alert(err.message);
    btn.disabled = false;
  }
});

// "Vybrat vše" v rámci jedné tabulky/sekce
document.querySelectorAll('.check-all').forEach(cb => {
  cb.addEventListener('change', function() {
    const table = this.closest('table');
    table.querySelectorAll('.item-select').forEach(ch => ch.checked = this.checked);
  });
});

// Export vybraných položek do PDF (horní tlačítko)
function exportSelected() {
  const checked = Array.from(document.querySelectorAll('.item-select:checked'));
  if (checked.length === 0) { alert('Vyber alespoň jednu položku.'); return; }
  const form = document.getElementById('exportSelectedForm');
  const container = document.getElementById('exportSelectedContainer');
  container.innerHTML = '';
  checked.forEach(cb => {
    const inp = document.createElement('input');
    inp.type = 'hidden';
    inp.name = 'item_id[]';
    inp.value = cb.value;
    container.appendChild(inp);
  });
  form.submit();
}

document.getElementById('btnExportSelected')?.addEventListener('click', exportSelected);
// Pokud bys použil i spodní tlačítko, odkomentuj formulář výše a toto:
// document.getElementById('btnExportSelectedBottom')?.addEventListener('click', exportSelected);
</script>

<?php include "../includes/footer.php"; ?>
