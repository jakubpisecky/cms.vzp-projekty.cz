<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('menus');

include "../includes/header.php";

// --- filtry ---
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 14;
$from    = trim($_GET['from'] ?? '');
$to      = trim($_GET['to'] ?? '');

$where  = [];
$params = [];
$types  = '';

if ($from !== '') {
    $where[] = "menu_date >= ?";
    $params[] = $from;
    $types .= 's';
}

if ($to !== '') {
    $where[] = "menu_date <= ?";
    $params[] = $to;
    $types .= 's';
}

$whereSql = $where ? " WHERE " . implode(" AND ", $where) : "";

// počet
$sqlCount = "
    SELECT COUNT(*) AS c
    FROM menu_days
    {$whereSql}
";

$stmt = $conn->prepare($sqlCount);

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$total = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$totalPages = max(1, (int)ceil($total / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

$paramsUrl = $_GET;
unset($paramsUrl['page']);
$base = http_build_query($paramsUrl);
$base = $base ? ('?' . $base . '&') : '?';

// data
$sql = "
    SELECT id, menu_date, status, note
    FROM menu_days
    {$whereSql}
    ORDER BY menu_date DESC
    LIMIT ?, ?
";

$stmt = $conn->prepare($sql);

$typesData = $types . 'ii';
$paramsData = $params;
$paramsData[] = $offset;
$paramsData[] = $perPage;

$stmt->bind_param($typesData, ...$paramsData);
$stmt->execute();
$res = $stmt->get_result();
$stmt->close();
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="mb-1">
                Jídelníčky <small class="text-muted">(<?= (int)$total ?>)</small>
            </h2>
            <p class="text-muted mb-0">Správa jídelníčků a PDF exportů</p>
        </div>

        <div class="responsive-actions">
            <button type="button"
                    class="btn btn-outline-secondary"
                    id="btnExportSelectedDays">
                <i class="bi bi-filetype-pdf me-1"></i>
                PDF (vybrané dny)
            </button>

            <a href="add_day.php" class="btn btn-success">
                <i class="bi bi-plus-circle me-1"></i>
                Nový den
            </a>

            <a href="add_day.php?mode=week" class="btn btn-outline-success">
                <i class="bi bi-calendar-week me-1"></i>
                Vytvořit týden
            </a>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form class="row g-3" method="get">

                <div class="col-lg-3 col-md-6">
                    <label class="form-label">Od</label>
                    <input type="date"
                           class="form-control"
                           name="from"
                           value="<?= e($from) ?>">
                </div>

                <div class="col-lg-3 col-md-6">
                    <label class="form-label">Do</label>
                    <input type="date"
                           class="form-control"
                           name="to"
                           value="<?= e($to) ?>">
                </div>

                <div class="col-auto d-flex align-items-end">
                    <button class="btn btn-primary">
                        <i class="bi bi-search me-1"></i>
                        Filtrovat
                    </button>
                </div>

                <?php if ($from !== '' || $to !== ''): ?>
                    <div class="col-auto d-flex align-items-end">
                        <a href="list.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-1"></i>
                            Zrušit
                        </a>
                    </div>
                <?php endif; ?>

            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover align-middle mb-0 bg-white">
                <thead class="table-light">
                    <tr>
                        <th width="50" class="text-center">
                            <input type="checkbox"
                                   class="form-check-input"
                                   id="checkAllDays"
                                   title="Vybrat vše na této stránce">
                        </th>
                        <th width="140">Datum</th>
                        <th width="120" class="text-center">Stav</th>
                        <th width="420" class="text-center">Akce</th>
                    </tr>
                </thead>

                <tbody>
                    <?php while ($d = $res->fetch_assoc()): ?>
                        <?php
                            $id = (int)$d['id'];

                            $badge = ($d['status'] ?? '') === 'published'
                                ? '<span class="badge bg-success">Publikováno</span>'
                                : '<span class="badge bg-secondary">Koncept</span>';

                            $pdfDayHref = "../export/menu_pdf.php?id=" . $id;

                            [$wStart, $wEnd] = week_range($d['menu_date']);
                            $pdfWeekHref = "../export/menu_pdf.php?week_of=" . urlencode($wStart);
                        ?>

                        <tr>
                            <td class="text-center">
                                <input type="checkbox"
                                       class="form-check-input day-select"
                                       value="<?= $id ?>">
                            </td>

                            <td class="text-nowrap">
                                <strong><?= e(date('j. n. Y', strtotime($d['menu_date']))) ?></strong>
                            </td>

                            <td class="text-center">
                                <?= $badge ?>
                            </td>


                            <td class="text-center text-nowrap">
                                <div class="d-flex flex-wrap justify-content-center gap-1">

                                    <a href="duplicate.php?id=<?= $id ?>"
                                       class="btn btn-sm btn-outline-primary"
                                       title="Duplikovat">
                                        <i class="bi bi-files"></i>
                                        Duplikovat
                                    </a>

                                    <a href="<?= e($pdfDayHref) ?>"
                                       class="btn btn-sm btn-outline-secondary"
                                       target="_blank"
                                       title="PDF pro den">
                                        <i class="bi bi-filetype-pdf"></i>
                                        Den
                                    </a>

                                    <a href="<?= e($pdfWeekHref) ?>"
                                       class="btn btn-sm btn-outline-secondary"
                                       target="_blank"
                                       title="PDF pro týden">
                                        <i class="bi bi-calendar-week"></i>
                                        Týden
                                    </a>

                                    <a href="../emails/menu_send.php?id=<?= $id ?>"
                                       class="btn btn-sm btn-outline-secondary"
                                       title="Odeslat e-mailem">
                                        <i class="bi bi-envelope"></i>
                                        E-mail
                                    </a>

                                    <?php if (($d['status'] ?? '') === 'published'): ?>
                                        <a href="status.php?id=<?= $id ?>&to=draft"
                                           class="btn btn-sm btn-outline-secondary"
                                           title="Přepnout na koncept">
                                            <i class="bi bi-eye-slash"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="status.php?id=<?= $id ?>&to=published"
                                           class="btn btn-sm btn-success"
                                           title="Publikovat">
                                            <i class="bi bi-check2-circle"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="edit.php?id=<?= $id ?>"
                                       class="btn btn-sm btn-primary"
                                       title="Upravit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="delete.php?id=<?= $id ?>"
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Smazat jídelníček pro tento den?');"
                                       title="Smazat">
                                        <i class="bi bi-trash"></i>
                                    </a>

                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                    <?php if ($res->num_rows === 0): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted p-4">
                                Žádné jídelníčky nebyly nalezeny.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (function_exists('renderPagination')): ?>
        <?php renderPagination($totalPages, $page, $base); ?>
    <?php elseif ($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $base ?>page=<?= $i ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>

</div>

<form method="post"
      action="../export/menu_pdf.php"
      target="_blank"
      id="exportDaysForm"
      class="d-none">
    <input type="hidden" name="mode" value="days">
    <div id="exportDaysContainer"></div>
</form>

<script>
document.getElementById('checkAllDays')?.addEventListener('change', function () {
    document.querySelectorAll('.day-select').forEach(cb => {
        cb.checked = this.checked;
    });
});

function exportSelectedDays() {
    const checked = Array.from(document.querySelectorAll('.day-select:checked'));

    if (checked.length === 0) {
        alert('Vyber alespoň jeden den.');
        return;
    }

    const form = document.getElementById('exportDaysForm');
    const container = document.getElementById('exportDaysContainer');

    container.innerHTML = '';

    checked.forEach(cb => {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'day_id[]';
        inp.value = cb.value;
        container.appendChild(inp);
    });

    form.submit();
}

document.getElementById('btnExportSelectedDays')?.addEventListener('click', exportSelectedDays);
</script>

<?php include "../includes/footer.php"; ?>