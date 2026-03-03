<?php
require_once "includes/auth.php";
require_once "includes/db.php";
require_once "includes/functions.php";
require_once "includes/permissions.php";
include "includes/header.php";

$counts = [];

if (hasPermission('pages')) {
    $counts['pages'] = $conn->query("SELECT COUNT(*) AS c FROM pages")->fetch_assoc()['c'];
}
if (hasPermission('articles')) {
    $counts['articles'] = $conn->query("SELECT COUNT(*) AS c FROM articles")->fetch_assoc()['c'];
}
if (hasPermission('categories')) {
    $counts['categories'] = $conn->query("SELECT COUNT(*) AS c FROM categories")->fetch_assoc()['c'];
}
if (hasPermission('documents')) {
    $counts['documents'] = $conn->query("SELECT COUNT(*) AS c FROM documents")->fetch_assoc()['c'];
}
if (hasPermission('banners')) {
    $counts['banners'] = $conn->query("SELECT COUNT(*) AS c FROM banners")->fetch_assoc()['c'];
}
if (hasPermission('pictures')) {
    $counts['pictures'] = $conn->query("SELECT COUNT(*) AS c FROM pictures")->fetch_assoc()['c'];
}
if (hasPermission('galleries')) {
    $counts['galleries'] = $conn->query("SELECT COUNT(*) AS c FROM galleries")->fetch_assoc()['c'];
}
if (hasPermission('visits')) {
    $counts['visits'] = $conn->query("SELECT COUNT(*) AS c FROM visits")->fetch_assoc()['c'];
}
if (hasPermission('contact_messages')) {
    $counts['contact_messages'] = $conn->query("SELECT COUNT(*) AS c FROM contact_messages")->fetch_assoc()['c'];
}
if (hasPermission('menus')) {
    $counts['menus'] = $conn->query("SELECT COUNT(*) AS c FROM menu_days")->fetch_assoc()['c'];
}
if (hasPermission('settings')) {
    $counts['settings'] = $conn->query("SELECT COUNT(*) AS c FROM settings")->fetch_assoc()['c'];
}
if (hasPermission('users')) {
    $counts['users'] = $conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'];
    $counts['roles'] = $conn->query("SELECT COUNT(*) AS c FROM roles")->fetch_assoc()['c'];
}

