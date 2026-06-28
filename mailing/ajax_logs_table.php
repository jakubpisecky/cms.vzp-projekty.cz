<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('mailing');

$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
$types = '';

if ($q !== '') {
    $where[] = "(email LIKE ? OR message LIKE ?)";
    $like = "%" . $q . "%";
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

if ($status !== '' && in_array($status, ['info', 'success', 'error'], true)) {
    $where[] = "status = ?";
    $params[] = $status;
    $types .= 's';
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

$sql = "
    SELECT *
    FROM mailing_logs
    $whereSql
    ORDER BY id DESC
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
        <td colspan="5" class="text-center text-muted p-4">
            Žádné logy nebyly nalezeny.
        </td>
    </tr>
<?php endif; ?>

<?php while ($row = $result->fetch_assoc()): ?>
    <tr>
        <td><?= (int)$row['id'] ?></td>
        <td><?= htmlspecialchars($row['email'] ?? '') ?></td>
        <td>
            <?php if ($row['status'] === 'success'): ?>
                <span class="badge bg-success">OK</span>
            <?php elseif ($row['status'] === 'error'): ?>
                <span class="badge bg-danger">Chyba</span>
            <?php else: ?>
                <span class="badge bg-secondary">Info</span>
            <?php endif; ?>
        </td>
        <td><?= htmlspecialchars($row['message']) ?></td>
        <td><?= formatDateTimeCz(htmlspecialchars($row['created_at'])) ?></td>
    </tr>
<?php endwhile; ?>

<?php
$html = ob_get_clean();

header('Content-Type: application/json');
echo json_encode([
    'html' => $html
]);