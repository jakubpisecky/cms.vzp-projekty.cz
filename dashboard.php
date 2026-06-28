<?php
require_once "includes/auth.php";
require_once "includes/db.php";
require_once "includes/functions.php";
require_once "includes/permissions.php";
require_once "includes/modules.php";

include "includes/header.php";

$dashboardModules = getDashboardAdminModules();

function getModuleCount(mysqli $conn, array $module): int
{
    if (empty($module['count_query'])) {
        return 0;
    }

    try {
        $res = $conn->query($module['count_query']);

        if ($res && ($row = $res->fetch_row())) {
            return (int)$row[0];
        }
    } catch (Throwable $e) {
        return 0;
    }

    return 0;
}

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

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h1 class="mb-1">Dashboard</h1>
            <p class="text-muted mb-0">Přehled modulů administrace</p>
        </div>
    </div>

    <div class="row g-3 mb-5">

        <?php foreach ($dashboardModules as $module): ?>
            <?php
                $count = getModuleCount($conn, $module);
                $url = ltrim((string)($module['url'] ?? '#'), '/');
            ?>

            <div class="col-xl-2 col-lg-3 col-md-4 col-6">
                <a href="<?= e($url) ?>" class="text-decoration-none text-dark">
                    <div class="card text-center shadow-sm h-100 dashboard-card border-0">
                        <div class="card-body">
                            <div class="mb-2 fs-3 text-secondary">
                                <i class="<?= e($module['icon'] ?? 'bi bi-folder') ?>"></i>
                            </div>

                            <div class="fw-bold">
                                <?= e($module['title'] ?? '') ?>
                            </div>

                            <div class="fs-4">
                                <?= (int)$count ?>
                            </div>

                            <?php if (!empty($module['description'])): ?>
                                <div class="small text-muted mt-1">
                                    <?= e($module['description']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>

        <?php if (empty($dashboardModules)): ?>
            <div class="col-12">
                <div class="alert alert-info mb-0">
                    Nemáte dostupné žádné moduly pro dashboard.
                </div>
            </div>
        <?php endif; ?>

    </div>

    <?php if ($logs): ?>
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h3 class="mb-0">Poslední akce</h3>

            <a href="logs/list.php" class="btn btn-sm btn-outline-secondary">
                Zobrazit všechny logy
            </a>
        </div>

        <div class="card shadow-sm border-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover align-middle mb-0 bg-white">
                    <thead class="table-light">
                        <tr>
                            <th width="70" class="text-center">ID</th>
                            <th width="240">Uživatel</th>
                            <th>Akce</th>
                            <th width="180">Čas</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php while ($l = $logs->fetch_assoc()): ?>
                            <tr>
                                <td class="text-center"><?= (int)$l['id'] ?></td>
                                <td><?= e($l['email'] ?? 'Systém') ?></td>
                                <td><?= e($l['action'] ?? '') ?></td>
                                <td class="text-nowrap"><?= e(formatDateTimeCz($l['created_at'])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php include "includes/footer.php"; ?>