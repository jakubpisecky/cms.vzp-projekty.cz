<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/settings_helpers.php";
require_once "../includes/mailing.php";

requirePermission('mailing');

include "../includes/header.php";

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    echo '<div class="alert alert-danger">Neplatné ID kampaně.</div>';
    include "../includes/footer.php";
    exit;
}

// načti kampaň
$stmt = $conn->prepare("SELECT * FROM mailing_campaigns WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$campaign = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$campaign) {
    echo '<div class="alert alert-danger">Kampaň nenalezena.</div>';
    include "../includes/footer.php";
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Neplatný e-mail.";
    } else {

        // fake příjemce pro test
        $recipient = [
            'email' => $email,
            'first_name' => 'Test',
            'last_name' => 'Uživatel',
            'company' => 'Test s.r.o.'
        ];

        $content = mailingReplaceVariables($campaign['content'], $recipient);

        $res = sendMailingMessage([
            'to_email' => $email,
            'to_name'  => 'Test',
            'subject'  => '[TEST] ' . $campaign['subject'],
            'html'     => $content
        ]);

        if ($res['success']) {
            $message = "Testovací e-mail byl odeslán.";
        } else {
            $error = "Chyba: " . $res['error'];
        }
    }
}
?>

<div class="container-fluid">
<div class="row">
<div class="col-lg-8 offset-lg-2">

<h2 class="mb-4">Testovací odeslání</h2>

<?php include "_menu.php"; ?>

<div class="card shadow-sm">
<div class="card-body">

<p><strong>Kampaň:</strong> <?= htmlspecialchars($campaign['title']) ?></p>

<?php if ($message): ?>
<div class="alert alert-success"><?= $message ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="post">

<div class="mb-3">
<label class="form-label">Testovací e-mail</label>
<input type="email" name="email" class="form-control" required>
</div>

<div class="d-flex justify-content-between">
<a href="list.php" class="btn btn-secondary">Zpět</a>
<button class="btn btn-primary">
<i class="bi bi-send"></i> Odeslat test
</button>
</div>

</form>

</div>
</div>

</div>
</div>
</div>

<?php include "../includes/footer.php"; ?>