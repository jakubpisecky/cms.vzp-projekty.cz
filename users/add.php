<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('users');

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
function csrf_field(){ echo '<input type="hidden" name="csrf" value="'.htmlspecialchars($_SESSION['csrf']).'">'; }

$msg = "";
$errors = [];

// Načteme role pro select
$rolesRes = $conn->query("SELECT id, name, label FROM roles ORDER BY name");
$roles = [];
while ($r = $rolesRes->fetch_assoc()) $roles[(int)$r['id']] = $r;

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
    if ($password === '' || strlen($password) < 6) {
        $errors[] = "Heslo musí mít alespoň 6 znaků.";
    }
    if ($roleId <= 0 || empty($roles[$roleId])) {
        $errors[] = "Vyber platnou roli.";
    }

    // Duplicitní e‑mail (rychlá kontrola)
    if (!$errors) {
        $chk = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $chk->bind_param("s", $email);
        $chk->execute();
        if ($chk->get_result()->fetch_row()) {
            $errors[] = "Uživatel s tímto e‑mailem už existuje.";
        }
    }

    if (!$errors) {
        $hash     = password_hash($password, PASSWORD_DEFAULT);
        $roleName = $roles[$roleId]['name']; // pro sloupec users.role (legacy)

        $conn->begin_transaction();
        try {
            // 1) users
            $insUser = $conn->prepare("INSERT INTO users (email, password, role, created_at) VALUES (?,?,?,NOW())");
            $insUser->bind_param("sss", $email, $hash, $roleName);
            $insUser->execute();
            $userId = (int)$conn->insert_id;

            // 2) user_role (M:N)
            $insUR = $conn->prepare("INSERT INTO user_role (user_id, role_id) VALUES (?, ?)");
            $insUR->bind_param("ii", $userId, $roleId);
            $insUR->execute();

            $conn->commit();

            header("Location: list.php?created=1");
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            $errors[] = "Chyba ukládání: " . $e->getMessage();
        }
    }
}

include "../includes/header.php";
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Přidat uživatele</h2>
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
                <input type="email" name="email" class="form-control" autocomplete="off" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Heslo</label>
                <input type="password" name="password" class="form-control" autocomplete="new-password" required>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label">Role</label>
            <select name="role_id" class="form-select" required>
                <?php foreach ($roles as $rid => $r): ?>
                    <option value="<?= (int)$rid ?>"
                        <?= (($_POST['role_id'] ?? '') == $rid || ($r['name'] === 'user' && !isset($_POST['role_id']))) ? 'selected' : '' ?>>
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
