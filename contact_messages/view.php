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

// zpracování POST akcí (smazání)
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
    SELECT id, created_at, page_id, page_slug, name, email, phone, subject, message, ip_address, user_agent, is_read, email_sent
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

// označíme jako přečtené, pokud ještě není
if ((int)$message['is_read'] === 0) {
    $stmt = $conn->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $message['is_read'] = 1;
}

include "../includes/header.php";
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Detail zprávy #<?= (int)$message['id'] ?></h2>
        <div>
            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                ← Zpět na přehled
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-3 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?= htmlspecialchars($message['subject']) ?></strong><br>
                        <small class="text-muted">
                            <?= htmlspecialchars(date('j.n.Y H:i', strtotime($message['created_at']))) ?>
                        </small>
                    </div>
                    <div>
                        <?php if ((int)$message['email_sent'] === 1): ?>
                            <span class="badge bg-success">E-mail odeslán</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Chyba e-mailu</span>
                        <?php endif; ?>
                        <?php if ((int)$message['is_read'] === 1): ?>
                            <span class="badge bg-secondary ms-1">Přečteno</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark ms-1">Nepřečteno</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Jméno</dt>
                        <dd class="col-sm-9"><?= htmlspecialchars($message['name']) ?></dd>

                        <dt class="col-sm-3">E-mail</dt>
                        <dd class="col-sm-9">
                            <a href="mailto:<?= htmlspecialchars($message['email']) ?>">
                                <?= htmlspecialchars($message['email']) ?>
                            </a>
                        </dd>

                        <?php if (!empty($message['phone'])): ?>
                            <dt class="col-sm-3">Telefon</dt>
                            <dd class="col-sm-9">
                                <a href="tel:<?= htmlspecialchars($message['phone']) ?>">
                                    <?= htmlspecialchars($message['phone']) ?>
                                </a>
                            </dd>
                        <?php endif; ?>

                        <?php if (!empty($message['page_slug'])): ?>
                            <dt class="col-sm-3">Stránka</dt>
                            <dd class="col-sm-9">
                                <a href="/<?= htmlspecialchars($message['page_slug']) ?>" target="_blank">
                                    /<?= htmlspecialchars($message['page_slug']) ?>
                                </a>
                            </dd>
                        <?php endif; ?>
                    </dl>

                    <hr>

                    <h5>Zpráva</h5>
                    <p class="mb-0" style="white-space: pre-line;">
                        <?= htmlspecialchars($message['message']) ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3 shadow-sm">
                <div class="card-header">
                    Technické informace
                </div>
                <div class="card-body small">
                    <p class="mb-1">
                        <strong>IP adresa:</strong><br>
                        <?= htmlspecialchars($message['ip_address'] ?: '-') ?>
                    </p>
                    <p class="mb-1">
                        <strong>User-Agent:</strong><br>
                        <span class="text-break"><?= htmlspecialchars($message['user_agent'] ?: '-') ?></span>
                    </p>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header">
                    Akce
                </div>
                <div class="card-body">
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
    </div>
</div>

<?php include "../includes/footer.php"; ?>
