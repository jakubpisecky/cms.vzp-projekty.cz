<div class="mb-3">
    <label class="form-label">Nadpis CTA bloku</label>
    <input type="text"
           name="title"
           class="form-control"
           value="<?= e($title) ?>">
</div>

<div class="mb-3">
    <label class="form-label">Text CTA bloku</label>
    <textarea name="content"
              class="form-control editor"
              rows="6"><?= e($content) ?></textarea>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Text tlačítka</label>
        <input type="text"
               name="button_text"
               class="form-control"
               value="<?= e($button_text) ?>">
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label">URL tlačítka</label>
        <input type="text"
               name="button_url"
               class="form-control"
               value="<?= e($button_url) ?>">
    </div>
</div>