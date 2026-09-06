<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/mailing.php";


requirePermission('mailing');

include "../includes/header.php";

$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// --- WHERE ---
$where = [];
$params = [];
$types = '';

if ($q !== '') {
    $where[] = "(title LIKE ? OR subject LIKE ?)";
    $like = "%" . $q . "%";
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

// --- COUNT ---
$sqlCount = "SELECT COUNT(*) AS total FROM mailing_campaigns $whereSql";
$stmt = $conn->prepare($sqlCount);

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$total = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

// --- DATA ---
$sql = "
    SELECT *
    FROM mailing_campaigns
    $whereSql
    ORDER BY created_at DESC, id DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);

$bindParams = $params;
$bindTypes  = $types . 'ii';

$bindParams[] = $perPage;
$bindParams[] = $offset;

$stmt->bind_param($bindTypes, ...$bindParams);
$stmt->execute();
$result = $stmt->get_result();

$totalPages = max(1, (int)ceil($total / $perPage));

?>

<div class="container-fluid py-4">

    <div class="admin-page-header-content mb-4">
        <div>
            <h2 class="mb-1">Mailing <small class="text-muted">(<?= $total ?>)</small></h1>
            <p class="text-muted mb-0">Přehled kampaní</p>
        </div>
        <div class="responsive-actions">
            <a href="add.php" class="btn btn-success">
                <i class="bi bi-plus-lg"></i> Nová kampaň
            </a>
        </div>
    </div>
    <?php include "_menu.php"; ?>
    <?php if (isset($_GET['created'])): ?>
        <div class="alert alert-success">Kampaň byla vytvořena.</div>
    <?php endif; ?>
    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success">Kampaň byla upravena.</div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">Kampaň byla smazána.</div>
    <?php endif; ?>
    <?php if (isset($_GET['stopped'])): ?>
    <div class="alert alert-success">Kampaň byla zastavena.</div>
    <?php endif; ?>
    <?php if (isset($_GET['played'])): ?>
        <div class="alert alert-success">Kampaň byla znovu spuštěna.</div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-8">
                    <input type="text" name="q" class="form-control"
                           placeholder="Hledat podle názvu nebo předmětu..."
                           value="<?php echo htmlspecialchars($q); ?>">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Hledat
                    </button>
                    <a href="list.php" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>



            <?php if ($result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover bg-white">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Název</th>
                                <th>Předmět</th>
                                <th>Stav</th>
                                <th>Statistiky</th>
                                <th>Vytvořeno</th>
                                <th class="text-center">Akce</th>
                            </tr>
                        </thead>
                        <tbody>

                            <?php while ($row = $result->fetch_assoc()): 
                                $stats = mailingCampaignStats((int)$row['id']);
                                ?>
                                <tr>
                                    <td><?php echo (int)$row['id']; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($row['title']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['subject']); ?></td>
                                    <td id="status-<?php echo $row['id']; ?>">
                                        <?php echo mailingStatusBadge($row['status']); ?>
                                    </td>
                                    <td id="stats-<?php echo (int)$row['id']; ?>">
                                            <small class="text-muted"><?php echo (int)$stats['sent']; ?> / <?php echo (int)$stats['total_recipients']; ?></small>
                                    </td>
                                    <td><?php echo formatDateTimeCz(htmlspecialchars($row['created_at'])); ?></td>

                                    <td class="text-center" id="actions-<?php echo $row['id']; ?>">
                                        

                                        <?php if (($row['status'] ?? '') === 'draft'): ?>
                                            <a href="queue_campaign.php?id=<?php echo (int)$row['id']; ?>"
                                            class="btn btn-sm btn-outline-success"
                                            onclick="return confirm('Opravdu zařadit kampaň do fronty?');"
                                            title="Zařadit do fronty">
                                                <i class="bi bi-send"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (in_array(($row['status'] ?? ''), ['queued', 'sending'], true)): ?>
                                            <a href="stop.php?id=<?php echo (int)$row['id']; ?>"
                                            class="btn btn-sm btn-outline-warning"
                                            onclick="return confirm('Opravdu zastavit tuto kampaň? Zbývající e-maily zůstanou čekat ve frontě.');"
                                            title="Zastavit kampaň">
                                                <i class="bi bi-pause-fill"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (($row['status'] ?? '') === 'stopped'): ?>
                                            <a href="play.php?id=<?php echo (int)$row['id']; ?>"
                                            class="btn btn-sm btn-outline-success"
                                            onclick="return confirm('Opravdu znovu spustit tuto kampaň?');"
                                            title="Pokračovat v odesílání">
                                                <i class="bi bi-play-fill"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="send_test.php?id=<?php echo (int)$row['id']; ?>"
                                        class="btn btn-sm btn-outline-info"
                                        title="Testovací odeslání">
                                            <i class="bi bi-envelope-check"></i>
                                        </a>

                                        <a href="preview.php?id=<?php echo (int)$row['id']; ?>"
                                        class="btn btn-sm btn-outline-secondary"
                                        title="Náhled e-mailu"
                                        target="_blank">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="detail.php?id=<?php echo (int)$row['id']; ?>"
                                        class="btn btn-sm btn-outline-dark"
                                        title="Detail kampaně">
                                            <i class="bi bi-bar-chart"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo (int)$row['id']; ?>"
                                        class="btn btn-sm btn-primary"
                                        title="Upravit">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <a href="delete.php?id=<?php echo (int)$row['id']; ?>"
                                        class="btn btn-sm btn-danger"
                                        onclick="return confirm('Opravdu smazat tuto kampaň?');"
                                        title="Smazat">
                                            <i class="bi bi-trash"></i>
                                        </a>

                                    
                                    </td>
                                </tr>
                            <?php endwhile; ?>

                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-4">
                    <p class="text-muted mb-0">Žádné kampaně nebyly nalezeny.</p>
                </div>
            <?php endif; ?>

   
    <?php if ($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link"
                           href="?q=<?php echo urlencode($q); ?>&page=<?php echo $i; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>

</div>

<?php
$stmt->close();
?><script>
function refreshCampaigns() {
    fetch('ajax_campaign_status.php')
        .then(res => res.json())
        .then(data => {
            Object.keys(data).forEach(id => {
                const row = data[id];

                const statusEl = document.getElementById('status-' + id);
                const actionsEl = document.getElementById('actions-' + id);
                const statsEl = document.getElementById('stats-' + id);

                if (statusEl) {
                    statusEl.innerHTML = row.badge;
                }

                if (actionsEl) {
                    actionsEl.innerHTML = row.actions;
                }

                if (statsEl) {
                    statsEl.innerHTML = row.stats_html;
                }
            });
        })
        .catch(err => console.error('Campaign refresh error:', err));
}

setInterval(refreshCampaigns, 10000);
</script>
<?php
include "../includes/footer.php";