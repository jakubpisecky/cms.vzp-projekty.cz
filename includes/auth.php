<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/functions.php"; // kvůli logAction apod.

// Pomocná funkce: přihlásí uživatele do session dle ID
function adminLoginUserById(int $userId, mysqli $conn): bool {
    $stmt = $conn->prepare("SELECT id, email, role FROM users WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    if (!$u) return false;

    $_SESSION['admin_id']    = (int)$u['id'];
    $_SESSION['admin_email'] = $u['email'];
    $_SESSION['role']        = strtolower($u['role'] ?? 'user');

    if (function_exists('logAction')) {
        logAction("Uživatel {$u['email']} byl automaticky přihlášen (remember-me).");
    }
    return true;
}

// ===== Auto-login z remember-me cookie (pokud není aktivní session) =====
if (empty($_SESSION['admin_id']) && !empty($_COOKIE['admin_remember'])) {
    // Cookie může mít dvojtečku URL-enkódovanou (%3A) -> nejdřív dekódovat
    $raw  = $_COOKIE['admin_remember'];
    $val  = urldecode($raw);
    $parts = explode(':', $val, 2); // historický formát "selector:token"
    if (count($parts) !== 2) {
        // fallback – kdybys někdy přešel na "selector|token"
        $parts = explode('|', $val, 2);
    }

    if (count($parts) === 2) {
        [$selector, $token] = $parts;

        // Načti záznam pro selector
        $stmt = $conn->prepare("SELECT user_id, token_hash, expires_at FROM user_remember_tokens WHERE selector=? LIMIT 1");
        $stmt->bind_param("s", $selector);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($row = $res->fetch_assoc()) {
            $isNotExpired = (strtotime($row['expires_at']) > time());
            $calcHash     = hash('sha256', $token);

            if ($isNotExpired && hash_equals($row['token_hash'], $calcHash)) {
                // Přihlas uživatele
                if (adminLoginUserById((int)$row['user_id'], $conn)) {
                    // ROTACE tokenu (doporučeno)
                    $newToken = bin2hex(random_bytes(32));
                    $newHash  = hash('sha256', $newToken);
                    $newExp   = (new DateTime('+30 days'))->format('Y-m-d H:i:s');

                    $upd = $conn->prepare("UPDATE user_remember_tokens SET token_hash=?, expires_at=? WHERE selector=?");
                    $upd->bind_param("sss", $newHash, $newExp, $selector);
                    $upd->execute();

                    // přepiš cookie
                    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) == 443);
                    setcookie('admin_remember', $selector . ':' . $newToken, [
                        'expires'  => time() + 60*60*24*30,
                        'path'     => '/',
                        'secure'   => $isHttps,  // jen pokud je HTTPS
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]);
                }
            } else {
                // Neplatný/expir. token → smaž v DB i cookie (prevence zneužití)
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
        // rozbitá hodnota cookie → pryč
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

// ===== Vynucení přihlášení (po případném auto-loginu) =====
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit;
}

// ===== Legacy: jednorázově načti roli, pokud chybí =====
if (!isset($_SESSION['role'])) {
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['admin_id']);
    $stmt->execute();
    $stmt->bind_result($role);
    $stmt->fetch();
    $stmt->close();

    $_SESSION['role'] = strtolower($role ?? 'user');
}

// ===== Načtení DB-oprávnění do session cache =====
require_once __DIR__ . "/permissions.php";
loadUserPermissionsToSession((int)$_SESSION['admin_id'], $conn);
