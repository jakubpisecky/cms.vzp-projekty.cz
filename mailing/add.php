<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/mailing.php";

requirePermission('mailing');

$title        = '';
$subject      = '';
$content      = '';
$sender_name  = '';
$sender_email = '';
$reply_to     = '';

$error = '';

$templates = [];

$res = $conn->query("
    SELECT id, title, content
    FROM mailing_templates
    ORDER BY title ASC
");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $templates[] = $row;
    }
}

// výchozí hodnoty jen při prvním načtení formuláře
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $sender_name  = trim((string) (function_exists('setting') ? setting('mailing_from_name') : ''));
    $sender_email = trim((string) (function_exists('setting') ? setting('mailing_from_email') : ''));
    $reply_to     = trim((string) (function_exists('setting') ? setting('mailing_reply_to') : ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title        = trim($_POST['title'] ?? '');
    $subject      = trim($_POST['subject'] ?? '');
    $content      = trim($_POST['content'] ?? '');
    $sender_name  = trim($_POST['sender_name'] ?? '');
    $sender_email = trim($_POST['sender_email'] ?? '');
    $reply_to     = trim($_POST['reply_to'] ?? '');

    if ($title === '' || $subject === '' || $content === '') {
        $error = "Vyplňte všechna povinná pole.";
    } elseif ($sender_email !== '' && !filter_var($sender_email, FILTER_VALIDATE_EMAIL)) {
        $error = "E-mail odesílatele není ve správném formátu.";
    } elseif ($reply_to !== '' && !filter_var($reply_to, FILTER_VALIDATE_EMAIL)) {
        $error = "Reply-To e-mail není ve správném formátu.";
    } else {
        $createdBy = (int) ($_SESSION['admin_id'] ?? 0);

        $stmt = $conn->prepare("
            INSERT INTO mailing_campaigns
                (title, subject, content, sender_name, sender_email, reply_to, created_by)
            VALUES
                (?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            $error = "Nepodařilo se připravit SQL dotaz: " . $conn->error;
        } else {
            $stmt->bind_param(
                "ssssssi",
                $title,
                $subject,
                $content,
                $sender_name,
                $sender_email,
                $reply_to,
                $createdBy
            );

            if ($stmt->execute()) {
                $newId = (int) $stmt->insert_id;
                $stmt->close();

                if (function_exists('logAction')) {
                    logAction("Vytvořena mailing kampaň ID {$newId}: {$title}");
                }

                header("Location: list.php?created=1");
                exit;
            } else {
                $error = "Nepodařilo se uložit kampaň: " . $stmt->error;
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

            <div class="admin-page-header-content mb-4">
                <div>
                    <h2 class="mb-1">Nová mailing kampaň</h1>
                    <p class="text-muted mb-0">Vytvoření nové e-mailové kampaně</p>
                </div>
                    <a href="list.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Zpět na přehled
                    </a>
            </div>

            <?php include "_menu.php"; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <form method="post">

                        <div class="mb-3">
                            <label for="title" class="form-label">Název kampaně <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                class="form-control"
                                id="title"
                                name="title"
                                value="<?php echo htmlspecialchars($title); ?>"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label for="subject" class="form-label">Předmět e-mailu <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                class="form-control"
                                id="subject"
                                name="subject"
                                value="<?php echo htmlspecialchars($subject); ?>"
                                required
                            >
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="sender_name" class="form-label">Jméno odesílatele</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="sender_name"
                                    name="sender_name"
                                    value="<?php echo htmlspecialchars($sender_name); ?>"
                                >
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="sender_email" class="form-label">E-mail odesílatele</label>
                                <input
                                    type="email"
                                    class="form-control"
                                    id="sender_email"
                                    name="sender_email"
                                    value="<?php echo htmlspecialchars($sender_email); ?>"
                                >
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="reply_to" class="form-label">Reply-To e-mail</label>
                            <input
                                type="email"
                                class="form-control"
                                id="reply_to"
                                name="reply_to"
                                value="<?php echo htmlspecialchars($reply_to); ?>"
                            >
                        </div>
                        <div class="mb-3">
                            <label for="template_id" class="form-label">Šablona</label>
                            <select id="template_id" class="form-select">
                                <option value="">-- Vyber šablonu --</option>
                                <?php foreach ($templates as $tpl): ?>
                                    <option value="<?php echo (int)$tpl['id']; ?>">
                                        <?php echo htmlspecialchars($tpl['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Po výběru se HTML obsah šablony vloží do pole Obsah e-mailu.</div>
                        </div>
                        <div class="mb-3">
                            <label for="content" class="form-label">Obsah e-mailu <span class="text-danger">*</span></label>
                            <textarea
                                class="form-control"
                                id="content"
                                name="content"
                                rows="20"
                                required
                            ><?php echo htmlspecialchars($content); ?></textarea>
                            <div class="form-text">
                                Můžeš používat proměnné: <code>{first_name}</code>, <code>{last_name}</code>, <code>{name}</code>, <code>{email}</code>, <code>{company}</code>, <code>{unsubscribe_link}</code>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="list.php" class="btn btn-outline-secondary">
                                Zrušit
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Uložit kampaň
                            </button>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>
</div>


<script>
const mailingTemplates = <?php echo json_encode($templates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

document.addEventListener('DOMContentLoaded', function () {
    const templateSelect = document.getElementById('template_id');
    if (!templateSelect) return;

    templateSelect.addEventListener('change', function () {
        const id = this.value;
        if (!id) return;

        const tpl = mailingTemplates.find(t => String(t.id) === String(id));
        if (!tpl) return;

        if (!confirm('Chceš nahradit obsah e-mailu obsahem vybrané šablony?')) {
            return;
        }

        if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
            tinymce.get('content').setContent(tpl.content || '');
        } else {
            document.getElementById('content').value = tpl.content || '';
        }
    });
});
</script>
<?php include "../includes/footer.php"; ?>