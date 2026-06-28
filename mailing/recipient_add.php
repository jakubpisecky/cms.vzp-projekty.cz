<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/mailing.php";

requirePermission('mailing');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email   = trim($_POST['email'] ?? '');
    $first   = trim($_POST['first_name'] ?? '');
    $last    = trim($_POST['last_name'] ?? '');
    $company = trim($_POST['company'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Neplatný e-mail.";
    } else {
        $token = generateUnsubscribeToken();

        $stmt = $conn->prepare("
            INSERT INTO mailing_recipients (email, first_name, last_name, company, unsubscribe_token)
            VALUES (?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            $error = "Chyba SQL: " . $conn->error;
        } else {
            $stmt->bind_param("sssss", $email, $first, $last, $company, $token);

            if ($stmt->execute()) {
                $stmt->close();

                if (function_exists('logAction')) {
                    logAction("Přidán mailing příjemce: {$email}");
                }

                header("Location: recipients.php?created=1");
                exit;
            } else {
                $error = "Nepodařilo se uložit příjemce: " . $stmt->error;
                $stmt->close();
            }
        }
    }
}

include "../includes/header.php";
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-12">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Přidat příjemce</h1>
                    <p class="text-muted mb-0">Nový kontakt do mailing databáze</p>
                </div>
                <div>
                    <a href="recipients.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Zpět
                    </a>
                </div>
            </div>

            <?php include "_menu.php"; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">E-mail *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Jméno</label>
                            <input type="text" name="first_name" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Příjmení</label>
                            <input type="text" name="last_name" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Firma</label>
                            <input type="text" name="company" class="form-control">
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="recipients.php" class="btn btn-outline-secondary">Zrušit</a>
                            <button class="btn btn-primary">
                                <i class="bi bi-save"></i> Uložit
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>