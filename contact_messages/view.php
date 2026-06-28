<?php
// admin/contact_messages/view.php

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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: index.php");
    exit;
}

// zpracování POST akcí
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        $_SESSION['flash_contact_msg'] = [
            'type' => 'danger',
            'text' => 'Neplatný CSRF token.'
        ];

        header("Location: index.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM contact_messages WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['flash_contact_msg'] = [
            'type' => 'success',
            'text' => 'Zpráva byla odstraněna.'
        ];

        header("Location: index.php");
        exit;
    }
}

// načtení zprávy
$stmt = $conn->prepare("
    SELECT 
        id, created_at, page_id, page_slug, name, email, phone, subject, message,
        ip_address, user_agent, is_read, email_sent, replied_at, replied_by
    FROM contact_messages
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$message = $res->fetch_assoc();
$stmt->close();

if (!$message) {
    $_SESSION['flash_contact_msg'] = [
        'type' => 'danger',
        'text' => 'Zpráva nebyla nalezena.'
    ];

    header("Location: index.php");
    exit;
}

// označit jako přečtené
if ((int)$message['is_read'] === 0) {
    $stmt = $conn->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $message['is_read'] = 1;
}

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="admin-page-header-content mb-4">
        <div>
            <h2 class="mb-1">
                Detail zprávy <small class="text-muted">#<?= (int)$message['id'] ?></small>
            </h2>
            <p class="text-muted mb-0">Zpráva odeslaná z kontaktního formuláře</p>
        </div>

        <div class="responsive-actions">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Zpět
            </a>
        </div>
    </div>

    <div class="row g-4">

        <div class="col-lg-8">

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="mb-1">
                                <?= !empty($message['subject']) ? e($message['subject']) : 'Bez předmětu' ?>
                            </h5>

                            <div class="text-muted small">
                                <?= e(date('j.n.Y H:i', strtotime($message['created_at']))) ?>
                            </div>
                        </div>

                        <div class="text-nowrap">
                            <?php if ((int)$message['email_sent'] === 1): ?>
                                <span class="badge bg-success">odeslán</span>
                            <?php else: ?>
                                <span class="badge bg-danger">chyba</span>
                            <?php endif; ?>

                            <?php if ((int)$message['is_read'] === 1): ?>
                                <span class="badge bg-secondary ms-1">přečteno</span>
                            <?php else: ?>
                                <span class="badge bg-primary ms-1">nepřečteno</span>
                            <?php endif; ?>
                            <?php if (!empty($message['replied_at'])): ?>
                                <span class="badge bg-info ms-1">odpovězeno</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="table-responsive mb-4">
                        <table class="table table-bordered align-middle mb-0 bg-white">
                            <tbody>
                                <tr>
                                    <th width="180" class="table-light">Jméno</th>
                                    <td>
                                        <strong><?= e($message['name'] ?? '') ?></strong>
                                    </td>
                                </tr>

                                <tr>
                                    <th class="table-light">E-mail</th>
                                    <td>
                                        <?php if (!empty($message['email'])): ?>
                                            <a href="mailto:<?= e($message['email']) ?>">
                                                <?= e($message['email']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <tr>
                                    <th class="table-light">Telefon</th>
                                    <td>
                                        <?php if (!empty($message['phone'])): ?>
                                            <a href="tel:<?= e($message['phone']) ?>">
                                                <?= e($message['phone']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <tr>
                                    <th class="table-light">Stránka</th>
                                    <td>
                                        <?php if (!empty($message['page_slug'])): ?>
                                            <a href="/<?= e($message['page_slug']) ?>" target="_blank">
                                                <?= e($message['page_slug']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h5 class="mb-3">Zpráva</h5>

                    <div class="border rounded bg-light p-3" style="white-space: pre-line;">
                        <?= e($message['message'] ?? '') ?>
                    </div>

                </div>
            </div>

        </div>

        <div class="col-lg-4">

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">

                    <h5 class="mb-3">Akce</h5>

                    <div class="d-grid gap-2">

                        <?php if (!empty($message['email'])): ?>
                            <a href="reply.php?id=<?= (int)$message['id'] ?>" class="btn btn-primary">
                                <i class="bi bi-reply me-1"></i> Odpovědět z administrace
                            </a>
                        <?php endif; ?>

                        <form method="post" onsubmit="return confirm('Opravdu smazat tuto zprávu?');">
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="action" value="delete">

                            <button type="submit" class="btn btn-danger w-100">
                                <i class="bi bi-trash me-1"></i> Smazat zprávu
                            </button>
                        </form>

                    </div>

                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">

                    <h5 class="mb-3">Technické informace</h5>

                    <table class="table table-bordered align-middle mb-0 bg-white">
                        <tbody>
                            <tr>
                                <th width="120" class="table-light">IP adresa</th>
                                <td>
                                    <?= !empty($message['ip_address']) ? e($message['ip_address']) : '<span class="text-muted">—</span>' ?>
                                </td>
                            </tr>

                            <tr>
                                <th class="table-light">User-Agent</th>
                                <td class="small text-break">
                                    <?= !empty($message['user_agent']) ? e($message['user_agent']) : '<span class="text-muted">—</span>' ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                </div>
            </div>

        </div>

    </div>

</div>

<?php include "../includes/footer.php"; ?>