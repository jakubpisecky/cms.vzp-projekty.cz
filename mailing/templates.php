<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('mailing');

include "../includes/header.php";

$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
$types = '';

if ($q !== '') {
    $where[] = "(title LIKE ? OR content LIKE ?)";
    $like = "%" . $q . "%";
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

$sqlCount = "SELECT COUNT(*) AS total FROM mailing_templates $whereSql";
$stmt = $conn->prepare($sqlCount);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

$sql = "
    SELECT *
    FROM mailing_templates
    $whereSql
    ORDER BY created_at DESC, id DESC
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

$totalPages = max(1, (int)ceil($total / $perPage));
?>

<div class="container-fluid py-4">

    <div class="admin-page-header-content mb-4">
        <div>
            <h2 class="mb-1">Mailing</h1>
            <p class="text-muted mb-0">Šablony e-mailů</p>
        </div>
        <div class="responsive-actions">
            <a href="template_add.php" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> Nová šablona
            </a>
        </div>
    </div>

    <?php include "_menu.php"; ?>

    <?php if (isset($_GET['created'])): ?>
        <div class="alert alert-success">Šablona byla vytvořena.</div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success">Šablona byla upravena.</div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">Šablona byla smazána.</div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-8">
                    <input type="text"
                           name="q"
                           class="form-control"
                           placeholder="Hledat podle názvu nebo obsahu..."
                           value="<?php echo htmlspecialchars($q); ?>">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-search"></i> Hledat
                    </button>
                    <a href="templates.php" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

        <div class="card-body p-0">
            <?php if ($result->num_rows > 0): ?>
                <div class="table table-bordered table-striped table-hover bg-white">
                    <table class="table table-bordered table-striped table-hover bg-white">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Název</th>
                                <th>Vytvořeno</th>
                                <th class="text-center">Akce</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo (int)$row['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['title']); ?></strong></td>
                                    <td><?php echo formatDateTimeCz(htmlspecialchars($row['created_at'])); ?></td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="template_edit.php?id=<?php echo (int)$row['id']; ?>"
                                               class="btn btn-outline-primary"
                                               title="Upravit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="template_delete.php?id=<?php echo (int)$row['id']; ?>"
                                               class="btn btn-outline-danger"
                                               onclick="return confirm('Opravdu smazat tuto šablonu?');"
                                               title="Smazat">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-4">
                    <p class="text-muted mb-0">Žádné šablony nebyly nalezeny.</p>
                </div>
            <?php endif; ?>
        </div>

    <?php if ($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?q=<?php echo urlencode($q); ?>&page=<?php echo $i; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>

</div>

<?php
$stmt->close();
include "../includes/footer.php";
?>