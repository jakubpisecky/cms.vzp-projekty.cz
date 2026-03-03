<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/permissions.php"; // kvůli invalidatePermissionCache()
requirePermission('users');
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
function csrf_field(){ echo '<input type="hidden" name="csrf" value="'.htmlspecialchars($_SESSION['csrf']).'">'; }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400); exit("Neplatný CSRF token.");
    }

    $label = trim($_POST['label'] ?? '');
    $name  = trim($_POST['name'] ?? '');

    if ($label === '') $errors[] = "Název role je povinný.";

    // Pokud nepřišlo system name, vyrobíme z labelu
    if ($name === '') {
        if (function_exists('slugify')) {
            $name = slugify($label);
        } else {
            $src = $label;
            $trans = @iconv('UTF-8','ASCII//TRANSLIT', $src);
            if ($trans !== false && $trans !== null) $src = $trans;
            $name = strtolower(preg_replace('~[^a-z0-9]+~', '_', $src));
            $name = trim($name, '_');
        }
    }
    // Bezpečnostní úprava system name
    $name = strtolower(preg_replace('~[^a-z0-9_]+~', '', $name));
    if ($name === '') $errors[] = "System name je prázdný.";

    if (!$errors) {
        $stmt = $conn->prepare("INSERT INTO roles (name, label) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $label);

        if ($stmt->execute()) {
            // Invalidate permission cache a redirect jako u ostatních modulů
            invalidatePermissionCache();
            header("Location: list.php?created=1");
            exit;
        } else {
            $errors[] = "Nepodařilo se uložit roli (možná duplicitní system name).";
        }
    }
}

include "../includes/header.php";
?>
<div class="container py-4">
  <div class="row">
    <div class="col-12">

      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Přidat roli</h2>
        <a href="list.php" class="btn btn-success">
          <i class="bi bi-arrow-left-circle me-1"></i> Zpět na role
        </a>
      </div>

      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <ul class="mb-0"><?php foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul>
        </div>
      <?php endif; ?>

      <form method="post" class="bg-white border rounded p-3">
        <?php csrf_field(); ?>
        <div class="mb-3">
          <label class="form-label">Název (label) *</label>
          <input type="text" name="label" class="form-control" required value="<?= htmlspecialchars($_POST['label'] ?? '') ?>">
          <div class="form-text">Zobrazovaný název role, např. „Editor“.</div>
        </div>

        <div class="mb-3">
          <label class="form-label">System name</label>
          <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
          <div class="form-text">Nepovinné. Když necháš prázdné, vytvoří se automaticky z názvu (malá písmena, podtržítka).</div>
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
