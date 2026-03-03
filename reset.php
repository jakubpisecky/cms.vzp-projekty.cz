<?php
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/functions.php";

if (session_status() === PHP_SESSION_NONE) session_start();

$msg = "";
$msgType = "info";

// --- pomocné ---
function fail($text, $type = 'danger') {
    global $msg, $msgType;
    $msg = $text;
    $msgType = $type;
}

// --- rate-limit na pokusy s tímto tokenem (v session) ---
$token = $_GET['token'] ?? '';
$token = is_string($token) ? trim($token) : '';

if ($token !== '') {
    $_SESSION['reset_try'][$token] = ($_SESSION['reset_try'][$token] ?? 0);
    if ($_SESSION['reset_try'][$token] > 50) { // extrémní brzda
        http_response_code(429);
        fail("Příliš mnoho pokusů. Zkuste to prosím později.");
        $token = '';
    }
}

$userId = null;
$email  = null;

if ($token === '') {
    fail("Chybí token pro obnovu hesla.");
} else {
    // Najdeme platného uživatele s tokenem
    $stmt = $conn->prepare("SELECT id, email FROM users WHERE reset_token = ? AND reset_expires > NOW() LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->bind_result($userId, $email);
    $stmt->fetch();
    $stmt->close();

    if (!$userId) {
        fail("Neplatný nebo expirovaný odkaz pro obnovu hesla.");
        // token už nepoužijeme
        $token = '';
    }
}

// --- CSRF pro samotný formulář (oddělený od reset tokenu v URL) ---
if (!isset($_SESSION['form_csrf'])) {
    $_SESSION['form_csrf'] = bin2hex(random_bytes(16));
}
$formCsrf = $_SESSION['form_csrf'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userId) {
    // zvýšíme čítač pokusů
    $_SESSION['reset_try'][$token]++;

    $password  = trim($_POST['password']  ?? '');
    $password2 = trim($_POST['password2'] ?? '');
    $postedCsrf = $_POST['csrf'] ?? '';

    if (!hash_equals($formCsrf, $postedCsrf)) {
        fail("Neplatný požadavek (CSRF). Zkuste to znovu.");
    } elseif ($password === '' || $password2 === '') {
        fail("Vyplňte obě pole pro nové heslo.");
    } elseif ($password !== $password2) {
        fail("Hesla se neshodují.");
    } elseif (strlen($password) < 8) {
        fail("Heslo musí mít alespoň 8 znaků.", "warning");
    } else {
        // změna hesla
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        $stmt->bind_param("si", $hash, $userId);
        $stmt->execute();
        $ok = $stmt->affected_rows >= 0; // i když je stejné heslo, bereme jako OK
        $stmt->close();

        if ($ok) {
            logAction("Uživatel '$email' obnovil heslo pomocí reset linku");
            // vynulujeme CSRF i token v URL sessionově
            unset($_SESSION['form_csrf'], $_SESSION['reset_try'][$token]);
            $msg = "Heslo bylo úspěšně změněno. Nyní se můžete přihlásit.";
            $msgType = "success";
            $token = ''; // aby se formulář už nezobrazil
        } else {
            fail("Nastala chyba při ukládání hesla. Zkuste to prosím znovu.");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <title>Obnova hesla</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh;">

<div class="container d-flex justify-content-center">
  <div class="row w-100 justify-content-center">
    <div class="col-md-5">
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <h2 class="mb-4 text-center"><i class="bi bi-unlock"></i> Nastavení nového hesla</h2>

          <?php if ($msg): ?>
            <div class="alert alert-<?= htmlspecialchars($msgType) ?>"><?= htmlspecialchars($msg) ?></div>
          <?php endif; ?>

          <?php if ($token): ?>
            <form method="post" autocomplete="off" novalidate>
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($formCsrf) ?>">

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
                <a href="index.php" class="btn btn-outline-secondary">Zpět na přihlášení</a>
              </div>
            </form>
          <?php else: ?>
            <div class="text-center mt-3">
              <a href="index.php" class="btn btn-outline-primary">Přihlášení</a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Zobrazit/Skrýt + síla hesla -->
<script>
document.addEventListener('click', function (e) {
  const btn = e.target.closest('.toggle-password');
  if (!btn) return;
  const input = document.querySelector(btn.dataset.target);
  if (!input) return;

  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  btn.innerHTML = show ? '<i class="bi bi-eye-slash"></i> Skrýt' : '<i class="bi bi-eye"></i> Zobrazit';
});

// Indikátor síly hesla
document.getElementById('password')?.addEventListener('input', function () {
  const val = this.value;
  const strengthText = document.getElementById('passwordStrength');
  let strength = 0;

  if (val.length >= 8) strength++;
  if (/[A-Z]/.test(val)) strength++;
  if (/[0-9]/.test(val)) strength++;
  if (/[^A-Za-z0-9]/.test(val)) strength++;

  if (!val.length) {
    strengthText.textContent = '';
    strengthText.className = 'form-text mt-1';
  } else if (strength <= 1) {
    strengthText.textContent = 'Slabé heslo';
    strengthText.className = 'form-text mt-1 text-danger';
  } else if (strength === 2 || strength === 3) {
    strengthText.textContent = 'Středně silné heslo';
    strengthText.className = 'form-text mt-1 text-warning';
  } else {
    strengthText.textContent = 'Silné heslo';
    strengthText.className = 'form-text mt-1 text-success';
  }
});
</script>

</body>
</html>
