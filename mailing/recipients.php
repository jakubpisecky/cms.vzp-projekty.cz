<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('mailing');

include "../includes/header.php";

$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
$types = '';

if ($q !== '') {
    $where[] = "(email LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR company LIKE ?)";
    $like = "%" . $q . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'ssss';
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

// celkový počet
$sqlCount = "SELECT COUNT(*) AS total FROM mailing_recipients $whereSql";
$stmt = $conn->prepare($sqlCount);

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$total = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

// data
$sql = "
    SELECT *
    FROM mailing_recipients
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
            <p class="text-muted mb-0">Správa příjemců</p>
        </div>
        <div class="responsive-actions">
    <a href="recipient_import.php" class="btn btn-outline-primary">
        <i class="bi bi-upload me-1"></i> Import CSV
    </a>
    <a href="recipient_add.php" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Přidat příjemce
    </a>
</div>
    </div>

    <?php include "_menu.php"; ?>

    <?php if (isset($_GET['created'])): ?>
        <div class="alert alert-success">Příjemce byl vytvořen.</div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success">Příjemce byl upraven.</div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">Příjemce byl smazán.</div>
    <?php endif; ?>

    <?php if (isset($_GET['unsubscribed'])): ?>
    <div class="alert alert-success">Příjemce byl odhlášen z odběru.</div>
    <?php endif; ?>

    <?php if (isset($_GET['resubscribed'])): ?>
        <div class="alert alert-success">Příjemce byl znovu přihlášen k odběru.</div>
    <?php endif; ?>

    <?php if (isset($_GET['imported'])): ?>
        <div class="alert alert-success">Import byl dokončen.</div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-8">
                    <input type="text"
                           name="q"
                           class="form-control"
                           placeholder="Hledat podle e-mailu, jména nebo firmy..."
                           value="<?= htmlspecialchars($q) ?>">
                </div>

                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-search"></i> Hledat
                    </button>
                    <a href="recipients.php" class="btn btn-outline-secondary">
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
                        <th>Email</th>
                        <th>Jméno</th>
                        <th>Firma</th>
                        <th>Stav</th>
                        <th>Vytvořeno</th>
                        <th class="text-end">Akce</th>
                    </tr>
                </thead>
                <tbody>

                <?php if ($result->num_rows === 0): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted p-4">
                            Žádní příjemci nebyli nalezeni.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= (int)$row['id'] ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= htmlspecialchars(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></td>
                        <td><?= htmlspecialchars($row['company'] ?? '') ?></td>
                        <td>
                            <?php if (!empty($row['unsubscribed'])): ?>
                                <span class="badge bg-danger">Odhlášen</span>
                            <?php elseif (empty($row['active'])): ?>
                                <span class="badge bg-secondary">Neaktivní</span>
                            <?php else: ?>
                                <span class="badge bg-success">Aktivní</span>
                            <?php endif; ?>
                        </td>
                        <td><?= formatDateTimeCz(htmlspecialchars($row['created_at'])) ?></td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <?php if (!empty($row['unsubscribed'])): ?>
                                    <a href="recipient_resubscribe.php?id=<?= (int)$row['id'] ?>"
                                    class="btn btn-outline-success"
                                    onclick="return confirm('Opravdu znovu přihlásit tohoto příjemce k odběru?');"
                                    title="Znovu přihlásit k odběru">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="recipient_unsubscribe.php?id=<?= (int)$row['id'] ?>"
                                    class="btn btn-outline-warning"
                                    onclick="return confirm('Opravdu odhlásit tohoto příjemce z odběru?');"
                                    title="Odhlásit z odběru">
                                        <i class="bi bi-person-dash"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="recipient_edit.php?id=<?= (int)$row['id'] ?>"
                                class="btn btn-outline-primary"
                                title="Upravit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="recipient_delete.php?id=<?= (int)$row['id'] ?>"
                                class="btn btn-outline-danger"
                                onclick="return confirm('Opravdu smazat tohoto příjemce?');"
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