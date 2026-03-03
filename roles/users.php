<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
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

$errorMsg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
      http_response_code(400); exit("Neplatný CSRF token.");
  }

  $selectedUsers = array_map('intval', $_POST['users'] ?? []);

  $conn->begin_transaction();
  try {
    // Smaž stávající přiřazení role -> uživatelé
    $del = $conn->prepare("DELETE FROM user_role WHERE role_id = ?");
    $del->bind_param("i", $roleId);
    $del->execute();

    // Vlož nová přiřazení
    if ($selectedUsers) {
      $ins = $conn->prepare("INSERT INTO user_role (user_id, role_id) VALUES (?, ?)");
      foreach ($selectedUsers as $uid) {
        $ins->bind_param("ii", $uid, $roleId);
        $ins->execute();
      }
    }

    $conn->commit();

    // Invalidate permission cache a redirect jako u add.php
    require_once "../includes/permissions.php";
    invalidatePermissionCache();
    header("Location: list.php?users_updated=1");
    exit;

  } catch (Throwable $e) {
    $conn->rollback();
    $errorMsg = "Chyba ukládání: " . $e->getMessage();
  }
}

// Data pro formulář
$users = $conn->query("SELECT id, email FROM users ORDER BY email");

$haveStmt = $conn->prepare("SELECT user_id FROM user_role WHERE role_id = ?");
$haveStmt->bind_param("i", $roleId);
$haveStmt->execute();
$res = $haveStmt->get_result();
$inRole = [];
while($row = $res->fetch_assoc()) $inRole[(int)$row['user_id']] = true;

include "../includes/header.php";
?>
<div class="container py-4">
  <div class="row">
    <div class="col-12">

      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Členové role: <?= htmlspecialchars($role['label']) ?> <small class="text-muted">(<code><?= htmlspecialchars($role['name']) ?></code>)</small></h2>
        <a href="list.php" class="btn btn-success">
          <i class="bi bi-arrow-left-circle me-1"></i> Zpět na role
        </a>
      </div>

      <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
      <?php endif; ?>

      <form method="post" class="bg-white border rounded p-3">
        <?php csrf_field(); ?>

        <div class="table-responsive mb-3">
          <table class="table table-bordered mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th style="width:60px;"></th>
                <th>Email</th>
              </tr>
            </thead>
            <tbody>
              <?php while($u = $users->fetch_assoc()):
                $uid = (int)$u['id']; ?>
                <tr>
                  <td class="text-center">
                    <input class="form-check-input usercb" type="checkbox" name="users[]" value="<?= $uid ?>" <?= isset($inRole[$uid]) ? 'checked' : '' ?>>
                  </td>
                  <td><?= htmlspecialchars($u['email']) ?></td>
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
<?php include "../includes/footer.php"; ?>
