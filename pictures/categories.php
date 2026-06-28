<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('pictures');

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    $name   = trim($_POST['name'] ?? '');

    if ($name === '') {
        $error = "Název kategorie je povinný.";
    } else {
        $slug = slugify($name);

        if ($action === 'add') {
            $stmt = $conn->prepare("
                INSERT INTO picture_categories (name, slug, sort_order, created_at)
                VALUES (?, ?, 0, NOW())
            ");
            $stmt->bind_param("ss", $name, $slug);

            if ($stmt->execute()) {
                header("Location: categories.php?created=1");
                exit;
            }

            $error = "Kategorii se nepodařilo uložit: " . $stmt->error;
            $stmt->close();
        }

        if ($action === 'edit' && $id > 0) {
            $stmt = $conn->prepare("
                UPDATE picture_categories
                SET name = ?, slug = ?
                WHERE id = ?
            ");
            $stmt->bind_param("ssi", $name, $slug, $id);

            if ($stmt->execute()) {
                header("Location: categories.php?updated=1");
                exit;
            }

            $error = "Kategorii se nepodařilo upravit: " . $stmt->error;
            $stmt->close();
        }
    }

    if ($action === 'delete' && $id > 0) {
        $stmt = $conn->prepare("
            UPDATE pictures
            SET category_id = NULL
            WHERE category_id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("
            DELETE FROM picture_categories
            WHERE id = ?
        ");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            header("Location: categories.php?deleted=1");
            exit;
        }

        $error = "Kategorii se nepodařilo smazat: " . $stmt->error;
        $stmt->close();
    }
}

$sql = "
    SELECT 
        c.*,
        COUNT(p.id) AS picture_count
    FROM picture_categories c
    LEFT JOIN pictures p ON p.category_id = c.id
    GROUP BY c.id
    ORDER BY c.sort_order ASC, c.name ASC
";

$result = $conn->query($sql);

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="mb-1">Kategorie obrázků</h2>
            <p class="text-muted mb-0">Správa kategorií v knihovně obrázků</p>
        </div>

        <div class="responsive-actions">
            <a href="list.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Zpět na obrázky
            </a>
        </div>
    </div>

    <?php if (isset($_GET['created'])): ?>
        <div class="alert alert-success">Kategorie byla vytvořena.</div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success">Kategorie byla upravena.</div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">Kategorie byla smazána.</div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white">
            <strong>Nová kategorie</strong>
        </div>

        <div class="card-body">
            <form method="post" class="row g-3">
                <input type="hidden" name="action" value="add">

                <div class="col-lg-7">
                    <input type="text"
                           name="name"
                           class="form-control"
                           placeholder="Název kategorie"
                           required>
                </div>

                <div class="col-auto">
                    <button class="btn btn-success">
                        <i class="bi bi-plus-circle me-1"></i> Přidat kategorii
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover align-middle mb-0 bg-white">
                <thead class="table-light">
                    <tr>
                        <th width="70" class="text-center">ID</th>
                        <th>Název</th>
                        <th>Slug</th>
                        <th width="120" class="text-center">Obrázků</th>
                        <th width="260" class="text-center">Akce</th>
                    </tr>
                </thead>

                <tbody>
                    <?php while ($cat = $result->fetch_assoc()): ?>
                        <?php $id = (int)$cat['id']; ?>

                        <tr>
                            <td class="text-center"><?= $id ?></td>

                            <td>
                                <form method="post" class="d-flex gap-2">
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="id" value="<?= $id ?>">

                                    <input type="text"
                                           name="name"
                                           class="form-control form-control-sm"
                                           value="<?= e($cat['name']) ?>"
                                           required>

                                    <button class="btn btn-sm btn-primary">
                                        <i class="bi bi-save"></i>
                                    </button>
                                </form>
                            </td>

                            <td>
                                <span class="text-muted small">/<?= e($cat['slug']) ?></span>
                            </td>

                            <td class="text-center">
                                <span class="badge bg-light text-dark">
                                    <?= (int)$cat['picture_count'] ?>
                                </span>
                            </td>

                            <td class="text-center text-nowrap">
                                <form method="post"
                                      class="d-inline"
                                      onsubmit="return confirm('Opravdu smazat tuto kategorii? Obrázky v této kategorii zůstanou zachované, jen budou bez kategorie.');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $id ?>">

                                    <button class="btn btn-sm btn-danger">
                                        <i class="bi bi-trash"></i> Smazat
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                    <?php if ($result->num_rows === 0): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted p-4">
                                Zatím nejsou vytvořené žádné kategorie obrázků.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>

            </table>
        </div>
    </div>

</div>

<?php include "../includes/footer.php"; ?>