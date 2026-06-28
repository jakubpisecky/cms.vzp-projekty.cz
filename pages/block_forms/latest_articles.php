<div class="mb-3">
    <label class="form-label">Nadpis sekce</label>
    <input type="text"
           name="title"
           class="form-control"
           value="<?= e($title) ?>">
</div>

<div class="mb-3">
    <label class="form-label">Počet článků</label>
    <input type="number"
           name="article_limit"
           class="form-control"
           value="<?= (int)$article_limit ?>">
</div>

<div class="mb-3">
    <label class="form-label">Layout</label>

    <select name="layout" class="form-select">
        <option value="cards" <?= $layout === 'cards' ? 'selected' : '' ?>>
            Karty
        </option>

        <option value="list" <?= $layout === 'list' ? 'selected' : '' ?>>
            Seznam
        </option>
    </select>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Text tlačítka</label>

        <input type="text"
               name="button_text"
               class="form-control"
               value="<?= e($button_text ?? '') ?>">
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label">Odkaz tlačítka</label>

        <input type="text"
               name="button_url"
               class="form-control"
               placeholder="/aktuality"
               value="<?= e($button_url ?? '') ?>">
    </div>
</div>