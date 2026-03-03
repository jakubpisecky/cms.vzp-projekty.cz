<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kontrola přihlášení
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit;
}

// Pokud role ještě není nastavena, načteme ji z DB
// (dělá se jen jednou po přihlášení nebo při prvním přístupu)
if (!isset($_SESSION['role'])) {
    require_once __DIR__ . "/db.php";

    $stmt = $conn->prepare("SELECT role FROM users WHERE id=?");
    $stmt->bind_param("i", $_SESSION['admin_id']);
    $stmt->execute();
    $stmt->bind_result($role);
    $stmt->fetch();
    $stmt->close();

    // Uložíme roli do session jako lowercase pro jednotnost
    $_SESSION['role'] = strtolower($role ?? 'user');
}
