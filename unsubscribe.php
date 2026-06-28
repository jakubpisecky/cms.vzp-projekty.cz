<?php
require_once __DIR__ . "/includes/db.php";

$token = trim($_GET['token'] ?? '');
$message = '';
$type = 'danger';

if ($token === '') {
    $message = 'Chybí odhlašovací token.';
} else {
    $stmt = $conn->prepare("
        SELECT id, email, unsubscribed
        FROM mailing_recipients
        WHERE unsubscribe_token = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $recipient = $result->fetch_assoc();
    $stmt->close();

    if (!$recipient) {
        $message = 'Odkaz pro odhlášení je neplatný nebo již neexistuje.';
    } else {
        if ((int)$recipient['unsubscribed'] === 1) {
            $message = 'Tento e-mail je již z odběru odhlášen.';
            $type = 'info';
        } else {
            $stmt = $conn->prepare("
                UPDATE mailing_recipients
                SET unsubscribed = 1
                WHERE id = ?
            ");
            $stmt->bind_param("i", $recipient['id']);
            $stmt->execute();
            $stmt->close();

            $message = 'E-mail ' . htmlspecialchars($recipient['email']) . ' byl úspěšně odhlášen z odběru.';
            $type = 'success';
        }
    }
}
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Odhlášení z odběru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row">
            <div class="col-lg-6 offset-lg-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <h1 class="h3 mb-3">Odhlášení z odběru</h1>

                        <?php if ($type === 'success'): ?>
                            <div class="alert alert-success mb-0"><?= $message ?></div>
                        <?php elseif ($type === 'info'): ?>
                            <div class="alert alert-info mb-0"><?= $message ?></div>
                        <?php else: ?>
                            <div class="alert alert-danger mb-0"><?= $message ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>