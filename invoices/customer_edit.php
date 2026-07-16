<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('invoices');

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die("Neplatné ID odběratele.");
}

$stmt = $conn->prepare("SELECT * FROM invoice_customers WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();
$stmt->close();

if (!$customer) {
    die("Odběratel nebyl nalezen.");
}

$name    = $customer['name'] ?? '';
$ico     = $customer['ico'] ?? '';
$dic     = $customer['dic'] ?? '';
$street  = $customer['street'] ?? '';
$city    = $customer['city'] ?? '';
$zip     = $customer['zip'] ?? '';
$country = $customer['country'] ?? 'Česká republika';
$email   = $customer['email'] ?? '';
$phone   = $customer['phone'] ?? '';
$note    = $customer['note'] ?? '';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $ico     = trim($_POST['ico'] ?? '');
    $dic     = trim($_POST['dic'] ?? '');
    $street  = trim($_POST['street'] ?? '');
    $city    = trim($_POST['city'] ?? '');
    $zip     = trim($_POST['zip'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $note    = trim($_POST['note'] ?? '');

    if ($name === '') {
        $error = "Název odběratele je povinný.";
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "E-mail není ve správném formátu.";
    } else {
        $stmt = $conn->prepare("
            UPDATE invoice_customers
            SET name = ?, ico = ?, dic = ?, street = ?, city = ?, zip = ?, country = ?, email = ?, phone = ?, note = ?
            WHERE id = ?
        ");

        if (!$stmt) {
            $error = "Nepodařilo se připravit SQL dotaz: " . $conn->error;
        } else {
            $stmt->bind_param(
                "ssssssssssi",
                $name,
                $ico,
                $dic,
                $street,
                $city,
                $zip,
                $country,
                $email,
                $phone,
                $note,
                $id
            );

            if ($stmt->execute()) {
                $stmt->close();

                if (function_exists('logAction')) {
                    logAction("Upraven odběratel ID {$id}: {$name}");
                }

                header("Location: customers.php?updated=1");
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

            <div class="admin-page-header-content mb-4">
                <div>
                    <h2 class="mb-1">Upravit odběratele</h1>
                    <p class="text-muted mb-0"><?= htmlspecialchars($customer['name']) ?></p>
                </div>
                
                    <a href="customers.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Zpět
                    </a>
                
            </div>

            <?php include "_menu.php"; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <form method="post">

                        <div class="mb-3">
                            <label for="name" class="form-label">Název / jméno <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control" value="<?= htmlspecialchars($name) ?>" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="ico" class="form-label">IČO</label>
                                <input type="text" name="ico" id="ico" class="form-control" value="<?= htmlspecialchars($ico) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="dic" class="form-label">DIČ</label>
                                <input type="text" name="dic" id="dic" class="form-control" value="<?= htmlspecialchars($dic) ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="street" class="form-label">Ulice a číslo</label>
                            <input type="text" name="street" id="street" class="form-control" value="<?= htmlspecialchars($street) ?>">
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="city" class="form-label">Město</label>
                                <input type="text" name="city" id="city" class="form-control" value="<?= htmlspecialchars($city) ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="zip" class="form-label">PSČ</label>
                                <input type="text" name="zip" id="zip" class="form-control" value="<?= htmlspecialchars($zip) ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="country" class="form-label">Země</label>
                                <input type="text" name="country" id="country" class="form-control" value="<?= htmlspecialchars($country) ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">E-mail</label>
                                <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($email) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Telefon</label>
                                <input type="text" name="phone" id="phone" class="form-control" value="<?= htmlspecialchars($phone) ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="note" class="form-label">Poznámka</label>
                            <textarea name="note" id="note" rows="4" class="form-control"><?= htmlspecialchars($note) ?></textarea>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="customers.php" class="btn btn-outline-secondary">Zrušit</a>
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

<?php include "../includes/footer.php"; ?>