<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('forms');

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: list.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT *
    FROM forms
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$form = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$form) {
    header("Location: list.php");
    exit;
}

$msg = "";

$name = $form['name'] ?? '';
$slug = $form['slug'] ?? '';
$description = $form['description'] ?? '';
$recipient_email = $form['recipient_email'] ?? '';
$email_subject = $form['email_subject'] ?? '';
$success_message = $form['success_message'] ?? '';
$save_submissions = (int)($form['save_submissions'] ?? 1);
$send_email = (int)($form['send_email'] ?? 1);
$use_recaptcha = (int)($form['use_recaptcha'] ?? 0);
$is_active = (int)($form['is_active'] ?? 1);
$sort_order = (int)($form['sort_order'] ?? 10);
$send_confirmation = (int)($form['send_confirmation'] ?? 0);
$confirmation_subject = $form['confirmation_subject'] ?? '';
$confirmation_message = $form['confirmation_message'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $recipient_email = trim($_POST['recipient_email'] ?? '');
    $email_subject = trim($_POST['email_subject'] ?? '');
    $success_message = trim($_POST['success_message'] ?? '');
    $sort_order = intval($_POST['sort_order'] ?? 0);

    $save_submissions = isset($_POST['save_submissions']) ? 1 : 0;
    $send_email = isset($_POST['send_email']) ? 1 : 0;
    $use_recaptcha = isset($_POST['use_recaptcha']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $send_confirmation = isset($_POST['send_confirmation']) ? 1 : 0;
    $confirmation_subject = trim($_POST['confirmation_subject'] ?? '');
    $confirmation_message = trim($_POST['confirmation_message'] ?? '');

    if ($slug === '' && $name !== '') {
        $slug = slugify($name);
    } else {
        $slug = slugify($slug);
    }

    if ($sort_order <= 0) {
        $sort_order = 10;
    }

    if ($name === '') {
        $msg = "Název formuláře je povinný.";
    } elseif ($slug === '') {
        $msg = "Slug je povinný.";
    } else {

        $stmt = $conn->prepare("
            SELECT id
            FROM forms
            WHERE slug = ?
              AND id != ?
            LIMIT 1
        ");
        $stmt->bind_param("si", $slug, $id);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($exists) {
            $msg = "Formulář s tímto slugem už existuje.";
        } else {

            $stmt = $conn->prepare("
                UPDATE forms
                SET name = ?,
                    slug = ?,
                    description = ?,
                    recipient_email = ?,
                    email_subject = ?,
                    success_message = ?,
                    save_submissions = ?,
                    send_email = ?,
                    use_recaptcha = ?,
                    is_active = ?,
                    sort_order = ?,
                    send_confirmation = ?,
                    confirmation_subject = ?,
                    confirmation_message = ?,
                    updated_at = NOW()
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->bind_param(
                "ssssssiiiiiissi",
                $name,
                $slug,
                $description,
                $recipient_email,
                $email_subject,
                $success_message,
                $save_submissions,
                $send_email,
                $use_recaptcha,
                $is_active,
                $sort_order,
                $send_confirmation,
                $confirmation_subject,
                $confirmation_message,
                $id
            );

            $stmt->execute();
            $stmt->close();

            logAction("Upraven formulář '$name'");

            header("Location: list.php?updated=1");
            exit;
        }
    }
}

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="admin-page-header-content mb-4">
        <div>
            <h2 class="mb-1">Upravit formulář</h2>
            <p class="text-muted mb-0"><?= e($form['name']) ?></p>
        </div>

        <div>
            <a href="fields.php?id=<?= (int)$id ?>" class="btn btn-outline-primary">
                Pole formuláře
            </a>

            <a href="list.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Zpět na formuláře
            </a>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-danger"><?= e($msg) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body">

            <form method="post">

                <div class="row">
                    <div class="col-lg-8">

                        <div class="mb-3">
                            <label class="form-label">Název formuláře *</label>
                            <input type="text"
                                   name="name"
                                   class="form-control"
                                   required
                                   value="<?= e($name) ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Slug</label>
                            <input type="text"
                                   name="slug"
                                   class="form-control"
                                   value="<?= e($slug) ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Popis</label>
                            <textarea name="description"
                                      class="form-control"
                                      rows="3"><?= e($description) ?></textarea>
                        </div>

                        <hr class="my-4">

                        <div class="mb-3">
                            <label class="form-label">E-mail příjemce</label>
                            <input type="email"
                                   name="recipient_email"
                                   class="form-control"
                                   value="<?= e($recipient_email) ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Předmět e-mailu</label>
                            <input type="text"
                                   name="email_subject"
                                   class="form-control"
                                   value="<?= e($email_subject) ?>"
                                   placeholder="Nová zpráva z formuláře">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Děkovací zpráva na webu</label>
                            <textarea name="success_message"
                                      class="form-control"
                                      rows="4"><?= e($success_message) ?></textarea>
                        </div>
                        
                        <hr class="my-4">
                        
                        <div class="mb-3">
                            <label class="form-label">Předmět potvrzovacího e-mailu odesílateli</label>

                            <input type="text"
                                name="confirmation_subject"
                                class="form-control"
                                value="<?= e($confirmation_subject) ?>"
                                placeholder="Děkujeme za vaši zprávu">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Text potvrzovacího e-mailu odesílateli</label>

                            <textarea name="confirmation_message"
                                    class="form-control"
                                    rows="6"><?= e($confirmation_message) ?></textarea>

                            <div class="form-text">
                                Potvrzení se odešle na první pole typu E-mail ve formuláři.
                            </div>
                        </div>

                    </div>

                    <div class="col-lg-4">

                        <div class="card bg-light border-0 mb-3">
                            <div class="card-body">

                                <h5 class="mb-3">Nastavení</h5>

                                <div class="mb-3">
                                    <label class="form-label">Pořadí</label>
                                    <input type="number"
                                           name="sort_order"
                                           class="form-control"
                                           value="<?= (int)$sort_order ?>">
                                </div>

                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="is_active"
                                           id="is_active"
                                           value="1"
                                           <?= $is_active ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_active">
                                        Aktivní
                                    </label>
                                </div>

                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="save_submissions"
                                           id="save_submissions"
                                           value="1"
                                           <?= $save_submissions ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="save_submissions">
                                        Ukládat odeslané zprávy do DB
                                    </label>
                                </div>

                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="send_email"
                                           id="send_email"
                                           value="1"
                                           <?= $send_email ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="send_email">
                                        Odesílat e-mail
                                    </label>
                                </div>

                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="use_recaptcha"
                                           id="use_recaptcha"
                                           value="1"
                                           <?= $use_recaptcha ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="use_recaptcha">
                                        Použít reCAPTCHA
                                    </label>
                                </div>

                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input"
                                        type="checkbox"
                                        name="send_confirmation"
                                        id="send_confirmation"
                                        value="1"
                                        <?= $send_confirmation ? 'checked' : '' ?>>

                                    <label class="form-check-label" for="send_confirmation">
                                        Poslat potvrzovací e-mail odesílateli
                                    </label>
                                </div>

                            </div>
                        </div>

                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h6 class="mb-2">Další kroky</h6>

                                <a href="fields.php?id=<?= (int)$id ?>" class="btn btn-sm btn-outline-primary w-100 mb-2">
                                    Nastavit pole
                                </a>

                                <a href="submissions.php?id=<?= (int)$id ?>" class="btn btn-sm btn-outline-info w-100">
                                    Přijaté zprávy
                                </a>
                            </div>
                        </div>

                    </div>
                </div>

                <hr class="my-4">

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Uložit změny
                </button>

                <a href="list.php" class="btn btn-outline-secondary">
                    Zrušit
                </a>

            </form>

        </div>
    </div>

</div>

<?php include "../includes/footer.php"; ?>