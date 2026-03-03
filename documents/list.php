<?php 
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('documents');

$picker = $_GET['picker'] ?? ''; // '', 'tinymce'
$totalDocuments = (int)$conn->query("SELECT COUNT(*) FROM documents")->fetch_row()[0];
$result = $conn->query("SELECT * FROM documents ORDER BY uploaded_at DESC");

if (!$picker) {
    include "../includes/header.php";
} else {
    // Minimal layout pro picker v dialogu TinyMCE
    ?>
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="utf-8">
        <title>Knihovna dokumentů</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="/assets/css/admin.css">
        <script>
function selectDocument(url, text){
  if (window.parent && typeof window.parent.tinymceActiveCallback === 'function') {
    // ponecháme uživatelem označený text, jinak použijeme název dokumentu
    let selected = '';
    try {
      selected = window.parent.tinymce?.activeEditor?.selection?.getContent({ format: 'text' }) || '';
    } catch(e){}

    const linkText = selected || (text || '');

    // vyplní URL, Text odkazu i Title (tooltip) v dialogu
    window.parent.tinymceActiveCallback(url, {
      text:  linkText,
      title: text || linkText
    });

    // zavřít modal
    if (window.parent._editorImageModal) {
      window.parent._editorImageModal.hide();
    }
  } else {
    alert('Nepodařilo se předat dokument do editoru.');
  }
}
</script>

    </head>
    <body class="bg-light p-3">
    <?php
}
?>

<div class="container py-4">
    <?php if (!$picker): ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Dokumenty <small class="text-muted">(<?= $totalDocuments ?>)</small></h2>
            <a href="add.php" class="btn btn-success">
                <i class="bi bi-upload me-1"></i> Nahrát
            </a>
        </div>
    <?php else: ?>
        <h4 class="mb-3">Vyberte dokument</h4>
    <?php endif; ?>
        <?php if (isset($_GET['created'])): ?>
        <div class="alert alert-success">Dokument byl uložen.</div>
      <?php endif; ?>
      <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">Dokument byl smazán.</div>
      <?php endif; ?>
    <div class="table-responsive">
        <table class="table table-bordered table-striped table-hover bg-white">
            <thead class="table-light">
                <tr>
                    <th width="60">ID</th>
                    <th>Název</th>
                    <th>Soubor</th>
                    <th>Datum nahrání</th>
                    <th width="120" class="text-center">Akce</th>
                </tr>
            </thead>
            <tbody>
                <?php while($doc = $result->fetch_assoc()): 
                    $title = htmlspecialchars($doc['title']);
                    $filename = htmlspecialchars($doc['filename']);
                    $url = "/uploads/documents/" . rawurlencode($doc['filename']);
                ?>
                    <tr>
                        <td><?= $doc['id'] ?></td>
                        <td><?= $title ?></td>
                        <td>
                            <a href="<?= $url ?>" target="_blank" rel="noopener">
                                <?= $filename ?>
                            </a>
                        </td>
                        <td><?= formatDateCz($doc['uploaded_at']) ?></td>
                        <td class="text-center text-nowrap">
                            <?php if ($picker): ?>
                                <button
                                    class="btn btn-sm btn-primary"
                                    onclick="selectDocument('<?= $url ?>','<?= htmlspecialchars($doc['title'], ENT_QUOTES) ?>')">
                                    Vybrat
                                </button>
                            <?php else: ?>
                                <a href="delete.php?id=<?= $doc['id'] ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Opravdu smazat dokument?')">
                                   <i class="bi bi-trash"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!$picker) include "../includes/footer.php"; ?>
<?php if ($picker): ?>
</body></html>
<?php endif; ?>
