<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/functions.php";

$user = getCurrentUser();

$current = $_SERVER['REQUEST_URI'] ?? '';
function isActive($needle) {
  global $current;
  return strpos($current, $needle) !== false ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <title>Administrace</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- CSS: Bootstrap, Select2, Icons, tvoje admin CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <!-- + Dropzone CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/dropzone@5.9.3/dist/min/dropzone.min.css">
  <link rel="stylesheet" href="/css/admin.css">
</head>
<body class="bg-light">

<!-- Horní lišta (světlá), s user dropdownem vpravo -->
<nav class="navbar navbar-light bg-white border-bottom sticky-top topbar">
  <div class="container-fluid px-0">
    <button class="btn btn-outline-secondary d-lg-none mx-3" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNav" aria-label="Menu">
      <i class="bi bi-list"></i>
    </button>

    <!-- menší odsazení na mobilu, větší od LG -->
    <a class="navbar-brand fw-semibold mx-1 mx-lg-4" href="/dashboard.php"><i class="bi bi-gear-fill me-1"></i> Administrace</a>

    <ul class="navbar-nav ms-auto flex-row align-items-center">

  <!-- Zobrazit web -->
  <li class="nav-item me-3">
    <a class="nav-link d-flex align-items-center" href="https://web.vzp-projekty.cz" target="_blank">
      <i class="bi bi-globe me-1"></i> <span class="d-none d-lg-inline">Zobrazit web</span>
    </a>
  </li>

  <!-- User dropdown -->
  <li class="nav-item dropdown me-3">
    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
      <i class="bi bi-person-circle me-1"></i>
      <span class="d-none d-lg-inline">
        <?= htmlspecialchars($_SESSION['admin_email'] ?? 'Nepřihlášen') ?>
      </span>
    </a>
    <ul class="dropdown-menu dropdown-menu-end">
      <li><a class="dropdown-item" href="/users/change-password.php">Změna hesla</a></li>
      <li><hr class="dropdown-divider"></li>
      <li><a class="dropdown-item text-danger" href="/logout.php">Odhlásit</a></li>
    </ul>
  </li>
</ul>

  </div>
</nav>
<div class="admin-wrap d-flex">

  <!-- Sidebar pro ≥ lg -->
  <aside class="sidebar d-none d-lg-block p-3">
    <ul class="nav nav-pills flex-column nav-aside gap-1">
      <?php if (hasPermission('pages')): ?>
        <li class="nav-item"><a class="nav-link <?= isActive('/pages/') ?>" href="/pages/list.php"><i class="bi bi-layers me-2"></i>Stránky</a></li>
      <?php endif; ?>
      <?php if (hasPermission('articles')): ?>
        <li class="nav-item"><a class="nav-link <?= isActive('/articles/') ?>" href="/articles/list.php"><i class="bi bi-file-text me-2"></i>Články</a></li>
      <?php endif; ?>
      <?php if (hasPermission('pictures')): ?>
        <li class="nav-item"><a class="nav-link <?= isActive('/pictures/') ?>" href="/pictures/list.php"><i class="bi bi-image me-2"></i>Obrázky</a></li>
      <?php endif; ?>
      <?php if (hasPermission('banners')): ?>
        <li class="nav-item"><a class="nav-link <?= isActive('/banners/') ?>" href="/banners/list.php"><i class="bi bi-image-alt me-2"></i>Bannery</a></li>
      <?php endif; ?>
      <?php if (hasPermission('documents')): ?>
        <li class="nav-item"><a class="nav-link <?= isActive('/documents/') ?>" href="/documents/list.php"><i class="bi bi-file-earmark-arrow-down me-2"></i>Dokumenty</a></li>
      <?php endif; ?>
      <?php if (hasPermission('contact_messages')): ?>
        <li class="nav-item"><a class="nav-link <?= isActive('/contact_messages/') ?>" href="/contact_messages/index.php"><i class="bi bi-envelope me-2"></i>Přijaté zprávy</a></li>
      <?php endif; ?>
      <?php if (hasPermission('visits')): ?>
        <li class="nav-item"><a class="nav-link <?= isActive('/visits/') ?>" href="/visits/list.php"><i class="bi bi-graph-up me-2"></i>Návštěvy</a></li>
      <?php endif; ?>
      <?php if (hasPermission('menus')): ?>
      <li class="nav-item"><a class="nav-link <?= isActive('/menus/') ?>" href="/menus/list.php">
          <i class="bi bi-card-checklist me-2"></i>Jídelníčky</a></li>
      <?php endif; ?>
      <?php if (hasPermission('galleries')): ?>
        <li class="nav-item"><a class="nav-link <?= isActive('/galleries/') ?>" href="/galleries/list.php"><i class="bi bi-collection me-2"></i>Fotogalerie</a></li>
      <?php endif; ?>
      <?php if (hasPermission('categories')): ?>
        <li class="nav-item"><a class="nav-link <?= isActive('/categories/') ?>" href="/categories/list.php"><i class="bi bi-tags me-2"></i>Kategorie</a></li>
      <?php endif; ?>
      <?php if (hasPermission('settings')): ?>
        <li class="nav-item"><a class="nav-link <?= isActive('/settings/') ?>" href="/settings/index.php"><i class="bi bi-gear me-2"></i>Nastavení webu</a></li>
      <?php endif; ?>
      <?php if (hasPermission('users')): ?>
        <li class="nav-item"><a class="nav-link <?= isActive('/users/') ?>" href="/users/list.php"><i class="bi bi-people me-2"></i>Uživatelé</a></li>
        <li class="nav-item"><a class="nav-link <?= isActive('/roles/') ?>" href="/roles/list.php"><i class="bi bi-shield-lock me-2"></i>Role a oprávnění</a></li>
      <?php endif; ?>
      <?php if (hasPermission('logs')): ?>
        <li class="nav-item"><a class="nav-link <?= isActive('/logs/') ?>" href="/logs/list.php"><i class="bi bi-journal-text me-2"></i>Logování</a></li>
      <?php endif; ?>
    </ul>
  </aside>

  <!-- Offcanvas menu pro mobily -->
  <div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasNav" aria-labelledby="offcanvasNavLabel">
    <div class="offcanvas-header">
      <h5 class="offcanvas-title" id="offcanvasNavLabel">Menu</h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Zavřít"></button>
    </div>
    <div class="offcanvas-body">
      <ul class="nav nav-pills flex-column nav-aside gap-1">
        <?php if (hasPermission('pages')): ?>
          <li class="nav-item"><a class="nav-link <?= isActive('/pages/') ?>" href="/pages/list.php"><i class="bi bi-layers me-2"></i>Stránky</a></li>
        <?php endif; ?>
        <?php if (hasPermission('articles')): ?>
          <li class="nav-item"><a class="nav-link <?= isActive('/articles/') ?>" href="/articles/list.php"><i class="bi bi-file-text me-2"></i>Články</a></li>
        <?php endif; ?>
        <?php if (hasPermission('pictures')): ?>
          <li class="nav-item"><a class="nav-link <?= isActive('/pictures/') ?>" href="/pictures/list.php"><i class="bi bi-image me-2"></i>Obrázky</a></li>
        <?php endif; ?>
        <?php if (hasPermission('banners')): ?>
          <li class="nav-item"><a class="nav-link <?= isActive('/banners/') ?>" href="/banners/list.php"><i class="bi bi-image-alt me-2"></i>Bannery</a></li>
        <?php endif; ?>
        <?php if (hasPermission('documents')): ?>
          <li class="nav-item"><a class="nav-link <?= isActive('/documents/') ?>" href="/documents/list.php"><i class="bi bi-file-earmark-arrow-down me-2"></i>Dokumenty</a></li>
        <?php endif; ?>
        <?php if (hasPermission('contact_messages')): ?>
          <li class="nav-item"><a class="nav-link <?= isActive('/contact_messages/') ?>" href="/contact_messages/list.php"><i class="bi bi-graph-up me-2"></i>Přijaté zprávy</a></li>
        <?php endif; ?>
        <?php if (hasPermission('visits')): ?>
          <li class="nav-item"><a class="nav-link <?= isActive('/visits/') ?>" href="/visits/list.php"><i class="bi bi-graph-up me-2"></i>Návštěvy</a></li>
        <?php endif; ?>
        <?php if (hasPermission('galleries')): ?>
          <li class="nav-item"><a class="nav-link <?= isActive('/galleries/') ?>" href="/galleries/list.php"><i class="bi bi-collection me-2"></i>Fotogalerie</a></li>
        <?php endif; ?>
        <?php if (hasPermission('categories')): ?>
          <li class="nav-item"><a class="nav-link <?= isActive('/categories/') ?>" href="/categories/list.php"><i class="bi bi-tags me-2"></i>Kategorie</a></li>
        <?php endif; ?>
        <?php if (hasPermission('settings')): ?>
          <li class="nav-item"><a class="nav-link <?= isActive('/settings/') ?>" href="/settings/index.php"><i class="bi bi-gear me-2"></i>Nastavení webu</a></li>
        <?php endif; ?>
        <?php if (hasPermission('users')): ?>
          <li class="nav-item"><a class="nav-link <?= isActive('/users/') ?>" href="/users/list.php"><i class="bi bi-people me-2"></i>Uživatelé</a></li>
          <li class="nav-item"><a class="nav-link <?= isActive('/roles/') ?>" href="/roles/list.php"><i class="bi bi-shield-lock me-2"></i>Role a oprávnění</a></li>
        <?php endif; ?>
        <?php if (hasPermission('logs')): ?>
          <li class="nav-item"><a class="nav-link <?= isActive('/logs/') ?>" href="/logs/list.php"><i class="bi bi-journal-text me-2"></i>Logování</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>

  <!-- Začátek hlavního obsahu -->
  <main class="content p-3 p-lg-4">
