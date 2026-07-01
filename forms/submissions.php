<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('forms');

$formId = intval($_GET['id'] ?? 0);

if ($formId <= 0) {
    header("Location: list.php");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM forms WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $formId);
$stmt->execute();
$form = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$form) {
    header("Location: list.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT *
    FROM form_submissions
    WHERE form_id = ?
    ORDER BY created_at DESC, id DESC
");
$stmt->bind_param("i", $formId);
$stmt->execute();
$submissions = $stmt->get_result();
$stmt->close();

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="admin-page-header-content mb-4">
        <div>
            <h2 class="mb-1">Přijaté zprávy</h2>
            <p class="text-muted mb-0"><?= e($form['name']) ?></p>
        </div>

        <a href="list.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Zpět na formuláře
        </a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">

            <?php if ($submissions->num_rows === 0): ?>
                <div class="text-center text-muted py-5">
                    Zatím nejsou žádné přijaté zprávy.
                </div>
            <?php else: ?>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Datum</th>
                                <th>IP adresa</th>
                                <th>Stav</th>
                                <th class="text-end">Akce</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $submissions->fetch_assoc()): ?>
                                <tr>
                                    <td><?= e(date('j. n. Y H:i', strtotime($row['created_at']))) ?></td>
                                    <td><?= e($row['ip_address'] ?? '') ?></td>
                                    <td>
                                        <?= (int)$row['is_read'] === 1
                                            ? '<span class="badge bg-secondary">Přečteno</span>'
                                            : '<span class="badge bg-success">Nové</span>' ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="submission_detail.php?id=<?= (int)$row['id'] ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            Detail
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>

        </div>
    </div>

</div>

<?php include "../includes/footer.php"; ?>