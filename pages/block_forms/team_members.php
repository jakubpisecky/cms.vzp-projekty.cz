<div class="mb-3">
    <label class="form-label">Nadpis bloku</label>
    <input type="text"
           name="title"
           class="form-control"
           value="<?= e($title ?: 'Náš tým') ?>">
</div>

<div class="mb-3">
    <label class="form-label">Podnadpis</label>
    <input type="text"
           name="subtitle"
           class="form-control"
           value="<?= e($subtitle ?? '') ?>">
</div>

<div class="mb-3">
    <label class="form-label">Zobrazení</label>
    <select name="layout" class="form-select">
        <option value="cards" <?= ($layout ?? 'cards') === 'cards' ? 'selected' : '' ?>>
            Karty
        </option>
        <option value="list" <?= ($layout ?? '') === 'list' ? 'selected' : '' ?>>
            Seznam
        </option>
    </select>
</div>