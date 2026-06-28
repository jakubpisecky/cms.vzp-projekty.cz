<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/mailing.php";

requirePermission('mailing');

$status = trim($_GET['status'] ?? '');
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
$types = '';

if ($status !== '' && in_array($status, ['pending', 'processing', 'sent', 'failed', 'skipped'], true)) {
    $where[] = "mq.status = ?";
    $params[] = $status;
    $types .= 's';
}

if ($q !== '') {
    $where[] = "(mq.email LIKE ? OR mc.title LIKE ?)";
    $like = "%" . $q . "%";
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

$sql = "
    SELECT mq.*, mc.title AS campaign_title
    FROM mailing_queue mq
    LEFT JOIN mailing_campaigns mc ON mc.id = mq.campaign_id
    $whereSql
    ORDER BY mq.id DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);

$bindParams = $params;
$bindTypes = $types . 'ii';
$bindParams[] = $perPage;
$bindParams[] = $offset;

$stmt->bind_param($bindTypes, ...$bindParams);
$stmt->execute();
$result = $stmt->get_result();

ob_start();
?>

<?php if ($result->num_rows === 0): ?>
    <tr>
        <td colspan="7" class="text-center text-muted p-4">
            Fronta je prázdná.
        </td>
    </tr>
<?php endif; ?>

<?php while ($row = $result->fetch_assoc()): ?>
    <tr>
        <td><?= (int)$row['id'] ?></td>
        <td><?= htmlspecialchars($row['campaign_title'] ?? '-') ?></td>
        <td><?= htmlspecialchars($row['email']) ?></td>
        <td><?= mailingQueueStatusBadge($row['status']) ?></td>
        <td><?= (int)$row['attempts'] ?></td>
        <td>
            <?php if (!empty($row['last_error'])): ?>
                <span class="text-danger small">
                    <?= htmlspecialchars(mb_strimwidth($row['last_error'], 0, 80, '...')) ?>
                </span>
            <?php else: ?>
                -
            <?php endif; ?>
        </td>
        <td><?= !empty($row['sent_at']) ? formatDateTimeCz(htmlspecialchars($row['sent_at'])) : '-' ?></td>
    </tr>
<?php endwhile; ?>

<?php
$html = ob_get_clean();

header('Content-Type: application/json');
echo json_encode([
    'html' => $html
]);