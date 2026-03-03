<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('galleries');

include "../includes/header.php";

/**
 * Načteme galerie i s počtem fotek jedním dotazem.
 * LEFT JOIN zajistí, že i prázdná galerie má count = 0.
 */
$sql = "
SELECT g.*,
       COALESCE(p.cnt, 0) AS photo_count
FROM galleries g
LEFT JOIN (
    SELECT gallery_id, COUNT(*) AS cnt
    FROM gallery_photos
    GROUP BY gallery_id
) p ON p.gallery_id = g.id
ORDER BY g.created_at DESC
";
$result = $conn->query($sql);
$totalGalleries = (int)$conn->query("SELECT COUNT(*) FROM galleries")->fetch_row()[0];
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Fotogalerie <small class="text-muted">(<?= $totalGalleries ?>)</small></h2>
        <a href="add.php" class="btn btn-success">
            <i class="bi bi-plus-circle me-1"></i> Přidat galerii
        </a>
    </div>
  <div class="table-responsive">
    <table class="table table-bordered table-striped table-hover bg-white">
        <thead>
            <tr>
                <th width="60">ID</th>
                <th>Název</th>
                <th>Vytvořeno</th>
                <th width="220" class="text-center">Akce</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $result->fetch_assoc()): ?>
                <?php
                    $id    = (int)$row['id'];
                    $title = htmlspecialchars($row['title'] ?? '');
                    $cnt   = (int)$row['photo_count'];
                ?>
                <tr>
                    <td><?= $id ?></td>
                    <td><?= $title ?></td>
                    <td><?= formatDateCz($row['created_at']) ?></td>
                    <td>
                        <a href="edit.php?id=<?= $id ?>" class="btn btn-sm btn-primary" title="Upravit">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <a href="delete.php?id=<?= $id ?>" class="btn btn-sm btn-danger"
                           onclick="return confirm('Opravdu chcete smazat tuto galerii?')"
                           title="Smazat">
                            <i class="bi bi-trash"></i>
                        </a>
                        <a href="photos.php?gallery_id=<?= $id ?>" class="btn btn-sm btn-secondary" title="Spravovat fotky">
                            <i class="bi bi-images me-1"></i>
                            Fotky <span class="badge bg-light text-dark ms-1"><?= $cnt ?></span>
                        </a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>
    const el = document.getElementById('photo-list');
    new Sortable(el, {
        animation: 150,
        onEnd: function (evt) {
            const order = [];
            document.querySelectorAll('#photo-list .photo-item').forEach((el, index) => {
                order.push({
                    id: el.dataset.id,
                    position: index + 1
                });
            });

            fetch('save_order.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(order)
            });
        }
    });
</script>
<?php include "../includes/footer.php"; ?>
