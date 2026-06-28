<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";

requirePermission('mailing');

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM mailing_recipients WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) die("Nenalezeno");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $first = $_POST['first_name'];
    $last  = $_POST['last_name'];
    $company = $_POST['company'];

    $stmt = $conn->prepare("
        UPDATE mailing_recipients
        SET email=?, first_name=?, last_name=?, company=?
        WHERE id=?
    ");
    $stmt->bind_param("ssssi", $email, $first, $last, $company, $id);
    $stmt->execute();

    header("Location: recipients.php");
    exit;
}

include "../includes/header.php";
?>

<div class="container-fluid py-4">
<div class="row">
<div class="col-lg-12">
<h2>Editace</h2>

<form method="post">

<input type="email" name="email" value="<?= $data['email'] ?>" class="form-control mb-2">
<input type="text" name="first_name" value="<?= $data['first_name'] ?>" class="form-control mb-2">
<input type="text" name="last_name" value="<?= $data['last_name'] ?>" class="form-control mb-2">
<input type="text" name="company" value="<?= $data['company'] ?>" class="form-control mb-3">

<button class="btn btn-primary">Uložit</button>
<a href="recipients.php" class="btn btn-secondary">Zpět</a>
</form>

</div>
</div>
</div>

<?php include "../includes/footer.php"; ?>