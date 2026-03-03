<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/permissions.php"; 
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

$errors = [];
$errorMsg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400); exit("Neplatný CSRF token.");
    }

    $label = trim($_POST['label'] ?? '');
    $name  = trim($_POST['name'] ?? '');

    if ($label === '') $errors[] = "Název role je povinný.";
    if ($name === '')  $errors[] = "System name je povinný.";

    // Sanitizace system name (malá písmena, čísla, podtržítka)
    $name = strtolower(preg_replace('~[^a-z0-9_]+~', '', $name));

    if (!$errors) {
        try {
            $upd = $conn->prepare("UPDATE roles SET name = ?, label = ? WHERE id = ?");
            $upd->bind_param("ssi", $name, $label, $roleId);
            if ($upd->execute()) {
                // refresh session cache oprávnění a redirect na seznam
                invalidatePermissionCache();
                header("Location: list.php?updated=1");
                exit;
            } else {
                $errorMsg = "Nepodařilo se uložit (možná duplicitní system name).";
            }
        } catch (Throwable $e) {
            $errorMsg = "Chyba ukládání: " . $e->getMessage();
        }
    }
}

// znovu načtení role pro formulář (při prvním zobrazení nebo při chybě)
if (!empty($errors) || !empty($errorMsg)) {
    // ponecháme uživatelem zadané hodnoty v inputs
} else {
    // hodnoty jsou už v $role
}

include "../includes/header.php";
?>
<div class="container py-4">
  <div class="row">
    <div class="col-12">

      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Upravit roli</h2>
        <a href="list.php" class="btn btn-success">
          <i class="bi bi-arrow-left-circle me-1"></i> Zpět na role
        </a>
      </div>

      <?php if ($errorMsg): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
      <?php endif; ?>
      <?php if ($errors): ?>
        <div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul></div>
      <?php endif; ?>

      <form method="post" class="bg-white border rounded p-3">
        <?php csrf_field(); ?>
        <div class="mb-3">
          <label class="form-label">Název (label) *</label>
          <input type="text" name="label" class="form-control" required
                 value="<?= htmlspecialchars($_POST['label'] ?? $role['label'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">System name *</label>
          <input type="text" name="name" class="form-control" required
                 value="<?= htmlspecialchars($_POST['name'] ?? $role['name'] ?? '') ?>">
          <div class="form-text">Malá písmena, čísla a podtržítka (např. <code>editor</code>).</div>
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
<?php include "../includes/footer.php"; ?>
