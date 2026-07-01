<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('forms');

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: list.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT 
        fs.*,
        f.id AS form_id,
        f.name AS form_name
    FROM form_submissions fs
    INNER JOIN forms f ON f.id = fs.form_id
    WHERE fs.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$submission = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$submission) {
    header("Location: list.php");
    exit;
}

$stmt = $conn->prepare("
    UPDATE form_submissions
    SET is_read = 1
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare("
    SELECT *
    FROM form_submission_values
    WHERE submission_id = ?
    ORDER BY id ASC
");
$stmt->bind_param("i", $id);
$stmt->execute();
$values = $stmt->get_result();
$stmt->close();

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="admin-page-header-content mb-4">
        <div>
            <h2 class="mb-1">Detail zprávy</h2>
            <p class="text-muted mb-0"><?= e($submission['form_name']) ?></p>
        </div>

        <a href="submissions.php?id=<?= (int)$submission['form_id'] ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Zpět na zprávy
        </a>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div><strong>Datum:</strong> <?= e(date('j. n. Y H:i', strtotime($submission['created_at']))) ?></div>
            <div><strong>IP:</strong> <?= e($submission['ip_address'] ?? '') ?></div>
            <div><strong>Referer:</strong> <?= e($submission['referer'] ?? '') ?></div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
     

            <?php if ($values->num_rows === 0): ?>
                <div class="text-muted">Zpráva neobsahuje žádné hodnoty.</div>
            <?php else: ?>
                <table class="table table-bordered table-striped table-hover align-middle mb-0 bg-white">
                    <tbody>
                        <?php while ($v = $values->fetch_assoc()): ?>
                            <tr>
                                <th style="width:260px;">
                                    <?= e($v['field_label'] ?: $v['field_name']) ?>
                                </th>
                                <td>
                                    <?= nl2br(e($v['field_value'] ?? '')) ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
    </div>

</div>

<?php include "../includes/footer.php"; ?>