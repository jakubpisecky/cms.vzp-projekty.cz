<div class="mb-3">
    <label class="form-label">Hlavní nadpis</label>
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
              rows="6"><?= e($content) ?></textarea>
</div>

<div class="mb-3">
    <label class="form-label">Obrázek</label>

    <div class="input-group">
        <input type="text"
               name="image"
               id="block_image"
               class="form-control"
               placeholder="URL obrázku"
               value="<?= e($image) ?>"
               readonly>

        <button type="button"
                class="btn btn-outline-secondary open-picker"
                data-bs-toggle="modal"
                data-bs-target="#imagePickerModal"
                data-iframe-src="/pictures/list.php?picker=thumb"
                data-target-input="#block_image"
                data-target-preview="#block-image-preview">
            Vybrat...
        </button>
    </div>

    <div class="mt-2"
         id="block-image-preview"
         style="<?= empty($image) ? 'display:none;' : '' ?>">

        <img src="<?= e($image) ?>"
             alt="Náhled"
             class="img-fluid mt-2 rounded border"
             style="max-height:150px;">
    </div>
</div>
<div class="mb-3">
    <label class="form-label">Pozice obrázku</label>

    <select name="image_position" class="form-select">
        <option value="right" <?= ($image_position ?? 'right') === 'right' ? 'selected' : '' ?>>
            Obrázek vpravo
        </option>

        <option value="left" <?= ($image_position ?? '') === 'left' ? 'selected' : '' ?>>
            Obrázek vlevo
        </option>
    </select>
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