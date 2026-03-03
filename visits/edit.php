<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('visits');
$id = intval($_GET['id'] ?? 0);

// Načteme návštěvu
$stmt = $conn->prepare("SELECT * FROM visits WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$visit = $stmt->get_result()->fetch_assoc();

if (!$visit) {
    header("Location: list.php");
    exit;
}

$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $paid = isset($_POST['paid']) ? 1 : 0;
    $confirmed = isset($_POST['confirmed']) ? 1 : 0;

    if ($name === '' || $email === '') {
        $msg = "Jméno i e-mail jsou povinné.";
    } else {
        // pokud admin zaškrtne potvrzení, vyplníme confirmed_by a confirmed_at
        if ($confirmed) {
            $stmt = $conn->prepare("
                UPDATE visits 
                SET name=?, email=?, paid=?, confirmed_by=?, confirmed_at=NOW()
                WHERE id=?
            ");
            $stmt->bind_param("ssiii", $name, $email, $paid, $_SESSION['admin_id'], $id);
        } else {
            // pokud odškrtne potvrzení, vynulujeme confirmed_by a confirmed_at
            $stmt = $conn->prepare("
                UPDATE visits 
                SET name=?, email=?, paid=?, confirmed_by=NULL, confirmed_at=NULL
                WHERE id=?
            ");
            $stmt->bind_param("ssii", $name, $email, $paid, $id);
        }

        $stmt->execute();
        header("Location: list.php");
        exit;
    }
}

include "../includes/header.php";
?>

<div class="container py-4">
    <h2 class="mb-4">Upravit návštěvu</h2>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <form method="post" class="bg-white p-4 rounded shadow-sm">
        <div class="row">
            <div class="col-md-12">

                <div class="mb-3">
                    <label class="form-label">Jméno návštěvníka</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($visit['name']) ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">E-mail návštěvníka</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($visit['email']) ?>" required>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="paid" id="paid" value="1" <?= $visit['paid'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="paid">Uhrazeno</label>
                </div>

                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="confirmed" id="confirmed" value="1" <?= $visit['confirmed_at'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="confirmed">Potvrzeno</label>
                </div>

                <div class="d-flex justify-content-between">
                        <button class="btn btn-success"><i class="bi bi-save me-1"></i> Uložit</button>
                                 <a href="list.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left me-1"></i> Zpět
                                </a>
                </div>

            </div>
        </div>
    </form>
</div>

<?php include "../includes/footer.php"; ?>
