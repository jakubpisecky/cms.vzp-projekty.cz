<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('documents');

$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    $file = $_FILES['document'];
    $title = trim($_POST['title'] ?? '');

    if ($file['error'] === 0) {
        if ($title === '') {
            $msg = "Popisek dokumentu je povinný.";
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf','doc','docx','xls','xlsx','ppt','pptx','zip','rar'];

            if (in_array($ext, $allowed)) {
                $slug = slugify($title);
                $baseName = $slug;
                $newName = $slug . '.' . $ext;
                $uploadDir = "../uploads/documents/";

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                // Kontrola duplicity
                $i = 1;
                while (file_exists($uploadDir . $newName)) {
                    $newName = $baseName . '-' . $i . '.' . $ext;
                    $i++;
                }

                if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
                    $stmt = $conn->prepare("INSERT INTO documents (filename, title, uploaded_by, uploaded_at) VALUES (?,?,?,NOW())");
                    $stmt->bind_param("ssi", $newName, $title, $_SESSION['admin_id']);
                    $stmt->execute();

                    header("Location: list.php?created=1");
                    exit;
                } else {
                    $msg = "Chyba při ukládání souboru na server.";
                }
            } else {
                $msg = "Nepovolený typ souboru. Povolené jsou PDF, DOC, XLS, PPT, ZIP, RAR.";
            }
        }
    } else {
        $msg = "Nebyl vybrán žádný soubor, nebo došlo k chybě při nahrávání.";
    }
}

include "../includes/header.php";
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <h2 class="mb-4">Nahrát</h2>

            <?php if(!empty($msg)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="bg-white p-4 rounded shadow-sm">
                <div class="mb-3">
                    <label class="form-label">Soubor dokumentu</label>
                    <input type="file" name="document" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Popisek dokumentu (povinný)</label>
                    <input type="text" name="title" class="form-control" required>
                </div>

                
            <div class="d-flex justify-content-between">
                <button class="btn btn-success">
                    <i class="bi bi-upload me-1"></i> Nahrát
                </button>
                <a href="list.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Zpět
                </a>
            </div>
            </form>
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
