<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('forms');

$formId = intval($_GET['id'] ?? 0);

if ($formId <= 0) {
    header("Location: list.php");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM forms WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $formId);
$stmt->execute();
$form = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$form) {
    header("Location: list.php");
    exit;
}

$msg = "";
$errors = [];

$fieldTypes = [
    'text' => 'Text',
    'email' => 'E-mail',
    'phone' => 'Telefon',
    'textarea' => 'Textarea',
    'checkbox' => 'Checkbox',
    'select' => 'Select',
];

$widths = [
    '100' => '100 %',
    '75'  => '75 %',
    '66'  => '66 %',
    '50'  => '50 %',
    '33'  => '33 %',
    '25'  => '25 %',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {

        $fieldId = intval($_POST['field_id'] ?? 0);

        $label = trim($_POST['label'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $type = trim($_POST['type'] ?? 'text');
        $placeholder = trim($_POST['placeholder'] ?? '');
        $help_text = trim($_POST['help_text'] ?? '');
        $options = trim($_POST['options'] ?? '');
        $width = trim($_POST['width'] ?? '100');

        $is_required = isset($_POST['is_required']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '' && $label !== '') {
            $name = slugify($label);
            $name = str_replace('-', '_', $name);
        } else {
            $name = slugify($name);
            $name = str_replace('-', '_', $name);
        }

        if ($label === '') {
            $errors[] = "Název pole je povinný.";
        }

        if ($name === '') {
            $errors[] = "Technický název pole je povinný.";
        }

        if (!array_key_exists($type, $fieldTypes)) {
            $type = 'text';
        }

        if (!array_key_exists($width, $widths)) {
            $width = '100';
        }

        if (!$errors) {

            if ($action === 'add') {

                $stmt = $conn->prepare("
                    SELECT id
                    FROM form_fields
                    WHERE form_id = ?
                      AND name = ?
                    LIMIT 1
                ");
                $stmt->bind_param("is", $formId, $name);
                $stmt->execute();
                $exists = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($exists) {
                    $errors[] = "Pole s tímto technickým názvem už existuje.";
                } else {

                    $res = $conn->query("
                        SELECT COALESCE(MAX(sort_order), 0) + 10 AS next_order
                        FROM form_fields
                        WHERE form_id = " . (int)$formId
                    );

                    $row = $res ? $res->fetch_assoc() : null;
                    $sort_order = (int)($row['next_order'] ?? 10);

                    if ($sort_order <= 0) {
                        $sort_order = 10;
                    }

                    $stmt = $conn->prepare("
                        INSERT INTO form_fields
                            (form_id, label, name, type, placeholder, help_text, options, is_required, width, sort_order, is_active)
                        VALUES
                            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $stmt->bind_param(
                        "issssssisii",
                        $formId,
                        $label,
                        $name,
                        $type,
                        $placeholder,
                        $help_text,
                        $options,
                        $is_required,
                        $width,
                        $sort_order,
                        $is_active
                    );

                    $stmt->execute();
                    $stmt->close();

                    logAction("Přidáno pole '$label' do formuláře '{$form['name']}'");

                    header("Location: fields.php?id=" . (int)$formId . "&field_created=1");
                    exit;
                }

            } else {

                if ($fieldId <= 0) {
                    $errors[] = "Neplatné ID pole.";
                } else {

                    $stmt = $conn->prepare("
                        SELECT id
                        FROM form_fields
                        WHERE form_id = ?
                          AND name = ?
                          AND id != ?
                        LIMIT 1
                    ");
                    $stmt->bind_param("isi", $formId, $name, $fieldId);
                    $stmt->execute();
                    $exists = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if ($exists) {
                        $errors[] = "Pole s tímto technickým názvem už existuje.";
                    } else {

                        $stmt = $conn->prepare("
                            UPDATE form_fields
                            SET label = ?,
                                name = ?,
                                type = ?,
                                placeholder = ?,
                                help_text = ?,
                                options = ?,
                                is_required = ?,
                                width = ?,
                                is_active = ?
                            WHERE id = ?
                              AND form_id = ?
                            LIMIT 1
                        ");

                        $stmt->bind_param(
                            "ssssssisiii",
                            $label,
                            $name,
                            $type,
                            $placeholder,
                            $help_text,
                            $options,
                            $is_required,
                            $width,
                            $is_active,
                            $fieldId,
                            $formId
                        );

                        $stmt->execute();
                        $stmt->close();

                        logAction("Upraveno pole '$label' ve formuláři '{$form['name']}'");

                        header("Location: fields.php?id=" . (int)$formId . "&field_updated=1");
                        exit;
                    }
                }
            }
        }
    }

    if ($action === 'delete') {
        $fieldId = intval($_POST['field_id'] ?? 0);

        if ($fieldId > 0) {
            $stmt = $conn->prepare("
                DELETE FROM form_fields
                WHERE id = ?
                  AND form_id = ?
                LIMIT 1
            ");
            $stmt->bind_param("ii", $fieldId, $formId);
            $stmt->execute();
            $stmt->close();

            logAction("Smazáno pole formuláře '{$form['name']}'");

            header("Location: fields.php?id=" . (int)$formId . "&field_deleted=1");
            exit;
        }
    }
}

$stmt = $conn->prepare("
    SELECT *
    FROM form_fields
    WHERE form_id = ?
    ORDER BY sort_order ASC, id ASC
");
$stmt->bind_param("i", $formId);
$stmt->execute();
$fields = $stmt->get_result();
$stmt->close();

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="admin-page-header-content mb-4">
        <div>
            <h2 class="mb-1">Pole formuláře</h2>
            <p class="text-muted mb-0"><?= e($form['name']) ?></p>
        </div>

        <div>
            <a href="edit.php?id=<?= (int)$formId ?>" class="btn btn-outline-secondary">
                Nastavení
            </a>

            <a href="list.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Zpět
            </a>

            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#fieldModal">
                <i class="bi bi-plus-lg me-1"></i> Přidat pole
            </button>
        </div>
    </div>

    <?php if (isset($_GET['created'])): ?>
        <div class="alert alert-success">Formulář byl vytvořen. Nyní přidejte jeho pole.</div>
    <?php endif; ?>

    <?php if (isset($_GET['field_created'])): ?>
        <div class="alert alert-success">Pole bylo přidáno.</div>
    <?php endif; ?>

    <?php if (isset($_GET['field_updated'])): ?>
        <div class="alert alert-success">Pole bylo upraveno.</div>
    <?php endif; ?>

    <?php if (isset($_GET['field_deleted'])): ?>
        <div class="alert alert-success">Pole bylo smazáno.</div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <div><?= e($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        

            <?php if ($fields->num_rows === 0): ?>

                <div class="text-center text-muted py-5">
                    Zatím nejsou přidaná žádná pole.
                </div>

            <?php else: ?>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover align-middle mb-0 bg-white">
                        <thead>
                            <tr>
                                <th>Název</th>
                                <th>Technický název</th>
                                <th>Typ</th>
                                <th>Šířka</th>
                                <th class="text-center">Povinné</th>
                                <th class="text-center">Stav</th>
                                <th class="text-end">Akce</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php while ($field = $fields->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($field['label']) ?></strong>

                                        <?php if (!empty($field['help_text'])): ?>
                                            <div class="text-muted small">
                                                <?= e($field['help_text']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <code><?= e($field['name']) ?></code>
                                    </td>

                                    <td>
                                        <?= e($fieldTypes[$field['type']] ?? $field['type']) ?>
                                    </td>

                                    <td>
                                        <?= e($field['width']) ?> %
                                    </td>

                                    <td class="text-center">
                                        <?= (int)$field['is_required'] === 1 ? '<span class="badge bg-danger">Ano</span>' : '<span class="badge bg-secondary">Ne</span>' ?>
                                    </td>

                                    <td class="text-center">
                                        <?= (int)$field['is_active'] === 1 ? '<span class="badge bg-success">Aktivní</span>' : '<span class="badge bg-secondary">Neaktivní</span>' ?>
                                    </td>

                                    <td class="text-end">
                                        <button type="button"
                                                class="btn btn-sm btn-outline-secondary edit-field-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#fieldModal"
                                                data-id="<?= (int)$field['id'] ?>"
                                                data-label="<?= e($field['label']) ?>"
                                                data-name="<?= e($field['name']) ?>"
                                                data-type="<?= e($field['type']) ?>"
                                                data-placeholder="<?= e($field['placeholder'] ?? '') ?>"
                                                data-help-text="<?= e($field['help_text'] ?? '') ?>"
                                                data-options="<?= e($field['options'] ?? '') ?>"
                                                data-width="<?= e($field['width']) ?>"
                                                data-required="<?= (int)$field['is_required'] ?>"
                                                data-active="<?= (int)$field['is_active'] ?>">
                                            Upravit
                                        </button>

                                        <form method="post" class="d-inline" onsubmit="return confirm('Opravdu chcete pole smazat?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="field_id" value="<?= (int)$field['id'] ?>">

                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                Smazat
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>

        
    </div>

</div>

<div class="modal fade" id="fieldModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="post" class="modal-content">

            <input type="hidden" name="action" id="field_action" value="add">
            <input type="hidden" name="field_id" id="field_id" value="">

            <div class="modal-header">
                <h5 class="modal-title" id="fieldModalTitle">Přidat pole</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zavřít"></button>
            </div>

            <div class="modal-body">

                <div class="mb-3">
                    <label class="form-label">Název pole *</label>
                    <input type="text" name="label" id="field_label" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Technický název</label>
                    <input type="text" name="name" id="field_name" class="form-control">
                    <div class="form-text">Pokud necháte prázdné, vytvoří se automaticky z názvu.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Typ pole</label>
                    <select name="type" id="field_type" class="form-select">
                        <?php foreach ($fieldTypes as $key => $label): ?>
                            <option value="<?= e($key) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Placeholder</label>
                    <input type="text" name="placeholder" id="field_placeholder" class="form-control">
                </div>

                <div class="mb-3">
                    <label class="form-label">Nápověda</label>
                    <textarea name="help_text" id="field_help_text" class="form-control" rows="2"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Možnosti</label>
                    <textarea name="options" id="field_options" class="form-control" rows="4"></textarea>
                    <div class="form-text">
                        Používá se pro Select. Každá možnost na nový řádek.
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Šířka</label>
                    <select name="width" id="field_width" class="form-select">
                        <?php foreach ($widths as $key => $label): ?>
                            <option value="<?= e($key) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="is_required" id="field_required" value="1">
                    <label class="form-check-label" for="field_required">Povinné</label>
                </div>

                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" name="is_active" id="field_active" value="1" checked>
                    <label class="form-check-label" for="field_active">Aktivní</label>
                </div>

            </div>

            <div class="modal-footer">
                <button type="submit" class="btn btn-success">
                    Uložit pole
                </button>

                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    Zavřít
                </button>
            </div>

        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var fieldModal = document.getElementById('fieldModal');
    var modalTitle = document.getElementById('fieldModalTitle');

    var actionInput = document.getElementById('field_action');
    var idInput = document.getElementById('field_id');
    var labelInput = document.getElementById('field_label');
    var nameInput = document.getElementById('field_name');
    var typeInput = document.getElementById('field_type');
    var placeholderInput = document.getElementById('field_placeholder');
    var helpTextInput = document.getElementById('field_help_text');
    var optionsInput = document.getElementById('field_options');
    var widthInput = document.getElementById('field_width');
    var requiredInput = document.getElementById('field_required');
    var activeInput = document.getElementById('field_active');

    fieldModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;

        if (!button || !button.classList.contains('edit-field-btn')) {
            modalTitle.textContent = 'Přidat pole';

            actionInput.value = 'add';
            idInput.value = '';
            labelInput.value = '';
            nameInput.value = '';
            typeInput.value = 'text';
            placeholderInput.value = '';
            helpTextInput.value = '';
            optionsInput.value = '';
            widthInput.value = '100';
            requiredInput.checked = false;
            activeInput.checked = true;

            return;
        }

        modalTitle.textContent = 'Upravit pole';

        actionInput.value = 'edit';
        idInput.value = button.getAttribute('data-id') || '';
        labelInput.value = button.getAttribute('data-label') || '';
        nameInput.value = button.getAttribute('data-name') || '';
        typeInput.value = button.getAttribute('data-type') || 'text';
        placeholderInput.value = button.getAttribute('data-placeholder') || '';
        helpTextInput.value = button.getAttribute('data-help-text') || '';
        optionsInput.value = button.getAttribute('data-options') || '';
        widthInput.value = button.getAttribute('data-width') || '100';
        requiredInput.checked = button.getAttribute('data-required') === '1';
        activeInput.checked = button.getAttribute('data-active') === '1';
    });
});
</script>

<?php include "../includes/footer.php"; ?>