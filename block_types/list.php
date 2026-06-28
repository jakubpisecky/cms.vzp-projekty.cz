<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('settings');

$result = $conn->query("
    SELECT *
    FROM block_types
    ORDER BY sort_order ASC, id ASC
");

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="admin-page-header-content mb-4">
        <div>
            <h2 class="mb-1">
                Správa bloků
            </h2>
            <p class="text-muted mb-0">Správa kategorií článků</p>
        </div>

        <div class="responsive-actions">
            <a href="add.php" class="btn btn-success">
                <i class="bi bi-plus-circle me-1"></i> Přidat blok
            </a>
        </div>
    </div>

    <?php if (isset($_GET['created'])): ?>
        <div class="alert alert-success">Blok byl vytvořen.</div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success">Blok byl upraven.</div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">Blok byl odstraněn.</div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover align-middle mb-0 bg-white">
                <thead class="table-light">
                    <tr>
                        <th width="70" class="text-center">ID</th>
                        <th>Název</th>
                        <th width="180">Typ</th>
                        <th width="80" class="text-center">Ikona</th>
                        <th width="100" class="text-center">Pořadí</th>
                        <th width="100" class="text-center">Stav</th>
                        <th width="120" class="text-center">Akce</th>
                    </tr>
                </thead>

                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="text-center"><?= (int)$row['id'] ?></td>

                            <td>
                                <div class="fw-semibold"><?= e($row['name']) ?></div>

                                <?php if (!empty($row['description'])): ?>
                                    <div class="text-muted small"><?= e($row['description']) ?></div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <code><?= e($row['type']) ?></code>
                            </td>

                            <td class="text-center">
                                <?php if (!empty($row['icon'])): ?>
                                    <i class="<?= e($row['icon']) ?>"></i>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-center">
                                <?= (int)$row['sort_order'] ?>
                            </td>

                            <td class="text-center">
                                <?php if ((int)$row['is_active'] === 1): ?>
                                    <span class="badge bg-success">aktivní</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">skrytý</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-center text-nowrap">
                                <a href="edit.php?id=<?= (int)$row['id'] ?>"
                                   class="btn btn-sm btn-primary"
                                   title="Upravit">
                                    <i class="bi bi-pencil"></i>
                                </a>

                                <a href="delete.php?id=<?= (int)$row['id'] ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Opravdu smazat tento typ bloku?');"
                                   title="Smazat">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                    <?php if ($result->num_rows === 0): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted p-4">
                                Zatím nejsou vytvořené žádné typy bloků.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php include "../includes/footer.php"; ?>