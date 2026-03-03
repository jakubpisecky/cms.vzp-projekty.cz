<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('users');
$id = intval($_GET['id'] ?? 0);

// Kontrola platného ID
if ($id <= 0) {
    header("Location: list.php?error=not_found");
    exit;
}

// Kontrola: nesmaže sám sebe
if ($id === ($_SESSION['admin_id'] ?? 0)) {
    header("Location: list.php?error=self_delete");
    exit;
}

// Najdeme uživatele
$stmt = $conn->prepare("SELECT email FROM users WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->bind_result($email);
$stmt->fetch();
$stmt->close();

if (!$email) {
    header("Location: list.php?error=not_found");
    exit;
}

// Smazání uživatele
$stmt = $conn->prepare("DELETE FROM users WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

// Logování
logAction("Smazán uživatel '$email'");

header("Location: list.php?deleted=1");
exit;
