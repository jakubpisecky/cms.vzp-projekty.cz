<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('galleries');

$msg = "";

/**
 * Vrátí unikátní slug: pokud už existuje, přidá -2, -3, ...
 */
function makeUniqueSlug(mysqli $conn, string $baseSlug): string {
    $slug = $baseSlug !== '' ? $baseSlug : 'galerie';
    $i = 1;

    $stmt = $conn->prepare("SELECT COUNT(*) FROM galleries WHERE slug = ?");
    if (!$stmt) return $slug;

    while (true) {
        $check = $slug;
        $stmt->bind_param("s", $check);
        $stmt->execute();
        $stmt->bind_result($cnt);
        $stmt->fetch();
        $stmt->free_result();

        if ((int)$cnt === 0) {
            $stmt->close();
            return $slug;
        }

        $i++;
        $slug = $baseSlug . '-' . $i;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');

    if ($title === '') {
        $msg = "Název galerie je povinný.";
    } else {
        // vygenerujeme slug
        $baseSlug = slugify($title);
        if ($baseSlug === '') {
            $baseSlug = 'galerie';
        }
        $slug = makeUniqueSlug($conn, $baseSlug);

        // vložení do DB včetně slugu
        $stmt = $conn->prepare("INSERT INTO galleries (title, slug, created_at) VALUES (?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("ss", $title, $slug);
            $stmt->execute();
            $stmt->close();

            header("Location: list.php");
            exit;
        } else {
            $msg = "Chyba při ukládání do databáze.";
        }
    }
}

include "../includes/header.php";
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <h2 class="mb-4">Přidat novou galerii</h2>

            <?php if (!empty($msg)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <form method="post" class="bg-white p-4 rounded shadow-sm">
                <div class="mb-3">
                    <label class="form-label">Název galerie</label>
                    <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
                </div>

                <div class="d-flex justify-content-between">
                    <button class="btn btn-success">
                        <i class="bi bi-plus-circle me-1"></i> Uložit
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
