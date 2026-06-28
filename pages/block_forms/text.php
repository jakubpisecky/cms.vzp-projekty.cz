<div class="mb-3">
    <label class="form-label">Název bloku</label>
    <input type="text"
           name="title"
           class="form-control"
           value="<?= e($title) ?>">
</div>

<div class="mb-3">
    <label class="form-label">Textový obsah</label>
    <textarea name="content"
              class="form-control editor"
              rows="12"><?= e($content) ?></textarea>
</div>