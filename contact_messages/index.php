<?php
// admin/contact_messages/index.php

require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

// uprav si na vhodné oprávnění, pokud chceš:
requirePermission('contact_messages');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// jednoduchý CSRF token (stejný pattern jako máš v nastavení)
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

$messages = [];

// filtrování: vše / nepřečtené / přečtené
$status = $_GET['status'] ?? 'all';
$allowedStatus = ['all','unread','read'];
if (!in_array($status, $allowedStatus, true)) {
    $status = 'all';
}

// jednoduché stránkování – stránka z GET, 25 na stránku
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 25;
$offset   = ($page - 1) * $perPage;

// spočítáme celkový počet
$where = '1';
$params = [];
$types  = '';

if ($status === 'unread') {
    $where = 'is_read = 0';
} elseif ($status === 'read') {
    $where = 'is_read = 1';
}

// total count
$sqlCount = "SELECT COUNT(*) AS cnt FROM contact_messages WHERE $where";
$resCount = $conn->query($sqlCount);
$total = 0;
if ($resCount && $row = $resCount->fetch_assoc()) {
    $total = (int)$row['cnt'];
}

// samotná data
$sql = "SELECT id, created_at, name, email, subject, page_slug, is_read, email_sent
        FROM contact_messages
        WHERE $where
        ORDER BY created_at DESC
        LIMIT ?, ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $offset, $perPage);
$stmt->execute();
$result = $stmt->get_result();
$rows = [];
while ($r = $result->fetch_assoc()) {
    $rows[] = $r;
}
$stmt->close();

// celkový počet stránek
$pagesCount = max(1, (int)ceil($total / $perPage));

include "../includes/header.php";
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Přijaté zprávy z formuláře</h2>
        <span class="text-muted small">
            Celkem: <?= (int)$total ?>
        </span>
    </div>

    <ul class="nav nav-pills mb-3">
        <li class="nav-item">
            <a class="nav-link <?= $status === 'all' ? 'active' : '' ?>" href="?status=all">Vše</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $status === 'unread' ? 'active' : '' ?>" href="?status=unread">Nepřečtené</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $status === 'read' ? 'active' : '' ?>" href="?status=read">Přečtené</a>
        </li>
    </ul>

    <?php if (isset($_SESSION['flash_contact_msg'])): ?>
        <div class="alert alert-<?= e($_SESSION['flash_contact_msg']['type']) ?>">
            <?= e($_SESSION['flash_contact_msg']['text']) ?>
        </div>
        <?php unset($_SESSION['flash_contact_msg']); ?>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <?php if (!$rows): ?>
                <p class="p-3 mb-0 text-muted">Zatím nebyla přijata žádná zpráva.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px;">&nbsp;</th>
                                <th>Datum</th>
                                <th>Jméno</th>
                                <th>E-mail</th>
                                <th>Předmět</th>
                                <th>Stránka</th>
                                <th>E-mail</th>
                                <th class="text-end" style="width: 80px;">Akce</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $msg): ?>
                                <?php
                                $isRead   = (int)$msg['is_read'] === 1;
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
                                    <td><?= htmlspecialchars(date('j.n.Y H:i', strtotime($msg['created_at']))) ?></td>
                                    <td><?= htmlspecialchars($msg['name']) ?></td>
                                    <td>
                                        <a href="mailto:<?= htmlspecialchars($msg['email']) ?>">
                                            <?= htmlspecialchars($msg['email']) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($msg['subject']) ?></td>
                                    <td><?= htmlspecialchars($msg['page_slug'] ?: '') ?></td>
                                    <td>
                                        <?php if ($emailSent): ?>
                                            <span class="badge bg-success">odeslán</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">chyba</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="view.php?id=<?= (int)$msg['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            Detail
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($pagesCount > 1): ?>
        <nav class="mt-3">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link"
                       href="?status=<?= urlencode($status) ?>&page=<?= max(1, $page - 1) ?>">«</a>
                </li>
                <?php for ($p = 1; $p <= $pagesCount; $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link"
                           href="?status=<?= urlencode($status) ?>&page=<?= $p ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $pagesCount ? 'disabled' : '' ?>">
                    <a class="page-link"
                       href="?status=<?= urlencode($status) ?>&page=<?= min($pagesCount, $page + 1) ?>">»</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php include "../includes/footer.php"; ?>
