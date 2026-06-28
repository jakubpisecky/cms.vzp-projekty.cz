<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('mailing');

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die("Neplatné ID šablony.");
}

$stmt = $conn->prepare("SELECT * FROM mailing_templates WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$template = $result->fetch_assoc();
$stmt->close();

if (!$template) {
    die("Šablona nebyla nalezena.");
}

$title   = $template['title'] ?? '';
$content = $template['content'] ?? '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if ($title === '' || $content === '') {
        $error = "Vyplňte všechna povinná pole.";
    } else {
        $stmt = $conn->prepare("
            UPDATE mailing_templates
            SET title = ?, content = ?
            WHERE id = ?
        ");

        if (!$stmt) {
            $error = "Nepodařilo se připravit SQL dotaz: " . $conn->error;
        } else {
            $stmt->bind_param("ssi", $title, $content, $id);

            if ($stmt->execute()) {
                $stmt->close();

                if (function_exists('logAction')) {
                    logAction("Upravena mailing šablona ID {$id}: {$title}");
                }

                header("Location: templates.php?updated=1");
                exit;
            } else {
                $error = "Nepodařilo se uložit změny: " . $stmt->error;
                $stmt->close();
            }
        }
    }
}

include "../includes/header.php";
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-12">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Upravit šablonu</h1>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($template['title']); ?></p>
                </div>
                <div>
                    <a href="templates.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Zpět na šablony
                    </a>
                </div>
            </div>

            <?php include "_menu.php"; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <form method="post">
                        <div class="mb-3">
                            <label for="title" class="form-label">Název šablony <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($title); ?>" required>
                        </div>
                        <div class="mb-3">
                                <h2 class="h6 mb-3">AI generování šablony</h2>

                                <div class="mb-3">
                                    <label for="ai_prompt" class="form-label">Zadání</label>
                                    <textarea id="ai_prompt" class="form-control" rows="4" placeholder="Např. vytvoř moderní newsletter s modrou hlavičkou, jedním CTA tlačítkem a patičkou."></textarea>
                                </div>

                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-outline-primary" id="ai-generate-btn">
                                        <i class="bi bi-stars"></i> Vygenerovat HTML
                                    </button>

                                    <button type="button" class="btn btn-outline-secondary" id="ai-edit-btn">
                                        <i class="bi bi-pencil-square"></i> Upravit stávající HTML
                                    </button>
                                </div>

                                <div id="ai-status" class="form-text mt-2"></div>
                        </div>
                        <div class="mb-3">
                            <label for="content" class="form-label">HTML obsah <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="content" name="content" rows="20"><?php echo htmlspecialchars($content); ?></textarea>
                            <div class="form-text">
                                Můžeš používat proměnné: <code>{first_name}</code>, <code>{last_name}</code>, <code>{name}</code>, <code>{email}</code>, <code>{company}</code>, <code>{unsubscribe_link}</code>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="templates.php" class="btn btn-outline-secondary">Zrušit</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Uložit změny
                            </button>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const textarea = document.getElementById('content');
    if (!textarea) return;

    window.templateEditor = CodeMirror.fromTextArea(textarea, {
        mode: 'htmlmixed',
        lineNumbers: true,
        lineWrapping: true,
        indentUnit: 2,
        tabSize: 2,
        theme: 'default'
    });

    window.templateEditor.setSize(null, 600);

    const form = textarea.closest('form');
    if (form) {
        form.addEventListener('submit', function () {
            window.templateEditor.save();
        });
    }
});
</script>
<?php include "../includes/footer.php"; ?>