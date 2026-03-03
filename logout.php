<?php
session_start();
require_once __DIR__ . '/includes/db.php';

// Zruš remember-me token (pokud je cookie)
if (!empty($_COOKIE['admin_remember'])) {
    // Cookie může být URL-enkódovaná → nejdřív dekódovat
    $val = urldecode($_COOKIE['admin_remember']);
    $parts = explode(':', $val, 2);
    if (count($parts) !== 2) {
        $parts = explode('|', $val, 2); // případný nový formát
    }

    if (count($parts) === 2) {
        [$selector, $token] = $parts;
        // smaž záznam v DB podle selectoru
        $stmt = $conn->prepare("DELETE FROM user_remember_tokens WHERE selector=?");
        $stmt->bind_param("s", $selector);
        $stmt->execute();
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) == 443);
    // smaž cookie
    setcookie('admin_remember', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// zruš session
$_SESSION = [];
session_destroy();

// redirect na login
header("Location: index.php");
exit;
