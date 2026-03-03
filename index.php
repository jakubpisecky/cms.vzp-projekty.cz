<?php
session_start();
require_once "includes/db.php";
require_once "includes/functions.php";

//
// 1) Pokud už je aktivní session, pošli rovnou do dashboardu
//
if (!empty($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

//
// 2) Pokud není session, ale existuje remember-me cookie, zkus auto-login
//
if (!empty($_COOKIE['admin_remember'])) {
    // cookie může být URL-enkódovaná (":" => %3A)
    $raw  = $_COOKIE['admin_remember'];
    $val  = urldecode($raw);
    $parts = explode(':', $val, 2);
    if (count($parts) !== 2) {
        // případný fallback na "selector|token" do budoucna
        $parts = explode('|', $val, 2);
    }

    if (count($parts) === 2) {
        [$selector, $token] = $parts;

        // načti záznam pro selector
        $stmt = $conn->prepare("SELECT user_id, token_hash, expires_at FROM user_remember_tokens WHERE selector=? LIMIT 1");
        $stmt->bind_param("s", $selector);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($row = $res->fetch_assoc()) {
            $validTime = (strtotime($row['expires_at']) > time());
            $calcHash  = hash('sha256', $token);

            if ($validTime && hash_equals($row['token_hash'], $calcHash)) {
                // přihlas uživatele (natáhni email/roli pro session)
                $u = $conn->prepare("SELECT id, email, role FROM users WHERE id=? LIMIT 1");
                $u->bind_param("i", $row['user_id']);
                $u->execute();
                if ($usr = $u->get_result()->fetch_assoc()) {
                    $_SESSION['admin_id']    = (int)$usr['id'];
                    $_SESSION['admin_email'] = $usr['email'];
                    $_SESSION['role']        = strtolower($usr['role'] ?? 'user');

                    if (function_exists('logAction')) {
                        logAction("Uživatel {$usr['email']} byl automaticky přihlášen (remember-me) přes index.");
                    }

                    // rotace tokenu (bezpečnost)
                    $newToken = bin2hex(random_bytes(32));
                    $newHash  = hash('sha256', $newToken);
                    $newExp   = (new DateTime('+30 days'))->format('Y-m-d H:i:s');

                    $upd = $conn->prepare("UPDATE user_remember_tokens SET token_hash=?, expires_at=? WHERE selector=?");
                    $upd->bind_param("sss", $newHash, $newExp, $selector);
                    $upd->execute();

                    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) == 443);
                    setcookie('admin_remember', $selector . ':' . $newToken, [
                        'expires'  => time() + 60*60*24*30,
                        'path'     => '/',
                        'secure'   => $isHttps,
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]);

                    // hotovo → na dashboard
                    header("Location: dashboard.php");
                    exit;
                }
            } else {
                // neplatné/expir → cleanup
                $del = $conn->prepare("DELETE FROM user_remember_tokens WHERE selector=?");
                $del->bind_param("s", $selector);
                $del->execute();

                $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) == 443);
                setcookie('admin_remember', '', [
                    'expires'  => time() - 3600,
                    'path'     => '/',
                    'secure'   => $isHttps,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }
        } else {
            // neznámý selector → smaž cookie
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) == 443);
            setcookie('admin_remember', '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    } else {
        // rozbitý formát → smaž cookie
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) == 443);
        setcookie('admin_remember', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

//
// 3) Pokud jsme se sem dostali, uživatel není přihlášen → běžný login flow
//
$msg = "";
$msgType = "danger";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $pass     = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    // Najdi uživatele
    $stmt = $conn->prepare("SELECT id, email, password, role FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($pass, $row['password'])) {
            // Přihlášení do session
            $_SESSION['admin_id']    = (int)$row['id'];
            $_SESSION['admin_email'] = $row['email'];
            $_SESSION['role']        = $row['role'];

            if (function_exists('logAction')) {
                logAction("Uživatel {$row['email']} se přihlásil");
            }

            // Remember-me (30 dní)
            if ($remember) {
                $selector  = bin2hex(random_bytes(8));   // 16 hex
                $token     = bin2hex(random_bytes(32));  // 64 hex
                $tokenHash = hash('sha256', $token);
                $expiresAt = (new DateTime('+30 days'))->format('Y-m-d H:i:s');

                // metadata (bezpečná varianta s NULLIF pro IP)
                $ipStr = $_SERVER['REMOTE_ADDR'] ?? '';
                $uaStr = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

                $sql = "INSERT INTO user_remember_tokens
                        (user_id, selector, token_hash, expires_at, created_ip, created_ua)
                        VALUES (?,?,?,?,INET6_ATON(NULLIF(?, '')),?)";
                $ins = $conn->prepare($sql);
                $ins->bind_param("isssss", $row['id'], $selector, $tokenHash, $expiresAt, $ipStr, $uaStr);
                $ins->execute();

                $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) == 443);
                setcookie('admin_remember', $selector . ':' . $token, [
                    'expires'  => time() + 60*60*24*30,
                    'path'     => '/',
                    'secure'   => $isHttps,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }

            header("Location: dashboard.php");
            exit;
        } else {
            $msg = "Nesprávné heslo!";
        }
    } else {
        $msg = "Uživatel neexistuje!";
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Přihlášení</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body class="bg-light">

<div class="container d-flex align-items-center justify-content-center" style="min-height: 100vh;">
    <div class="col-12 col-sm-10 col-md-6 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h3 class="mb-4 text-center"><i class="bi bi-box-arrow-in-right"></i> Přihlášení</h3>

                <?php if (!empty($msg)): ?>
                    <div class="alert alert-<?= $msgType ?> text-center"><?= htmlspecialchars($msg) ?></div>
                <?php endif; ?>

                <form method="post" autocomplete="off">
                    <div class="mb-3">
                        <input type="email" name="email" class="form-control" placeholder="E-mail" required autofocus>
                    </div>
                    <div class="mb-3 position-relative">
                        <input type="password" name="password" id="password" class="form-control" placeholder="Heslo" required>
                        <button type="button" class="btn btn-outline-secondary btn-sm position-absolute top-50 end-0 translate-middle-y me-2" onclick="togglePassword()" tabindex="-1">
                            <i class="bi bi-eye" id="toggleIcon"></i>
                        </button>
                    </div>

                    <!-- Zapamatovat na 30 dní -->
                    <div class="form-check mb-3">
                      <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember">
                      <label class="form-check-label" for="remember">
                        Zapamatovat na 30 dní
                      </label>
                    </div>

                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-box-arrow-in-right"></i> Přihlásit se
                        </button>
                    </div>
                </form>

                <div class="text-center">
                    <a href="forgot.php" class="text-decoration-none">Zapomenuté heslo?</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword() {
    const input = document.getElementById("password");
    const icon = document.getElementById("toggleIcon");
    if (input.type === "password") {
        input.type = "text";
        icon.classList.remove("bi-eye");
        icon.classList.add("bi-eye-slash");
    } else {
        input.type = "password";
        icon.classList.remove("bi-eye-slash");
        icon.classList.add("bi-eye");
    }
}
</script>

</body>
</html>
