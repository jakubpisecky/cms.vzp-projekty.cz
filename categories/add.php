<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('categories');

$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $slug = slugify($name);

    if ($name === '') {
        $msg = "Název rubriky je povinný.";
    } elseif ($slug === '') {
        $msg = "Z názvu se nepodařilo vygenerovat slug.";
    } else {
        // 1) Předběžná kontrola duplicity slugu
        $check = $conn->prepare("SELECT id FROM categories WHERE slug = ? LIMIT 1");
        $check->bind_param("s", $slug);
        $check->execute();
        $checkRes = $check->get_result();
        $exists = $checkRes && $checkRes->num_rows > 0;
        $check->close();

        if ($exists) {
            $msg = "Kategorie s tímto názvem už existuje.";
        } else {
            // 2) Pokus o insert + fallback zachycení 1062 (Duplicate entry)
            try {
                $stmt = $conn->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
                $stmt->bind_param("ss", $name, $slug);
                $stmt->execute();
                $stmt->close();

                header("Location: list.php?created=1");
                exit;
            } catch (mysqli_sql_exception $e) {
                // 1062 = Duplicate entry (pro případ race condition apod.)
                if ($e->getCode() === 1062) {
                    $msg = "Kategorie s tímto názvem už existuje.";
                } else {
                    $msg = "Chyba při ukládání do databáze.";
                }
            }
        }
    }
}

include "../includes/header.php";
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <h2 class="mb-4">Přidat kategorii</h2>

            <?php if (!empty($msg)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <form method="post" class="bg-white p-4 rounded shadow-sm" novalidate>
                <div class="mb-3">
                    <label class="form-label">Název kategorie</label>
                    <input type="text" name="name" class="form-control" required
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    <div class="form-text">Slug se vygeneruje automaticky z názvu.</div>
                </div>

                <div class="d-flex justify-content-between">
                    <button class="btn btn-success">
                        <i class="bi bi-save me-1"></i> Uložit
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
