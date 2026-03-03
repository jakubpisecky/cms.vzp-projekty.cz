<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/permissions.php"; // kvůli invalidatePermissionCache()
requirePermission('users');

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

$roleId = (int)($_GET['id'] ?? 0);
$token  = $_GET['csrf'] ?? '';

if ($roleId <= 0 || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
    header("Location: list.php?error=invalid");
    exit;
}

$stmt = $conn->prepare("SELECT name FROM roles WHERE id = ?");
$stmt->bind_param("i", $roleId);
$stmt->execute();
$role = $stmt->get_result()->fetch_assoc();
if (!$role) {
    header("Location: list.php?error=notfound");
    exit;
}

// systémové role mazat nechceme
if (in_array($role['name'], ['admin'])) {
    header("Location: list.php?error=admin");
    exit;
}

// je přiřazena uživatelům?
$chk = $conn->prepare("SELECT COUNT(*) FROM user_role WHERE role_id = ?");
$chk->bind_param("i", $roleId);
$chk->execute();
list($cnt) = $chk->get_result()->fetch_row();
if ($cnt > 0) {
    header("Location: list.php?error=assigned");
    exit;
}

$conn->begin_transaction();
try {
    $delRp = $conn->prepare("DELETE FROM role_permission WHERE role_id = ?");
    $delRp->bind_param("i", $roleId);
    $delRp->execute();

    $delRole = $conn->prepare("DELETE FROM roles WHERE id = ?");
    $delRole->bind_param("i", $roleId);
    $delRole->execute();

    $conn->commit();
    logAction("Smazána role '{$role['name']}' (ID {$roleId})");

    // zneplatni cache a vrať se na list
    invalidatePermissionCache();
    header("Location: list.php?deleted=1");
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    header("Location: list.php?error=server");
    exit;
}
