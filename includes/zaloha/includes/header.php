<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/functions.php";

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Administrace</title>
    <meta name="viewport" content="width=device-width, initial-scale=1"> <!-- DŮLEŽITÉ pro mobilní zobrazení -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/css/admin.css">
</head>
<body class="bg-light">

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid px-3">
        <a class="navbar-brand" href="/dashboard.php">Administrace</a>

        <!-- Hamburger menu -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Menu -->
        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if (hasPermission('pages')): ?>
                    <li class="nav-item"><a class="nav-link" href="/pages/list.php">Stránky</a></li>
                <?php endif; ?>
                <?php if (hasPermission('articles')): ?>
                    <li class="nav-item"><a class="nav-link" href="/articles/list.php">Články</a></li>
                <?php endif; ?>
                <?php if (hasPermission('pictures')): ?>
                    <li class="nav-item"><a class="nav-link" href="/pictures/list.php">Obrázky</a></li>
                <?php endif; ?>
                <?php if (hasPermission('documents')): ?>
                    <li class="nav-item"><a class="nav-link" href="/documents/list.php">Dokumenty</a></li>
                <?php endif; ?>
                <?php if (hasPermission('visits')): ?>
                    <li class="nav-item"><a class="nav-link" href="/visits/list.php">Návštěvy</a></li>
                <?php endif; ?>
                <?php if (hasPermission('galleries')): ?>
                    <li class="nav-item"><a class="nav-link" href="/galleries/list.php">Fotogalerie</a></li>
                <?php endif; ?>
                <?php if (hasPermission('categories')): ?>
                    <li class="nav-item"><a class="nav-link" href="/categories/list.php">Kategorie</a></li>
                <?php endif; ?>
                <?php if (hasPermission('settings')): ?>
                    <li class="nav-item"><a class="nav-link" href="/settings/index.php">Nastavení webu</a></li>
                <?php endif; ?><?php if (hasPermission('users')): ?>
                    <li class="nav-item"><a class="nav-link" href="/roles/list.php">Role & oprávnění</a></li>
                <?php endif; ?>
                <?php if (hasPermission('users')): ?>
                    <li class="nav-item"><a class="nav-link" href="/users/list.php">Uživatelé</a></li>
                <?php endif; ?>
                <?php if (hasPermission('logs')): ?>
                    <li class="nav-item"><a class="nav-link" href="/logs/list.php">Logování</a></li>
                <?php endif; ?>
            </ul>

            <!-- Pravá část: uživatel -->
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($_SESSION['admin_email'] ?? 'Nepřihlášen') ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="/users/change-password.php">Změna hesla</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="/logout.php">Odhlásit</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
