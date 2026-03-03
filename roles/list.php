<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('users');

// CSRF pro mazání (odkazem)
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$totalRoles = (int)$conn->query("SELECT COUNT(*) FROM roles")->fetch_row()[0];
include "../includes/header.php";

$sql = "
  SELECT r.id, r.name, r.label, COUNT(ur.user_id) AS members
  FROM roles r
  LEFT JOIN user_role ur ON ur.role_id = r.id
  GROUP BY r.id, r.name, r.label
  ORDER BY r.name
";
$roles = $conn->query($sql);
?>
<div class="container py-4">
  <div class="row">
    <div class="col-12">

      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Role <small class="text-muted">(<?= $totalRoles ?>)</small></h2>
        <a href="add.php" class="btn btn-success">
          <i class="bi bi-plus-circle me-1"></i> Přidat roli
        </a>
      </div>

      <?php if (isset($_GET['created'])): ?>
        <div class="alert alert-success">Role byla vytvořena.</div>
      <?php endif; ?>

      <?php if (isset($_GET['users_updated'])): ?>
        <div class="alert alert-success">Členové role byli uloženi.</div>
      <?php endif; ?>

      <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success">Role byla upravena.</div>
      <?php endif; ?>

      <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">Role byla smazána.</div>
      <?php endif; ?>

<?php if (isset($_GET['error'])):
    $map = [
      'invalid'  => "Neplatný požadavek.",
      'notfound' => "Role nebyla nalezena.",
      'admin'    => "Systémovou roli „admin“ nelze smazat.",
      'assigned' => "Roli nelze smazat – je přiřazena uživatelům.",
      'server'   => "Při mazání došlo k chybě. Zkuste to znovu.",
    ];
    $msg = $map[$_GET['error']] ?? "Neznámá chyba.";
?>
  <div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

      <div class="table-responsive">
        <table class="table table-bordered table-striped table-hover bg-white">
          <thead>
            <tr>
              <th style="width:80px;">ID</th>
              <th>Systémový název</th>
              <th>Název</th>
              <th style="width:140px;" class="text-center">Uživatelů</th>
              <th style="width:360px;" class="text-center">Akce</th>
            </tr>
          </thead>
          <tbody>
          <?php while($r = $roles->fetch_assoc()): ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td><?= htmlspecialchars($r['name']) ?></td>
              <td><?= htmlspecialchars($r['label']) ?></td>
              <td class="text-center"><?= (int)$r['members'] ?></td>
              <td>
                <a href="users.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-secondary" title="Členové role">
                  <i class="bi bi-people me-1"></i> Členové
                </a>
                <a href="edit.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-warning" title="Práva modulů">
                  <i class="bi bi-shield-check me-1"></i> Práva
                </a>
                <a href="meta.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-primary" title="Upravit název">
                  <i class="bi bi-pencil me-1"></i> Upravit
                </a>
                <?php if ($r['name'] !== 'admin'): ?>
                  <a href="delete.php?id=<?= (int)$r['id'] ?>&csrf=<?= urlencode($_SESSION['csrf']) ?>"
                     class="btn btn-sm btn-danger"
                     onclick="return confirm('Opravdu smazat roli „<?= htmlspecialchars($r['label']) ?>“?');"
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
  </div>
</div>
<?php include "../includes/footer.php"; ?>
