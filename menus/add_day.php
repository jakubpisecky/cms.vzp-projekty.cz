<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('menus');

$msg = "";
$mode = ($_GET['mode'] ?? '') === 'week' ? 'week' : 'day';

// načti sekce (předvyplníme pro každý den)
$sections = [];
$r = $conn->query("SELECT id, name FROM menu_sections ORDER BY sort_order ASC, id ASC");
while ($s = $r->fetch_assoc()) $sections[] = $s;

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if ($mode==='day') {
    $date = $_POST['menu_date'] ?? '';
    if (!$date) $msg = "Vyber datum.";
    else {
      // vytvoř den (ignore pokud existuje)
      $stmt = $conn->prepare("INSERT IGNORE INTO menu_days (menu_date, status, created_at) VALUES (?, 'draft', NOW())");
      $stmt->bind_param("s", $date);
      $stmt->execute(); $stmt->close();
      header("Location: list.php?created=1"); exit;
    }
  } else {
    $start = $_POST['start_date'] ?? '';
    if (!$start) $msg = "Vyber počáteční datum.";
    else {
      $t = strtotime($start);
      // vytvoř 5 pracovních dnů (Po–Pá) od startu
      for ($i=0; $i<5; $i++) {
        $d = date('Y-m-d', strtotime("+{$i} day", $t));
        $stmt = $conn->prepare("INSERT IGNORE INTO menu_days (menu_date, status, created_at) VALUES (?, 'draft', NOW())");
        $stmt->bind_param("s", $d); $stmt->execute(); $stmt->close();
      }
      header("Location: list.php?created=1"); exit;
    }
  }
}

include "../includes/header.php";
?>
<div class="container py-4">
  <h2 class="mb-4"><?= $mode==='week' ? 'Vytvořit týden jídelníčků' : 'Vytvořit den jídelníčku' ?></h2>
  <?php if ($msg): ?><div class="alert alert-danger"><?= e($msg) ?></div><?php endif; ?>

  <form method="post" class="bg-white p-4 rounded shadow-sm" autocomplete="off">
    <?php if ($mode==='week'): ?>
      <div class="row mb-3"><div class="col-md-4">
        <label class="form-label">Počáteční datum</label>
        <input type="date" name="start_date" class="form-control" required>
        <div class="form-text">Vytvoří se 5 po sobě jdoucích pracovních dnů.</div>
      </div></div>
    <?php else: ?>
      <div class="row mb-3"><div class="col-md-4">
        <label class="form-label">Datum</label>
        <input type="date" name="menu_date" class="form-control" required>
      </div></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between">
      <button class="btn btn-success"><i class="bi bi-save me-1"></i> Uložit</button>
      <a href="list.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i> Zpět</a>
    </div>
  </form>
</div>
<?php include "../includes/footer.php"; ?>
