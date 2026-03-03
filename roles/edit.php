<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/permissions.php"; // kvůli invalidatePermissionCache()
requirePermission('users');

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
function csrf_field(){ echo '<input type="hidden" name="csrf" value="'.htmlspecialchars($_SESSION['csrf']).'">'; }

$roleId = (int)($_GET['id'] ?? 0);
if ($roleId <= 0) { http_response_code(400); exit("Chybí ID role."); }

$stmt = $conn->prepare("SELECT * FROM roles WHERE id = ?");
$stmt->bind_param("i", $roleId);
$stmt->execute();
$role = $stmt->get_result()->fetch_assoc();
if (!$role) { http_response_code(404); exit("Role nenalezena."); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); exit("Neplatný CSRF token."); }
  $selected = array_map('intval', $_POST['permissions'] ?? []);

  $conn->begin_transaction();
  try {
    $del = $conn->prepare("DELETE FROM role_permission WHERE role_id = ?");
    $del->bind_param("i", $roleId);
    $del->execute();

    if ($selected) {
      $ins = $conn->prepare("INSERT INTO role_permission (role_id, permission_id) VALUES (?, ?)");
      foreach ($selected as $pid) {
        $ins->bind_param("ii", $roleId, $pid);
        $ins->execute();
      }
    }

    $conn->commit();

    // >>> okamžitě zneplatnit cache oprávnění a přesměrovat na list
    invalidatePermissionCache();
    header("Location: list.php?updated=1");
    exit;

  } catch (Throwable $e) {
    $conn->rollback();
    // při chybě spadneme do formuláře s hláškou níže
    $errorMsg = "Chyba ukládání: " . $e->getMessage();
  }
}

$all = $conn->query("SELECT id, name, label FROM permissions ORDER BY label");
$haveStmt = $conn->prepare("SELECT permission_id FROM role_permission WHERE role_id = ?");
$haveStmt->bind_param("i", $roleId);
$haveStmt->execute();
$res = $haveStmt->get_result();
$have = [];
while($row = $res->fetch_assoc()) $have[(int)$row['permission_id']] = true;

include "../includes/header.php";
?>
<div class="container py-4">
  <div class="row">
    <div class="col-12">

      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Práva modulů: <?= htmlspecialchars($role['label']) ?> <small class="text-muted">(<?= htmlspecialchars($role['name']) ?>)</small></h2>
      </div>

      <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
      <?php endif; ?>

      <form method="post" class="bg-white border rounded p-3">
        <?php csrf_field(); ?>

        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="checkAll">
          <label class="form-check-label" for="checkAll">Zaškrtnout/odškrtnout vše</label>
        </div>

        <div class="table-responsive mb-3">
          <table class="table table-bordered align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:60px;"></th>
                <th>Modul</th>
                <th style="width:260px;">Kód</th>
              </tr>
            </thead>
            <tbody>
              <?php while($p = $all->fetch_assoc()): $pid = (int)$p['id']; ?>
                <tr>
                  <td class="text-center">
                    <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= $pid ?>" <?= isset($have[$pid]) ? 'checked' : '' ?>>
                  </td>
                  <td><strong><?= htmlspecialchars($p['label']) ?></strong></td>
                  <td><?= htmlspecialchars($p['name']) ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>

        <div class="d-flex justify-content-between">
          <button class="btn btn-success" type="submit">
            <i class="bi bi-save me-1"></i> Uložit
          </button>
          <a href="list.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i> Zpět
          </a>
        </div>
      </form>

    </div>
  </div>
</div>

<script>
document.getElementById('checkAll')?.addEventListener('change', function(){
  document.querySelectorAll('input[name="permissions[]"]').forEach(cb => cb.checked = this.checked);
});
</script>

<?php include "../includes/footer.php"; ?>
