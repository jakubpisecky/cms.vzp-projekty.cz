<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('visits');
$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($name === '' || $email === '') {
        $msg = "Jméno i e-mail jsou povinné.";
    } else {
        $stmt = $conn->prepare("INSERT INTO visits (name, email, created_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("ss", $name, $email);
        $stmt->execute();

        header("Location: list.php");
        exit;
    }
}

include "../includes/header.php";
?>

<div class="container py-4">
    <h2 class="mb-4">Přidat návštěvu</h2>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <form method="post" class="bg-white p-4 rounded shadow-sm">
        <div class="row">
            <div class="col-md-12">
                <div class="mb-3">
                    <label class="form-label">Jméno návštěvníka</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">E-mail návštěvníka</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                    <div class="d-flex justify-content-between">
                        <button class="btn btn-success"><i class="bi bi-save me-1"></i> Uložit</button>
                                 <a href="list.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left me-1"></i> Zpět
                                </a>
                            </div>
                    </div>
            </div>
        </div>
    </form>
</div>

<?php include "../includes/footer.php"; ?>
