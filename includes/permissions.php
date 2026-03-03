<?php
// DB oprávnění s cache v session. Nezávislé na require db.php – $conn se předává jako argument.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Interní: načte seznam povolených modulů pro daného uživatele z DB.
 * Vrací asociativní pole ['articles' => true, ...]
 */
function _loadPermissionsFromDb(int $userId, mysqli $conn): array {
    $perms = [];

    // 1) Je uživatel admin? -> má všechno
    $sqlAdmin = "
        SELECT 1
        FROM user_role ur
        JOIN roles r ON r.id = ur.role_id
        WHERE ur.user_id = ? AND r.name = 'admin'
        LIMIT 1
    ";
    $stmt = $conn->prepare($sqlAdmin);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    if ($stmt->get_result()->fetch_row()) {
        $res = $conn->query("SELECT name FROM permissions");
        while ($row = $res->fetch_assoc()) {
            $perms[$row['name']] = true;
        }
        return $perms;
    }

    // 2) Jinak seber práva z rolí
    $sql = "
        SELECT DISTINCT p.name
        FROM permissions p
        JOIN role_permission rp ON p.id = rp.permission_id
        JOIN user_role ur ON rp.role_id = ur.role_id
        WHERE ur.user_id = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $perms[$row['name']] = true;
    }

    return $perms;
}

/**
 * Načti a ulož práva do session cache.
 * POZOR: nově přijímá i $conn.
 */
function loadUserPermissionsToSession(int $userId, mysqli $conn): void {
    if (!$userId) return;
    $_SESSION['perm_cache'] = _loadPermissionsFromDb($userId, $conn);
    $_SESSION['perm_cache_user'] = $userId;
}

/** Invalidate cache (volat po změně rolí/členů). */
function invalidatePermissionCache(?int $userId = null): void {
    if ($userId === null || $userId === ($_SESSION['perm_cache_user'] ?? null)) {
        unset($_SESSION['perm_cache'], $_SESSION['perm_cache_user']);
    }
}

/** Legacy: aktuální role ze session (kvůli starému UI). */
function getUserRole(): string {
    return $_SESSION['role'] ?? 'user';
}

/** Má aktuální uživatel oprávnění na modul? */
function hasPermission(string $section): bool {
    $userId = (int)($_SESSION['admin_id'] ?? 0);
    if (!$userId) return false;

    // Lazy-load cache, pokud chybí nebo patří jinému userovi
    if (!isset($_SESSION['perm_cache']) || ($_SESSION['perm_cache_user'] ?? 0) !== $userId) {
        // Nutné: mít k dispozici $conn; zavolá se z auth.php po include db.php
        // Pro jistotu zkusíme získat $conn z globálu, pokud existuje:
        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
            loadUserPermissionsToSession($userId, $GLOBALS['conn']);
        } else {
            // Bez $conn neumíme ověřit – bezpečně odmítneme
            return false;
        }
    }

    return !empty($_SESSION['perm_cache'][$section]);
}
