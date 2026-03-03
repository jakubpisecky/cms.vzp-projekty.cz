<?php 
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('categories');
include "../includes/header.php";
$totalCategories = (int)$conn->query("SELECT COUNT(*) FROM categories")->fetch_row()[0];
$result = $conn->query("SELECT * FROM categories ORDER BY name ASC");
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="mb-0">Kategorie <small class="text-muted">(<?= $totalCategories ?>)</small></h2>
                <a href="add.php" class="btn btn-success">
                    <i class="bi bi-plus-circle me-1"></i> Přidat kategorii
                </a>
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
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover bg-white">
                    <thead class="table-light">
                        <tr>
                            <th style="width:60px;">ID</th>
                            <th>Název</th>
                            <th>Slug</th>
                            <th style="width:120px;" class="text-center">Akce</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($category = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= $category['id'] ?></td>
                                <td><?= htmlspecialchars($category['name']) ?></td>
                                <td><?= htmlspecialchars($category['slug']) ?></td>
                                <td class="text-center text-nowrap">
                                    <div class="d-flex justify-content-center gap-2">
                                        <a href="edit.php?id=<?= $category['id'] ?>" class="btn btn-sm btn-primary" title="Upravit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="delete.php?id=<?= $category['id'] ?>" class="btn btn-sm btn-danger" title="Smazat"
                                           onclick="return confirm('Opravdu chcete kategorii smazat?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