// Poslední logy jen pokud má oprávnění
$logs = hasPermission('logs')
    ? $conn->query("
        SELECT l.*, u.email
        FROM logs l
        LEFT JOIN users u ON l.admin_id = u.id
        ORDER BY l.id DESC
        LIMIT 10
    ")
    : false;
?>

<div class="container py-4">
    <h1 class="mb-4">Dashboard</h1>

    <!-- Dlaždice s odkazy -->
    <div class="row g-3 mb-5">

        <?php if (hasPermission('pages')): ?>
        <div class="col-md-2 col-6">
            <a href="pages/list.php" class="text-decoration-none text-dark">
                <div class="card text-center shadow-sm h-100 dashboard-card">
                    <div class="card-body">
                        <div class="mb-2 fs-3 text-secondary"><i class="bi bi-layers"></i></div>
                        <div class="fw-bold">Stránky</div>
                        <div class="fs-4"><?= $counts['pages'] ?></div>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if (hasPermission('articles')): ?>
        <div class="col-md-2 col-6">
            <a href="articles/list.php" class="text-decoration-none text-dark">
                <div class="card text-center shadow-sm h-100 dashboard-card">
                    <div class="card-body">
                        <div class="mb-2 fs-3 text-secondary"><i class="bi bi-file-text"></i></div>
                        <div class="fw-bold">Články</div>
                        <div class="fs-4"><?= $counts['articles'] ?></div>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if (hasPermission('pictures')): ?>
        <div class="col-md-2 col-6">
            <a href="pictures/list.php" class="text-decoration-none text-dark">
                <div class="card text-center shadow-sm h-100 dashboard-card">
                    <div class="card-body">
                        <div class="mb-2 fs-3 text-secondary"><i class="bi bi-image"></i></div>
                        <div class="fw-bold">Obrázky</div>
                        <div class="fs-4"><?= $counts['pictures'] ?></div>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if (hasPermission('documents')): ?>
        <div class="col-md-2 col-6">
            <a href="documents/list.php" class="text-decoration-none text-dark">
                <div class="card text-center shadow-sm h-100 dashboard-card">
                    <div class="card-body">
                        <div class="mb-2 fs-3 text-secondary"><i class="bi bi-file-earmark-arrow-down"></i></div>
                        <div class="fw-bold">Dokumenty</div>
                        <div class="fs-4"><?= $counts['documents'] ?></div>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>
        
        <?php if (hasPermission('banners')): ?>
        <div class="col-md-2 col-6">
            <a href="banners/list.php" class="text-decoration-none text-dark">
            <div class="card text-center shadow-sm h-100 dashboard-card">
                <div class="card-body">
                <div class="mb-2 fs-3 text-secondary"><i class="bi bi-image-alt"></i></div>
                <div class="fw-bold">Bannery</div>
                <div class="fs-4"><?= (int)($counts['banners'] ?? 0) ?></div>
                </div>
            </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if (hasPermission('contact_messages')): ?>
        <div class="col-md-2 col-6">
            <a href="contact_messages/index.php" class="text-decoration-none text-dark">
                <div class="card text-center shadow-sm h-100 dashboard-card">
                    <div class="card-body">
                        <div class="mb-2 fs-3 text-secondary"><i class="bi bi-envelope"></i></div>
                        <div class="fw-bold">Přijaté zprávy</div>
                        <div class="fs-4"><?= $counts['contact_messages'] ?></div>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if (hasPermission('visits')): ?>
        <div class="col-md-2 col-6">
            <a href="visits/list.php" class="text-decoration-none text-dark">
                <div class="card text-center shadow-sm h-100 dashboard-card">
                    <div class="card-body">
                        <div class="mb-2 fs-3 text-secondary"><i class="bi bi-graph-up"></i></div>
                        <div class="fw-bold">Návštěvy</div>
                        <div class="fs-4"><?= $counts['visits'] ?></div>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if (hasPermission('menus')): ?>
        <div class="col-md-2 col-6">
            <a href="menus/list.php" class="text-decoration-none text-dark">
                <div class="card text-center shadow-sm h-100 dashboard-card">
                    <div class="card-body">
                        <div class="mb-2 fs-3 text-secondary"><i class="bi bi-card-checklist"></i></div>
                        <div class="fw-bold">Jídelníčky</div>
                        <div class="fs-4"><?= $counts['menus'] ?></div>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if (hasPermission('galleries')): ?>
        <div class="col-md-2 col-6">
            <a href="galleries/list.php" class="text-decoration-none text-dark">
                <div class="card text-center shadow-sm h-100 dashboard-card">
                    <div class="card-body">
                        <div class="mb-2 fs-3 text-secondary"><i class="bi bi-collection"></i></div>
                        <div class="fw-bold">Fotogalerie</div>
                        <div class="fs-4"><?= $counts['galleries'] ?></div>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if (hasPermission('categories')): ?>
        <div class="col-md-2 col-6">
            <a href="categories/list.php" class="text-decoration-none text-dark">
                <div class="card text-center shadow-sm h-100 dashboard-card">
                    <div class="card-body">
                        <div class="mb-2 fs-3 text-secondary"><i class="bi bi-tags"></i></div>
                        <div class="fw-bold">Kategorie</div>
                        <div class="fs-4"><?= $counts['categories'] ?></div>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>


        <?php if (hasPermission('settings')): ?>
        <div class="col-md-2 col-6">
            <a href="settings/index.php" class="text-decoration-none text-dark">
                <div class="card text-center shadow-sm h-100 dashboard-card">
                    <div class="card-body">
                        <div class="mb-2 fs-3 text-secondary"><i class="bi bi-gear"></i></div>
                        <div class="fw-bold">Nastavení webu</div>
                        <div class="fs-4"><?= $counts['settings'] ?></div>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if (hasPermission('users')): ?>
        <div class="col-md-2 col-6">
            <a href="users/list.php" class="text-decoration-none text-dark">
                <div class="card text-center shadow-sm h-100 dashboard-card">
                    <div class="card-body">
                        <div class="mb-2 fs-3 text-secondary"><i class="bi bi-people"></i></div>
                        <div class="fw-bold">Uživatelé</div>
                        <div class="fs-4"><?= $counts['users'] ?></div>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if (hasPermission('users')): ?>
        <div class="col-md-2 col-6">
            <a href="roles/list.php" class="text-decoration-none text-dark">
                <div class="card text-center shadow-sm h-100 dashboard-card">
                    <div class="card-body">
                        <div class="mb-2 fs-3 text-secondary"><i class="bi bi-shield-lock"></i></div>
                        <div class="fw-bold">Role a nastavení</div>
                        <div class="fs-4"><?= $counts['roles'] ?></div>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

    </div>

    <!-- Poslední akce -->
    <?php if ($logs): ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Poslední akce</h3>
        <a href="logs/list.php" class="btn btn-sm btn-outline-secondary">Zobrazit všechny logy</a>
    </div>
    <table class="table table-striped table-bordered bg-white shadow-sm">
        <thead>
            <tr>
                <th width="60">ID</th>
                <th>Uživatel</th>
                <th>Akce</th>
                <th width="180">Čas</th>
            </tr>
        </thead>
        <tbody>
        <?php while($l = $logs->fetch_assoc()): ?>
            <tr>
                <td><?= $l['id'] ?></td>
                <td><?= htmlspecialchars($l['email'] ?? 'Systém') ?></td>
                <td><?= htmlspecialchars($l['action']) ?></td>
                <td><?= formatDateTimeCz($l['created_at']) ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php include "includes/footer.php"; ?>
