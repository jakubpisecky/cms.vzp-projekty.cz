<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('pages');

$msg = "";

// --- helpers ---
function makeUniqueSlug(mysqli $conn, string $baseSlug): string {
    $slug = $baseSlug !== '' ? $baseSlug : 'stranka';
    $i = 1;
    $stmt = $conn->prepare("SELECT COUNT(*) FROM pages WHERE slug=?");
    while (true) {
        $check = $slug;
        $stmt->bind_param("s", $check);
        $stmt->execute();
        $stmt->bind_result($cnt);
        $stmt->fetch();
        $stmt->free_result();
        if ((int)$cnt === 0) { $stmt->close(); return $slug; }
        $i++; $slug = $baseSlug . '-' . $i;
    }
}

function nextOrderForParent(mysqli $conn, int $parentId): int {
    $stmt = $conn->prepare("SELECT COALESCE(MAX(menu_order), 0) + 10 AS next_ord FROM pages WHERE parent_id=?");
    $stmt->bind_param("i", $parentId);
    $stmt->execute();
    $next = (int)($stmt->get_result()->fetch_assoc()['next_ord'] ?? 10);
    $stmt->close();
    return $next ?: 10;
}

function normalizeSiblings(mysqli $conn, int $parentId): void {
    // znormalizujeme 10,20,30… podle menu_order,id
    $stmt = $conn->prepare("SELECT id FROM pages WHERE parent_id=? ORDER BY menu_order ASC, id ASC FOR UPDATE");
    $stmt->bind_param("i", $parentId);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    $upd = $conn->prepare("UPDATE pages SET menu_order=? WHERE id=?");
    $order = 10;
    while ($row = $res->fetch_assoc()) {
        $id = (int)$row['id'];
        $upd->bind_param("ii", $order, $id);
        $upd->execute();
        $order += 10;
    }
    $upd->close();
}

function getPagesFlat(mysqli $conn): array {
    $res = $conn->query("SELECT id, title, parent_id FROM pages ORDER BY parent_id ASC, menu_order ASC, id ASC");
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    return $rows;
}

function buildTree(array $rows): array {
    $byId = []; $tree = [];
    foreach ($rows as $r) { $r['children'] = []; $byId[$r['id']] = $r; }
    foreach ($byId as $id => &$n) {
        if ((int)$n['parent_id'] === 0) $tree[] = &$n;
        else if (isset($byId[$n['parent_id']])) $byId[$n['parent_id']]['children'][] = &$n;
    }
    unset($n);
    return $tree;
}

function renderParentOptions(array $nodes, int $level, int $selectedId) {
    $pad = str_repeat('— ', $level);
    foreach ($nodes as $n) {
        // hlavní fix ↓ (obě strany jako int)
        $sel = ((int)$n['id'] === (int)$selectedId) ? 'selected' : '';
        echo "<option value='".(int)$n['id']."' $sel>"
            . htmlspecialchars($pad.$n['title'])
            . "</option>";
        if (!empty($n['children'])) {
            renderParentOptions($n['children'], $level+1, $selectedId);
        }
    }
}


// --- defaults pro GET ---
$parent_id_default = intval($_GET['parent_id'] ?? 0);
$prefill_order = nextOrderForParent($conn, $parent_id_default);

