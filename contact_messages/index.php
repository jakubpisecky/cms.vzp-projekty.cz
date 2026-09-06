<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('contact_messages');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

$csrf = $_SESSION['csrf'];

$status = $_GET['status'] ?? 'all';
$allowedStatus = ['all', 'unread', 'read'];

if (!in_array($status, $allowedStatus, true)) {
    $status = 'all';
}

$q       = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

$where  = [];
$params = [];
$types  = '';

if ($status === 'unread') {
    $where[] = "cm.is_read = 0";
} elseif ($status === 'read') {
    $where[] = "cm.is_read = 1";
}

if ($q !== '') {
    $where[] = "(cm.name LIKE ? OR cm.email LIKE ? OR cm.subject LIKE ? OR cm.page_slug LIKE ? OR cm.message LIKE ?)";
    $like = "%{$q}%";

    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;

    $types .= "sssss";
}

$whereSql = $where ? " WHERE " . implode(" AND ", $where) : "";

// počet
$sqlCount = "
    SELECT COUNT(*) AS c
    FROM contact_messages cm
    {$whereSql}
";

$stmt = $conn->prepare($sqlCount);

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$total = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$pagesCount = max(1, (int)ceil($total / $perPage));

if ($page > $pagesCount) {
    $page = $pagesCount;
}

$offset = ($page - 1) * $perPage;

$paramsUrl = $_GET;
unset($paramsUrl['page']);
$base = http_build_query($paramsUrl);
$base = $base ? ('?' . $base . '&') : '?';

// data
$sql = "
    SELECT id, created_at, name, email, subject, page_slug, is_read, email_sent
    FROM contact_messages cm
    {$whereSql}
    ORDER BY cm.created_at DESC, cm.id DESC
    LIMIT ?, ?
";

$stmt = $conn->prepare($sql);

$typesData = $types . "ii";
$paramsData = $params;
$paramsData[] = $offset;
$paramsData[] = $perPage;

$stmt->bind_param($typesData, ...$paramsData);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">
                Přijaté zprávy <small class="text-muted">(<?= (int)$total ?>)</small>
            </h2>
            <p class="text-muted mb-0">Zprávy odeslané z kontaktních formulářů</p>
        </div>
    </div>

    <?php if (isset($_SESSION['flash_contact_msg'])): ?>
        <div class="alert alert-<?= e($_SESSION['flash_contact_msg']['type']) ?>">
            <?= e($_SESSION['flash_contact_msg']['text']) ?>
        </div>
        <?php unset($_SESSION['flash_contact_msg']); ?>
    <?php endif; ?>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">

            <div class="mb-3">
                <ul class="nav nav-pills">
                    <li class="nav-item">
                        <a class="nav-link <?= $status === 'all' ? 'active' : '' ?>"
                           href="?status=all<?= $q !== '' ? '&q=' . urlencode($q) : '' ?>">
                            Vše
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?= $status === 'unread' ? 'active' : '' ?>"
                           href="?status=unread<?= $q !== '' ? '&q=' . urlencode($q) : '' ?>">
                            Nepřečtené
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?= $status === 'read' ? 'active' : '' ?>"
                           href="?status=read<?= $q !== '' ? '&q=' . urlencode($q) : '' ?>">
                            Přečtené
                        </a>
                    </li>
                </ul>
            </div>

            <form method="get" class="row g-3">
                <input type="hidden" name="status" value="<?= e($status) ?>">

                <div class="col-lg-7">
                    <input type="text"
                           name="q"
                           class="form-control"
                           placeholder="Hledat podle jména, e-mailu, předmětu nebo stránky..."
                           value="<?= e($q) ?>">
                </div>

                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-1"></i> Hledat
                    </button>
                </div>

                <?php if ($q !== ''): ?>
                    <div class="col-auto">
                        <a href="index.php?status=<?= urlencode($status) ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-1"></i> Zrušit
                        </a>
                    </div>
                <?php endif; ?>
            </form>

        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover align-middle mb-0 bg-white">
                <thead class="table-light">
                    <tr>
                        <th width="60" class="text-center">Stav</th>
                        <th width="170">Datum</th>
                        <th>Jméno</th>
                        <th>E-mail</th>
                        <th>Předmět</th>
                        <th>Stránka</th>
                        <th width="120" class="text-center">E-mail</th>
                        <th width="100" class="text-center">Akce</th>
                    </tr>
                </thead>

                <tbody>
                    <?php while ($msg = $result->fetch_assoc()): ?>
                        <?php
                            $isRead = (int)$msg['is_read'] === 1;
                            $emailSent = (int)$msg['email_sent'] === 1;
                        ?>

                        <tr class="<?= $isRead ? '' : 'table-warning' ?>">
                            <td class="text-center">
                                <?php if ($isRead): ?>
                                    <i class="bi bi-envelope-open text-muted" title="Přečteno"></i>
                                <?php else: ?>
                                    <i class="bi bi-envelope-fill text-primary" title="Nepřečteno"></i>
                                <?php endif; ?>
                            </td>

                            <td class="text-nowrap">
                                <?= e(date('j.n.Y H:i', strtotime($msg['created_at']))) ?>
                            </td>

                            <td>
                                <div class="fw-semibold">
                                    <?= e($msg['name'] ?? '') ?>
                                </div>
                            </td>

                            <td>
                                <?php if (!empty($msg['email'])): ?>
                                    <a href="mailto:<?= e($msg['email']) ?>">
                                        <?= e($msg['email']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= !empty($msg['subject']) ? e($msg['subject']) : '<span class="text-muted">—</span>' ?>
                            </td>

                            <td>
                                <?= !empty($msg['page_slug']) ? e($msg['page_slug']) : '<span class="text-muted">—</span>' ?>
                            </td>

                            <td class="text-center">
                                <?php if ($emailSent): ?>
                                    <span class="badge bg-success">odeslán</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">chyba</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-center text-nowrap">
                                <a href="view.php?id=<?= (int)$msg['id'] ?>"
                                   class="btn btn-sm btn-primary"
                                   title="Detail zprávy">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="delete.php?id=<?= (int)$msg['id'] ?>"
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Opravdu smazat dokument?');"
                                       title="Smazat">
                                        <i class="bi bi-trash"></i>
                                    </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                    <?php if ($result->num_rows === 0): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted p-4">
                                Žádné zprávy nebyly nalezeny.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>

            </table>
        </div>
    </div>

    <?php if (function_exists('renderPagination')): ?>
        <?php renderPagination($pagesCount, $page, $base); ?>
    <?php elseif ($pagesCount > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for ($p = 1; $p <= $pagesCount; $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $base ?>page=<?= $p ?>">
                            <?= $p ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>

</div>

<?php include "../includes/footer.php"; ?>