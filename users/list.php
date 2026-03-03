<?php 
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('users');

// CSRF pro bezpečné mazání odkazem
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

include "../includes/header.php"; 

// Načti uživatele + roli z M:N (fallback na users.role)
$sql = "
  SELECT 
    u.id,
    u.email,
    u.created_at,
    u.role        AS role_legacy,
    r.name        AS role_name,
    r.label       AS role_label
  FROM users u
  LEFT JOIN user_role ur ON ur.user_id = u.id
  LEFT JOIN roles r      ON r.id = ur.role_id
  ORDER BY u.id ASC
";
$result = $conn->query($sql);

$totalUsers = (int)$conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];

?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Uživatelé <small class="text-muted">(<?= $totalUsers ?>)</small></h2>
    <a href="add.php" class="btn btn-success">
      <i class="bi bi-plus-circle me-1"></i> Přidat uživatele
    </a>
  </div>

  <?php if (isset($_GET['created'])): ?>
    <div class="alert alert-success">Uživatel byl vytvořen.</div>
  <?php endif; ?>

  <?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success">Uživatel byl upraven.</div>
  <?php endif; ?>

  <?php if (isset($_GET['error']) && $_GET['error'] === 'self_delete'): ?>
    <div class="alert alert-danger">Nemůžeš smazat svůj vlastní účet.</div>
  <?php endif; ?>

  <?php if (isset($_GET['error']) && $_GET['error'] === 'not_found'): ?>
    <div class="alert alert-warning">Požadovaný uživatel nebyl nalezen.</div>
  <?php endif; ?>

  <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success">Uživatel byl úspěšně smazán.</div>
  <?php endif; ?>

  <div class="table-responsive">
    <table class="table table-bordered table-striped table-hover bg-white">
      <thead class="table-light">
        <tr>
          <th width="60">ID</th>
          <th>Email</th>
          <th>Role</th>
          <th>Vytvořeno</th>
          <th width="160" class="text-center">Akce</th>
        </tr>
      </thead>
      <tbody>
      <?php while($u = $result->fetch_assoc()): 
          // Fallback: když není vazba v user_role, použij users.role
          $roleText = $u['role_label'] ?: ($u['role_legacy'] ?: '—');
      ?>
        <tr>
          <td><?= (int)$u['id'] ?></td>
          <td><?= htmlspecialchars($u['email']) ?></td>
          <td>
            <?= htmlspecialchars($roleText) ?>
            <?php if ($u['role_label'] && $u['role_name']): ?>
              <small class="text-muted">(<?= htmlspecialchars($u['role_name']) ?>)</small>
            <?php elseif ($u['role_legacy']): ?>
              <small class="text-muted">(<?= htmlspecialchars($u['role_legacy']) ?>)</small>
            <?php endif; ?>
          </td>
          <td><?= formatDateCz($u['created_at']) ?></td>
          <td>
            <a href="edit.php?id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-primary" title="Upravit">
              <i class="bi bi-pencil"></i>
            </a>
            <?php if ($u['id'] != ($_SESSION['admin_id'] ?? 0)): ?>
              <a href="delete.php?id=<?= (int)$u['id'] ?>&csrf=<?= urlencode($_SESSION['csrf']) ?>"
                 class="btn btn-sm btn-danger"
                 onclick="return confirm('Opravdu smazat uživatele?')"
                 title="Smazat">
                <i class="bi bi-trash"></i>
              </a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include "../includes/footer.php"; ?>