// --- POST ---// --- POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title        = trim($_POST['title'] ?? '');
    $slugInput    = trim($_POST['slug'] ?? '');
    $slug         = $slugInput !== '' ? slugify($slugInput) : slugify($title);
    $status       = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $parent_id    = intval($_POST['parent_id'] ?? 0);
    $menu_order   = trim($_POST['menu_order'] ?? '');
    $show_in_menu = isset($_POST['show_in_menu']) ? 1 : 0;
    $content      = $_POST['content'] ?? '';

    // NOVÉ POLOŽKY
    $meta_title       = trim($_POST['meta_title'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');
    $template         = $_POST['template'] ?? 'page';
    if (function_exists('normalizePageTemplate') && isset($PAGE_TEMPLATES)) {
        $template = normalizePageTemplate($template, $PAGE_TEMPLATES);
    }

    if ($title === '') {
        $msg = "Titulek je povinný.";
    } else {
        // zajisti slug
        if ($slug === '') $slug = 'stranka';
        $slug = makeUniqueSlug($conn, $slug);

        // dopočítej pořadí, pokud není validní číslo
        if ($menu_order === '' || !is_numeric($menu_order)) {
            $menu_order = nextOrderForParent($conn, $parent_id);
        } else {
            $menu_order = intval($menu_order);
        }

        // uložení + normalizace v transakci
        $conn->begin_transaction();
        try {
            // UPRAVENÝ INSERT – přidány meta_title, meta_description, template
            $stmt = $conn->prepare(
                "INSERT INTO pages
                 (title, slug, parent_id, status, menu_order, show_in_menu, content, meta_title, meta_description, template, created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,NOW())"
            );
            // typy: s s i s i i s s s s
            $stmt->bind_param(
                "ssisiissss",
                $title, $slug, $parent_id, $status, $menu_order, $show_in_menu,
                $content, $meta_title, $meta_description, $template
            );
            $stmt->execute();
            $stmt->close();

            // reindex sourozenců (řeší duplicity menu_order)
            normalizeSiblings($conn, $parent_id);

            $conn->commit();
            header("Location: list.php?created=1");
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            $msg = "Chyba při ukládání: " . htmlspecialchars($e->getMessage());
        }
    }

    // pro znovuzobrazení formuláře po chybě
    $prefill_order = nextOrderForParent($conn, $parent_id);
    if (is_numeric($_POST['menu_order'] ?? '')) $prefill_order = (int)$_POST['menu_order'];
    $parent_id_default = $parent_id;
}


// data pro select rodiče
$tree = buildTree(getPagesFlat($conn));

include "../includes/header.php";
?>
<div class="container py-4">
  <h2 class="mb-4">Přidat stránku</h2>

  <?php if (!empty($msg)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <form method="post" class="bg-white p-4 rounded shadow-sm">

  <!-- Titulek -->
  <div class="row mb-3">
    <div class="col-md-8">
      <label class="form-label">Titulek</label>
      <input type="text" name="title" class="form-control" required
             value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
    </div>
  </div>

  <!-- Nadřazená stránka -->
  <div class="row mb-3">
    <div class="col-md-8">
      <label class="form-label">Nadřazená stránka</label>
      <select name="parent_id" id="parent_id" class="form-select">
        <option value="0" <?= $parent_id_default===0?'selected':'' ?>>— První úroveň —</option>
        <?php renderParentOptions($tree, 0, $parent_id_default); ?>
      </select>
    </div>
  </div>

  <!-- URL (slug) -->
  <div class="row mb-3">
    <div class="col-md-8">
      <label class="form-label">URL (slug)</label>
      <input type="text" name="slug" class="form-control"
             placeholder="prázdné = vygeneruje se z titulku"
             value="<?= htmlspecialchars($_POST['slug'] ?? '') ?>">
    </div>
  </div>

  <!-- Stav -->
  <div class="row mb-3">
    <div class="col-md-8">
      <?php $st = $_POST['status'] ?? 'draft'; ?>
      <label class="form-label">Stav</label>
      <select name="status" class="form-select">
        <option value="draft" <?= $st==='published'?'':'selected' ?>>Koncept</option>
        <option value="published" <?= $st==='published'?'selected':'' ?>>Publikováno</option>
      </select>
    </div>
  </div>

  <!-- Pořadí v menu -->
  <div class="row mb-3">
    <div class="col-md-8">
      <label class="form-label">Pořadí v menu</label>
      <input type="number" name="menu_order" id="menu_order" class="form-control"
             value="<?= (int)$prefill_order ?>">
    </div>
  </div>

  <!-- Zobrazit v menu (switch) -->
  <div class="row mb-3">
    <div class="col-md-8">
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="show_in_menu" id="show_in_menu"
               <?= isset($_POST['show_in_menu']) ? 'checked' : 'checked' ?>>
        <label class="form-check-label" for="show_in_menu">Zobrazit v menu</label>
      </div>
    </div>
  </div>

  <!-- Obsah -->
  <div class="row mb-4">
    <div class="col-md-12">
      <label class="form-label">Obsah</label>
      <textarea name="content" class="form-control editor" rows="12"><?= htmlspecialchars($_POST['content'] ?? '') ?></textarea>
    </div>
  </div>

    <!-- SEO titulek -->
  <div class="row mb-3">
    <div class="col-md-8">
      <label class="form-label">SEO titulek</label>
      <input type="text" name="meta_title" class="form-control"
             value="<?= htmlspecialchars($_POST['meta_title'] ?? '') ?>">
    </div>
  </div>

  <!-- SEO popis -->
  <div class="row mb-4">
    <div class="col-md-8">
      <label class="form-label">SEO popis</label>
      <input type="text" name="meta_description" class="form-control"
             value="<?= htmlspecialchars($_POST['meta_description'] ?? '') ?>">
    </div>
  </div>

  <!-- Šablona / typ stránky -->
  <div class="row mb-4">
    <div class="col-md-8">
      <label class="form-label">Šablona</label>
      <select name="template" class="form-select">
        <?php
        $currentTpl = $_POST['template'] ?? 'page';
        if (function_exists('normalizePageTemplate') && isset($PAGE_TEMPLATES)) {
            $currentTpl = normalizePageTemplate($currentTpl, $PAGE_TEMPLATES);
            foreach ($PAGE_TEMPLATES as $value => $label):
        ?>
              <option value="<?= htmlspecialchars($value) ?>" <?= $currentTpl === $value ? 'selected' : '' ?>>
                <?= htmlspecialchars($label) ?>
              </option>
        <?php
            endforeach;
        } else {
            // fallback, kdyby nebyla definice šablon
            ?>
            <option value="page" <?= $currentTpl === 'page' ? 'selected' : '' ?>>Obecná stránka</option>
            <?php
        }
        ?>
      </select>
    </div>
  </div>


  <div class="d-flex justify-content-between">
    <button class="btn btn-success">
      <i class="bi bi-save me-1"></i> Uložit
    </button>
    <a href="list.php" class="btn btn-secondary">
      <i class="bi bi-arrow-left me-1"></i> Zpět
    </a>
  </div>
</form>


</div>

<script>
// při změně nadřazené stránky dopočti doporučené pořadí
document.getElementById('parent_id')?.addEventListener('change', async function() {
  const pid = this.value || 0;
  try {
    const r = await fetch('next_order.php?parent_id=' + encodeURIComponent(pid));
    const j = await r.json();
    if (j.ok && typeof j.next === 'number') {
      document.getElementById('menu_order').value = j.next;
    }
  } catch(e) { /* nic */ }
});
</script>

<?php include "../includes/footer.php"; ?>
