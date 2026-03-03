<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('visits');
include "../includes/header.php";

$result = $conn->query("
    SELECT v.*, a.email AS admin_email
    FROM visits v
    LEFT JOIN users a ON v.confirmed_by = a.id
    ORDER BY v.created_at DESC
");

$totalVisits = (int)$conn->query("SELECT COUNT(*) FROM documents")->fetch_row()[0];
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Návštěvy <small class="text-muted">(<?= $totalVisits ?>)</small></h2>
    <a href="add.php" class="btn btn-success">
        <i class="bi bi-plus-circle me-1"></i> Přidat návštěvu
    </a>
</div>


    <div class="table-responsive">
        <table class="table table-bordered table-striped table-hover bg-white">
            <thead class="table-light">
                <tr>
                    <th width="60">ID</th>
                    <th>Jméno</th>
                    <th>Email</th>
                    <th>Vytvořeno</th>
                    <th>Uhrazeno</th>
                    <th>Potvrzeno</th>
                    <th>Potvrdil</th>
                    <th width="260" class="text-center">Akce</th>
                </tr>
            </thead>
            <tbody>
                <?php while($visit = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $visit['id'] ?></td>
                        <td><?= htmlspecialchars($visit['name']) ?></td>
                        <td><?= htmlspecialchars($visit['email']) ?></td>
                        <td><?= formatDateCz($visit['created_at']) ?></td>
                        <td><?= $visit['paid'] ? 'Ano' : 'Ne' ?></td>
                        <td><?= $visit['confirmed_at'] ? formatDateCz($visit['confirmed_at']) : '-' ?></td>
                        <td><?= $visit['admin_email'] ?: '-' ?></td>
                        <td class="text-center text-nowrap">
                            <div class="d-flex justify-content-center gap-2 flex-wrap">
                                <a href="edit.php?id=<?= $visit['id'] ?>" class="btn btn-sm btn-primary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if (!$visit['paid']): ?>
                                    <a href="update.php?action=pay&id=<?= $visit['id'] ?>" class="btn btn-sm btn-success">
                                        <i class="bi bi-currency-dollar"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (!$visit['confirmed_at']): ?>
                                    <a href="update.php?action=confirm&id=<?= $visit['id'] ?>" class="btn btn-sm btn-warning">
                                        <i class="bi bi-check-circle"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="delete.php?id=<?= $visit['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Opravdu smazat návštěvu?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
