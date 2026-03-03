<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('galleries');

$id = intval($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM galleries WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$gallery = $stmt->get_result()->fetch_assoc();

if (!$gallery) {
    header("Location: list.php");
    exit;
}

$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');

    if ($title === '') {
        $msg = "Název galerie je povinný.";
    } else {
        $stmt = $conn->prepare("UPDATE galleries SET title=? WHERE id=?");
        $stmt->bind_param("si", $title, $id);
        $stmt->execute();

        header("Location: list.php");
        exit;
    }
}

include "../includes/header.php";
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <h2 class="mb-4">Upravit galerii</h2>

            <?php if (!empty($msg)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <form method="post" class="bg-white p-4 rounded shadow-sm">
                <div class="mb-3">
                    <label class="form-label">Název galerie</label>
                    <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($gallery['title']) ?>" required>
                </div>

                <div class="d-flex justify-content-between">
                    <button class="btn btn-success">
                        <i class="bi bi-save me-1"></i> Uložit změny
                    </button>
                    <a href="list.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Zpět
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
