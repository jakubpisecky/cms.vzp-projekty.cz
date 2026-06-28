<?php
$galleries = $conn->query("
    SELECT id, title
    FROM galleries
    ORDER BY title ASC
");
?>

<div class="mb-3">
    <label class="form-label">Název sekce</label>
    <input type="text"
           name="title"
           class="form-control"
           value="<?= e($title) ?>">
</div>

<div class="mb-3">
    <label class="form-label">Vybrat fotogalerii</label>

    <select name="gallery_id" class="form-select" required>
        <option value="">— Vyberte fotogalerii —</option>

        <?php while ($gallery = $galleries->fetch_assoc()): ?>
            <option value="<?= (int)$gallery['id'] ?>"
                <?= (int)$gallery_id === (int)$gallery['id'] ? 'selected' : '' ?>>
                <?= e($gallery['title']) ?>
            </option>
        <?php endwhile; ?>
    </select>
</div>
