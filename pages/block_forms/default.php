<div class="mb-3">
    <label class="form-label">Název bloku</label>
    <input type="text"
           name="title"
           class="form-control"
           value="<?= e($title) ?>">
</div>

<div class="mb-3">
    <label class="form-label">Podnadpis</label>
    <input type="text"
           name="subtitle"
           class="form-control"
           value="<?= e($subtitle) ?>">
</div>

<div class="mb-3">
    <label class="form-label">Obsah</label>
    <textarea name="content"
              class="form-control editor"
              rows="10"><?= e($content) ?></textarea>
</div>