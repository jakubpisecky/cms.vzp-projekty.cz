<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

if (session_status() === PHP_SESSION_NONE) session_start();

$user = getCurrentUser();
if (empty($user['id'])) {
    header("Location: ../login.php");
    exit;
}

$msg = "";
$msgType = "info";

// CSRF – jednou do session
if (empty($_SESSION['form_csrf'])) {
    $_SESSION['form_csrf'] = bin2hex(random_bytes(16));
}
$formCsrf = $_SESSION['form_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = $_POST['csrf'] ?? '';
    $current    = trim($_POST['current_password'] ?? '');
    $password   = trim($_POST['password'] ?? '');
    $password2  = trim($_POST['password2'] ?? '');

    if (!hash_equals($formCsrf, $postedCsrf)) {
        $msg = "Neplatný požadavek (CSRF). Zkuste to znovu.";
        $msgType = "danger";
    } elseif ($current === '' || $password === '' || $password2 === '') {
        $msg = "Vyplňte všechna pole.";
        $msgType = "danger";
    } elseif ($password !== $password2) {
        $msg = "Hesla se neshodují.";
        $msgType = "danger";
    } elseif (strlen($password) < 8) {
        $msg = "Heslo musí mít alespoň 8 znaků.";
        $msgType = "warning";
    } else {
        // načteme aktuální hash
        $stmt = $conn->prepare("SELECT password, email FROM users WHERE id=? LIMIT 1");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $stmt->bind_result($currentHash, $email);
        $stmt->fetch();
        $stmt->close();

        if (!$currentHash || !password_verify($current, $currentHash)) {
            $msg = "Aktuální heslo nesouhlasí.";
            $msgType = "danger";
        } elseif (password_verify($password, $currentHash)) {
            $msg = "Nové heslo nesmí být stejné jako současné.";
            $msgType = "warning";
        } else {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password=?, password_changed_at=NOW() WHERE id=?");
            $stmt->bind_param("si", $newHash, $user['id']);
            $stmt->execute();
            $ok = $stmt->affected_rows >= 0;
            $stmt->close();

            if ($ok) {
                // bezpečnost: regenerace session ID
                session_regenerate_id(true);
                logAction("Uživatel '{$email}' změnil své heslo");
                $msg = "Heslo bylo úspěšně změněno.";
                $msgType = "success";
                // zneplatni CSRF, aby se formulář neodeslal znovu
                unset($_SESSION['form_csrf']);
                $_SESSION['form_csrf'] = bin2hex(random_bytes(16));
                $formCsrf = $_SESSION['form_csrf'];
            } else {
                $msg = "Nastala chyba při ukládání hesla. Zkuste to prosím znovu.";
                $msgType = "danger";
            }
        }
    }
}

include "../includes/header.php";
?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <h2 class="mb-4 text-center"><i class="bi bi-key"></i> Změna hesla</h2>

          <?php if ($msg): ?>
            <div class="alert alert-<?= htmlspecialchars($msgType) ?>"><?= htmlspecialchars($msg) ?></div>
          <?php endif; ?>

          <form method="post" autocomplete="off" novalidate>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($formCsrf) ?>">

            <div class="mb-3">
              <label class="form-label">Aktuální heslo</label>
              <div class="input-group">
                <input type="password" name="current_password" id="current_password" class="form-control" required autocomplete="current-password">
                <button type="button" class="btn btn-outline-secondary toggle-password" data-target="#current_password">
                  <i class="bi bi-eye"></i> Zobrazit
                </button>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Nové heslo</label>
              <div class="input-group">
                <input type="password" name="password" id="password" class="form-control" required autocomplete="new-password">
                <button type="button" class="btn btn-outline-secondary toggle-password" data-target="#password">
                  <i class="bi bi-eye"></i> Zobrazit
                </button>
              </div>
              <small id="passwordStrength" class="form-text mt-1"></small>
            </div>

            <div class="mb-3">
              <label class="form-label">Potvrzení hesla</label>
              <div class="input-group">
                <input type="password" name="password2" id="password2" class="form-control" required autocomplete="new-password">
                <button type="button" class="btn btn-outline-secondary toggle-password" data-target="#password2">
                  <i class="bi bi-eye"></i> Zobrazit
                </button>
              </div>
            </div>

            <div class="d-grid gap-2">
              <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Změnit heslo</button>
              <a href="../dashboard.php" class="btn btn-outline-secondary">Zpět na dashboard</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// zobrazit/skrýt
document.addEventListener('click', function (e) {
  const btn = e.target.closest('.toggle-password');
  if (!btn) return;
  const input = document.querySelector(btn.dataset.target);
  if (!input) return;
  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  btn.innerHTML = show ? '<i class="bi bi-eye-slash"></i> Skrýt' : '<i class="bi bi-eye"></i> Zobrazit';
});

// indikátor síly hesla
document.getElementById('password')?.addEventListener('input', function () {
  const val = this.value;
  const strengthText = document.getElementById('passwordStrength');
  let s = 0;
  if (val.length >= 8) s++;
  if (/[A-Z]/.test(val)) s++;
  if (/[0-9]/.test(val)) s++;
  if (/[^A-Za-z0-9]/.test(val)) s++;

  if (!val.length) { strengthText.textContent = ''; strengthText.className = 'form-text mt-1'; }
  else if (s <= 1) { strengthText.textContent = 'Slabé heslo'; strengthText.className = 'form-text mt-1 text-danger'; }
  else if (s <= 3) { strengthText.textContent = 'Středně silné heslo'; strengthText.className = 'form-text mt-1 text-warning'; }
  else { strengthText.textContent = 'Silné heslo'; strengthText.className = 'form-text mt-1 text-success'; }
});
</script>

<?php include "../includes/footer.php"; ?>
