<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/permissions.php"; // kvůli invalidatePermissionCache()
requirePermission('users');

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
function csrf_field(){ echo '<input type="hidden" name="csrf" value="'.htmlspecialchars($_SESSION['csrf']).'">'; }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: list.php"); exit; }

// Načtení uživatele k editaci
$stmt = $conn->prepare("SELECT id, email, role FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$editedUser = $stmt->get_result()->fetch_assoc();
if (!$editedUser) { header("Location: list.php"); exit; }

// Načti role pro select
$rolesRes = $conn->query("SELECT id, name, label FROM roles ORDER BY name");
$roles = [];
while ($r = $rolesRes->fetch_assoc()) $roles[(int)$r['id']] = $r;

// Zjisti aktuální roli uživatele z user_role (fallback: podle users.role -> najdi id role stejného name)
$curRoleId = null;
$ur = $conn->prepare("SELECT role_id FROM user_role WHERE user_id = ? LIMIT 1");
$ur->bind_param("i", $id);
$ur->execute();
if ($row = $ur->get_result()->fetch_assoc()) {
    $curRoleId = (int)$row['role_id'];
} else {
    // fallback přes string v users.role
    if (!empty($editedUser['role'])) {
        $find = $conn->prepare("SELECT id FROM roles WHERE name = ? LIMIT 1");
        $find->bind_param("s", $editedUser['role']);
        $find->execute();
        if ($r = $find->get_result()->fetch_assoc()) $curRoleId = (int)$r['id'];
    }
    // poslední fallback: pokud existuje role 'user'
    if ($curRoleId === null) {
        $res = $conn->query("SELECT id FROM roles WHERE name = 'user' LIMIT 1");
        if ($r = $res->fetch_assoc()) $curRoleId = (int)$r['id'];
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400); exit("Neplatný CSRF token.");
    }

    $email    = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $roleId   = (int)($_POST['role_id'] ?? 0);

    // Validace
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Zadej platný e‑mail.";
    }
    if ($roleId <= 0 || empty($roles[$roleId])) {
        $errors[] = "Vyber platnou roli.";
    }

    // Duplicitní e‑mail (kromě tohoto uživatele)
    if (!$errors) {
        $chk = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
        $chk->bind_param("si", $email, $id);
        $chk->execute();
        if ($chk->get_result()->fetch_row()) {
            $errors[] = "Uživatel s tímto e‑mailem už existuje.";
        }
    }

    if (!$errors) {
        $conn->begin_transaction();
        try {
            // 1) update users (email, volitelně heslo, role string pro kompatibilitu)
            $roleName = $roles[$roleId]['name'];

            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $u = $conn->prepare("UPDATE users SET email = ?, password = ?, role = ? WHERE id = ?");
                $u->bind_param("sssi", $email, $hash, $roleName, $id);
            } else {
                $u = $conn->prepare("UPDATE users SET email = ?, role = ? WHERE id = ?");
                $u->bind_param("ssi", $email, $roleName, $id);
            }
            $u->execute();

            // 2) nastav user_role (smaž + vlož)
            $del = $conn->prepare("DELETE FROM user_role WHERE user_id = ?");
            $del->bind_param("i", $id);
            $del->execute();

            $ins = $conn->prepare("INSERT INTO user_role (user_id, role_id) VALUES (?, ?)");
            $ins->bind_param("ii", $id, $roleId);
            $ins->execute();

            $conn->commit();

            // Cache oprávnění (aktuálnímu správci) vyprázdnit a zpět na list
            invalidatePermissionCache();
            header("Location: list.php?updated=1");
            exit;

        } catch (Throwable $e) {
            $conn->rollback();
            $errors[] = "Chyba ukládání: " . $e->getMessage();
        }
    }

    // pro znovuzobrazení formuláře použij poslední vybranou roli
    if ($roleId > 0) $curRoleId = $roleId;
}

include "../includes/header.php";
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Upravit uživatele</h2>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0"><?php foreach ($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <form method="post" class="bg-white p-4 rounded shadow-sm">
        <?php csrf_field(); ?>

        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">E‑mail</label>
                <input type="email" name="email" class="form-control" required
                       value="<?= htmlspecialchars($_POST['email'] ?? $editedUser['email'] ?? '') ?>">
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Nové heslo <small class="text-muted">(ponechte prázdné pro zachování)</small></label>
                <input type="password" name="password" class="form-control" autocomplete="new-password">
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label">Role</label>
            <select name="role_id" class="form-select" required>
                <?php foreach ($roles as $rid => $r): ?>
                    <option value="<?= (int)$rid ?>" <?= ($curRoleId === (int)$rid) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($r['label']) ?> (<?= htmlspecialchars($r['name']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
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
<?php include "../includes/footer.php"; ?>
