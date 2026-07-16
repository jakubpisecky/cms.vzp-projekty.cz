<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('invoices');

include "../includes/header.php";

$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
$types = '';

if ($q !== '') {
    $where[] = "(name LIKE ? OR ico LIKE ? OR dic LIKE ? OR email LIKE ? OR city LIKE ?)";
    $like = "%" . $q . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sssss';
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

// COUNT
$sqlCount = "SELECT COUNT(*) AS total FROM invoice_customers $whereSql";
$stmt = $conn->prepare($sqlCount);

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$total = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

// DATA
$sql = "
    SELECT *
    FROM invoice_customers
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
            <h2 class="mb-1">Fakturace</h1>
            <p class="text-muted mb-0">Správa odběratelů</p>
        </div>
        <div class="responsive-actions">
            <a href="customer_add.php" class="btn btn-success">
                <i class="bi bi-plus-lg"></i> Přidat odběratele
            </a>
        </div>
    </div>

    <?php include "_menu.php"; ?>

    <?php if (isset($_GET['created'])): ?>
        <div class="alert alert-success">Odběratel byl vytvořen.</div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success">Odběratel byl upraven.</div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">Odběratel byl smazán.</div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-8">
                    <input type="text"
                           name="q"
                           class="form-control"
                           placeholder="Hledat podle názvu, IČO, DIČ, e-mailu nebo města..."
                           value="<?= htmlspecialchars($q) ?>">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-search"></i> Hledat
                    </button>
                    <a href="customers.php" class="btn btn-outline-secondary">
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>


        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover bg-white">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Název</th>
                        <th>IČO</th>
                        <th>DIČ</th>
                        <th>Město</th>
                        <th>E-mail</th>
                        <th>Telefon</th>
                        <th>Vytvořeno</th>
                        <th class="text-center">Akce</th>
                    </tr>
                </thead>
                <tbody>

                <?php if ($result->num_rows === 0): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted p-4">
                            Žádní odběratelé nebyli nalezeni.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= (int)$row['id'] ?></td>
                        <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                        <td><?= htmlspecialchars($row['ico'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['dic'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['city'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['email'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['phone'] ?? '') ?></td>
                        <td><?= formatDateTimeCz(htmlspecialchars($row['created_at'])) ?></td>
                        <td class="text-center">
                            <div class="d-flex flex-wrap">
                                <a href="customer_edit.php?id=<?= (int)$row['id'] ?>"
                                   class="btn btn-sm btn-outline-primary"
                                   title="Upravit">
                                    <i class="bi bi-pencil"></i>
                                </a>

                                <a href="customer_delete.php?id=<?= (int)$row['id'] ?>"
                                   class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('Opravdu smazat tohoto odběratele?');"
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


    <?php if ($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?q=<?= urlencode($q) ?>&page=<?= $i ?>">
                            <?= $i ?>
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