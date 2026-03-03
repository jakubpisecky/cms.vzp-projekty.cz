<?php // duplicate.php – duplikace dne do data
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('menus');

$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $target = $_POST['target_date'] ?? '';
  if ($target) {
    // vytvoř cílový den (pokud není)
    $stmt = $conn->prepare("INSERT IGNORE INTO menu_days (menu_date,status,created_at) VALUES (?,'draft',NOW())");
    $stmt->bind_param("s",$target); $stmt->execute(); $stmt->close();

    // zjisti id cíle
    $d = $conn->prepare("SELECT id FROM menu_days WHERE menu_date=?");
    $d->bind_param("s",$target); $d->execute(); $toDay = (int)($d->get_result()->fetch_assoc()['id'] ?? 0); $d->close();

    if ($toDay) {
      // smaž existující položky v cíli
      $conn->query("DELETE FROM menu_items WHERE day_id={$toDay}");
      // zkopíruj položky
      $res = $conn->query("SELECT section_id,title,price,quantity,flags,allergens,sort_order FROM menu_items WHERE day_id={$id}");
      $ins = $conn->prepare("INSERT INTO menu_items (day_id,section_id,title,price,quantity,flags,allergens,sort_order,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())");
      while ($r = $res->fetch_assoc()) {
        $ins->bind_param("iisdiisi", $toDay,$r['section_id'],$r['title'],$r['price'],$r['quantity'],$r['flags'],$r['allergens'],$r['sort_order']);
        $ins->execute();
      }
      $ins->close();
    }
    header("Location: list.php?created=1"); exit;
  }
}

include "../includes/header.php";
?>
<div class="container py-4">
  <h2 class="mb-4">Duplikovat jídelníček do data</h2>
  <form method="post" class="bg-white p-4 rounded shadow-sm">
    <div class="row mb-3"><div class="col-md-4">
      <label class="form-label">Cílové datum</label>
      <input type="date" name="target_date" class="form-control" required>
    </div></div>
    <div class="d-flex justify-content-between">
      <button class="btn btn-primary"><i class="bi bi-files me-1"></i> Duplikovat</button>
      <a href="list.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i> Zpět</a>
    </div>
  </form>
</div>
<?php include "../includes/footer.php"; ?>
