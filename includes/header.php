<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/functions.php";
require_once __DIR__ . "/modules.php";

$user = getCurrentUser();

$current = $_SERVER['REQUEST_URI'] ?? '';

$adminModules = getVisibleAdminModules();
$moduleGroups = groupAdminModules($adminModules);

function isActive($needle) {
    global $current;
    return strpos($current, $needle) !== false ? 'active' : '';
}

function isModuleActive(array $module): string
{
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $moduleUrl   = parse_url($module['url'] ?? '', PHP_URL_PATH) ?: '';

    $moduleDir = '/' . trim(dirname($moduleUrl), '/') . '/';

    return strpos($currentPath, $moduleDir) === 0 ? 'active' : '';
}

function renderAdminNavigation(array $moduleGroups): void
{
    foreach ($moduleGroups as $groupName => $modules): ?>
        <li class="nav-item sidebar-group-title">
            <strong><?= e($groupName) ?></strong>
        </li>

        <?php foreach ($modules as $module): ?>
            <li class="nav-item">
                <a class="nav-link <?= isModuleActive($module) ?>"
                   href="<?= e($module['url']) ?>">
                    <i class="<?= e($module['icon']) ?> me-2"></i>
                    <?= e($module['title']) ?>
                </a>
            </li>
        <?php endforeach; ?>

    <?php endforeach;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <title>Administrace</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/dropzone@5.9.3/dist/min/dropzone.min.css">
  <link rel="stylesheet" href="/css/admin.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">

  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/htmlmixed/htmlmixed.min.js"></script>

  <script>
  document.addEventListener('DOMContentLoaded', function () {
      const generateBtn = document.getElementById('ai-generate-btn');
      const editBtn = document.getElementById('ai-edit-btn');
      const promptEl = document.getElementById('ai_prompt');
      const statusEl = document.getElementById('ai-status');

      async function callTemplateAI(mode) {
          if (!promptEl || !statusEl || !window.templateEditor) return;

          const prompt = promptEl.value.trim();

          if (!prompt) {
              statusEl.textContent = 'Zadej nejdřív požadavek pro AI.';
              return;
          }

          statusEl.textContent = 'Generuji návrh...';

          const currentHtml = window.templateEditor.getValue();

          try {
              const response = await fetch('ajax_template_ai.php', {
                  method: 'POST',
                  headers: {
                      'Content-Type': 'application/json'
                  },
                  body: JSON.stringify({
                      mode: mode,
                      prompt: prompt,
                      content: currentHtml
                  })
              });

              const data = await response.json();

              if (!data.success) {
                  statusEl.textContent = data.error || 'Nepodařilo se vygenerovat HTML.';
                  return;
              }

              if (mode === 'edit' || confirm('Nahradit obsah editoru novým HTML?')) {
                  window.templateEditor.setValue(data.html || '');
              }

              statusEl.textContent = 'Hotovo.';

          } catch (err) {
              console.error(err);
              statusEl.textContent = 'Došlo k chybě při komunikaci s AI.';
          }
      }

      if (generateBtn) {
          generateBtn.addEventListener('click', function () {
              callTemplateAI('generate');
          });
      }

      if (editBtn) {
          editBtn.addEventListener('click', function () {
              callTemplateAI('edit');
          });
      }
  });
  </script>
</head>

<body class="bg-light">

<nav class="navbar navbar-light bg-white border-bottom sticky-top topbar">
  <div class="container-fluid px-0">

    <button class="btn btn-outline-secondary d-lg-none mx-3"
            data-bs-toggle="offcanvas"
            data-bs-target="#offcanvasNav"
            aria-label="Menu">
      <i class="bi bi-list"></i>
    </button>

    <a class="navbar-brand fw-semibold mx-1 mx-lg-4" href="/dashboard.php">
      <i class="bi bi-gear-fill me-1"></i>
      Administrace
    </a>

    <ul class="navbar-nav ms-auto flex-row align-items-center">

      <li class="nav-item me-3">
        <a class="nav-link d-flex align-items-center" href="https://web.vzp-projekty.cz" target="_blank">
          <i class="bi bi-globe me-1"></i>
          <span class="d-none d-lg-inline">Zobrazit web</span>
        </a>
      </li>

      <li class="nav-item dropdown me-3">
        <a class="nav-link dropdown-toggle d-flex align-items-center"
           href="#"
           role="button"
           data-bs-toggle="dropdown"
           data-bs-display="static"
           aria-expanded="false">
          <i class="bi bi-person-circle me-1"></i>
          <span class="d-none d-lg-inline">
            <?= e($_SESSION['admin_email'] ?? 'Nepřihlášen') ?>
          </span>
        </a>

        <ul class="dropdown-menu dropdown-menu-end">
          <li>
            <a class="dropdown-item" href="/users/change-password.php">
              Změna hesla
            </a>
          </li>
          <li><hr class="dropdown-divider"></li>
          <li>
            <a class="dropdown-item text-danger" href="/logout.php">
              Odhlásit
            </a>
          </li>
        </ul>
      </li>

    </ul>

  </div>
</nav>

<div class="admin-wrap d-flex">

  <aside class="sidebar d-none d-lg-block p-3">
    <ul class="nav nav-pills flex-column nav-aside gap-1">
      <?php renderAdminNavigation($moduleGroups); ?>
    </ul>
  </aside>

  <div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasNav" aria-labelledby="offcanvasNavLabel">
    <div class="offcanvas-header">
      <h5 class="offcanvas-title" id="offcanvasNavLabel">Menu</h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Zavřít"></button>
    </div>

    <div class="offcanvas-body">
      <ul class="nav nav-pills flex-column nav-aside gap-1">
        <?php renderAdminNavigation($moduleGroups); ?>
      </ul>
    </div>
  </div>

  <main class="content p-3 p-lg-4">