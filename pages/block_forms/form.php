<?php
// /pages/block_forms/form.php

$form_id = (int)($block['form_id'] ?? 0);

$forms = [];

$res = $conn->query("
    SELECT id, name, slug
    FROM forms
    WHERE is_active = 1
    ORDER BY sort_order ASC, name ASC
");

while ($row = $res->fetch_assoc()) {
    $forms[] = $row;
}
?>

<div class="mb-3">
    <label class="form-label">Nadpis bloku</label>
    <input type="text"
           name="title"
           class="form-control"
           value="<?= e($title ?? '') ?>">
</div>

<div class="mb-3">
    <label class="form-label">Vyberte formulář</label>

    <select name="form_id" class="form-select" required>
        <option value="">— Vyberte formulář —</option>

        <?php foreach ($forms as $form): ?>
            <option value="<?= (int)$form['id'] ?>"
                <?= $form_id === (int)$form['id'] ? 'selected' : '' ?>>
                <?= e($form['name']) ?>
                <?php if (!empty($form['slug'])): ?>
                    (<?= e($form['slug']) ?>)
                <?php endif; ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<?php if (empty($forms)): ?>
    <div class="alert alert-warning">
        Zatím není vytvořený žádný aktivní formulář.
    </div>
<?php endif; ?>