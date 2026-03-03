<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('categories');

$id = intval($_GET['id'] ?? 0);

// Načtení kategorie
$stmt = $conn->prepare("SELECT * FROM categories WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$category = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$category) {
    header("Location: list.php");
    exit;
}

$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $slug = slugify($name);

    if ($name === '') {
        $msg = "Název rubriky je povinný.";
    } elseif ($slug === '') {
        $msg = "Z názvu se nepodařilo vygenerovat slug.";
    } else {
        // Předběžná kontrola duplicity slugu u jiné kategorie
        $check = $conn->prepare("SELECT id FROM categories WHERE slug = ? AND id <> ? LIMIT 1");
        $check->bind_param("si", $slug, $id);
        $check->execute();
        $existsRes = $check->get_result();
        $exists = $existsRes && $existsRes->num_rows > 0;
        $check->close();

        if ($exists) {
            $msg = "Kategorie s tímto názvem už existuje.";
        } else {
            // Pokus o UPDATE + fallback zachycení 1062
            try {
                $stmt = $conn->prepare("UPDATE categories SET name=?, slug=? WHERE id=?");
                $stmt->bind_param("ssi", $name, $slug, $id);
                $stmt->execute();
                $stmt->close();

                header("Location: list.php?updated=1");
                exit;
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() === 1062) {
                    $msg = "Kategorie s tímto názvem už existuje.";
                } else {
                    $msg = "Chyba při ukládání do databáze.";
                }
            }
        }
    }

    // Pokud došlo k chybě, promítneme nové jméno do $category kvůli zobrazení ve formuláři
    if (!empty($msg)) {
        $category['name'] = $name;
        // Pozn.: slug zůstává jen informativně – generuje se znovu při odeslání.
    }
}

include "../includes/header.php";
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <h2 class="mb-4">Upravit kategorii</h2>

            <?php if (!empty($msg)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <form method="post" class="bg-white p-4 rounded shadow-sm" novalidate>
                <div class="mb-3">
                    <label class="form-label">Název kategorie</label>
                    <input type="text"
                           name="name"
                           class="form-control"
                           value="<?= htmlspecialchars($category['name'] ?? '') ?>"
                           required>
                    <div class="form-text">Slug se vygeneruje automaticky z názvu; při kolizi se zobrazí upozornění.</div>
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
