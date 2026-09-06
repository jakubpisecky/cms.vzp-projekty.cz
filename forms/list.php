<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('forms');

$res = $conn->query("
    SELECT 
        f.*,
        (
            SELECT COUNT(*) 
            FROM form_fields ff 
            WHERE ff.form_id = f.id
        ) AS fields_count,
        (
            SELECT COUNT(*) 
            FROM form_submissions fs 
            WHERE fs.form_id = f.id
        ) AS submissions_count
    FROM forms f
    ORDER BY f.sort_order ASC, f.id DESC
");

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="admin-page-header-content mb-4">
        <div>
            <h2 class="mb-1">Formuláře</h2>
            <p class="text-muted mb-0">Správa formulářů a přijatých zpráv</p>
        </div>

        <a href="add.php" class="btn btn-success">
            <i class="bi bi-plus-lg me-1"></i> Přidat formulář
        </a>
    </div>

    <?php if (isset($_GET['created'])): ?>
        <div class="alert alert-success">Formulář byl vytvořen.</div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success">Formulář byl upraven.</div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">Formulář byl smazán.</div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        

            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover align-middle mb-0 bg-white">
                    <thead>
                        <tr>
                            <th>Název</th>
                            <th>Slug</th>
                            <th class="text-center">Pole</th>
                            <th class="text-center">Zprávy</th>
                            <th class="text-center">Stav</th>
                            <th class="text-center">Akce</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($res && $res->num_rows > 0): ?>
                            <?php while ($row = $res->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($row['name']) ?></strong>

                                        <?php if (!empty($row['description'])): ?>
                                            <div class="text-muted small">
                                                <?= e($row['description']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <code><?= e($row['slug']) ?></code>
                                    </td>

                                    <td class="text-center">
                                        <span class="badge bg-light text-dark">
                                            <?= (int)$row['fields_count'] ?>
                                        </span>
                                    </td>

                                    <td class="text-center">
                                        <span class="badge bg-primary">
                                            <?= (int)$row['submissions_count'] ?>
                                        </span>
                                    </td>

                                    <td class="text-center">
                                        <?php if ((int)$row['is_active'] === 1): ?>
                                            <span class="badge bg-success">Aktivní</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Neaktivní</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-center">
                                        <a href="fields.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            Pole
                                        </a>

                                        <a href="submissions.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-outline-info">
                                            Zprávy
                                        </a>

                                        <a href="edit.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-primary">
                                           <i class="bi bi-pencil"></i>
                                        </a>

                                        <a href="delete.php?id=<?= (int)$row['id'] ?>"
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Opravdu chcete formulář smazat?');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    Zatím nejsou vytvořené žádné formuláře.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            

        </div>
    </div>

</div>

<?php include "../includes/footer.php"; ?>