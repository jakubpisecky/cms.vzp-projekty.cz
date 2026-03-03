<?php
// includes/menu.php — FLAT MENU (DB ONLY)

/** Aktivita podle URL (jednoduchá kontrola podřetězce) */
if (!function_exists('adminIsActivePath')) {
    function adminIsActivePath(string $url): bool {
        if ($url === '#' || $url === '') return false;
        $req = $_SERVER['REQUEST_URI'] ?? '';
        return strpos($req, $url) !== false;
    }
}

/**
 * Načte TOP-LEVEL položky menu z DB.
 * Bereme jen parent_id IS NULL a is_heading=0 (nebo NULL).
 * Očekává tabulku `admin_menu` se sloupci: id,parent_id,label,url,icon,perm,sort,is_heading.
 * Když tabulka neexistuje nebo není nic, vrátí prázdné pole (žádný fallback, ať je chyba vidět).
 */
function getAdminMenuFlatFromDb(mysqli $conn): array {
    // zapni si chybová hlášení z mysqli
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // ověř existenci tabulky
    $res = $conn->query("SHOW TABLES LIKE 'admin_menu'");
    if ($res->num_rows === 0) {
        // Diagnostická hláška – uvidíš rovnou v UI (dočasně; můžeš smazat)
        echo '<div class="alert alert-danger m-3">Tabulka <code>admin_menu</code> neexistuje.</div>';
        return [];
    }

    $sql = "SELECT label,url,icon,perm
            FROM admin_menu
            WHERE parent_id IS NULL AND COALESCE(is_heading,0)=0
            ORDER BY sort, id";
    $q = $conn->query($sql);

    $items = [];
    while ($r = $q->fetch_assoc()) {
        $items[] = [
            'label' => (string)($r['label'] ?? ''),
            'url'   => (string)($r['url'] ?? '#'),
            'icon'  => (string)($r['icon'] ?? 'bi-dot'),
            'perm'  => $r['perm'] ?? null,
        ];
    }

    if (!$items) {
        // Diagnostická hláška – uvidíš rovnou v UI (dočasně; můžeš smazat)
        echo '<div class="alert alert-warning m-3">V tabulce <code>admin_menu</code> nejsou žádné top-level položky.</div>';
    }

    return $items;
}

/** Renderer JEDNÉ ÚROVNĚ (sidebar i offcanvas) */
function renderAdminMenuFlat(array $items, array $opts = []): void {
    $dismiss = !empty($opts['dismissOffcanvas']);
    echo '<ul class="nav nav-pills flex-column nav-aside gap-1">';
    foreach ($items as $it) {
        if (!empty($it['perm']) && function_exists('hasPermission') && !hasPermission($it['perm'])) continue;

        $url   = $it['url']   ?? '#';
        $icon  = $it['icon']  ?? 'bi-dot';
        $label = $it['label'] ?? '';
        $active = adminIsActivePath($url) ? 'active' : '';

        echo '<li class="nav-item">';
        echo '<a class="nav-link '.$active.'" href="'.htmlspecialchars($url).'"'.
             ($dismiss ? ' data-bs-dismiss="offcanvas"' : '').'>';
        echo '<i class="bi '.htmlspecialchars($icon).' me-2"></i>'.htmlspecialchars($label).'</a>';
        echo '</li>';
    }
    echo '</ul>';
}
