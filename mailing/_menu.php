<?php
$current = basename($_SERVER['PHP_SELF']);

$items = [
    [
        'label' => 'Kampaně',
        'icon'  => 'bi-envelope',
        'href'  => 'list.php',
        'match' => ['list.php', 'add.php', 'edit.php', 'detail.php', 'delete.php', 'preview.php', 'queue.php', 'send_test.php', 'stop.php']
    ],
    [
        'label' => 'Příjemci',
        'icon'  => 'bi-people',
        'href'  => 'recipients.php',
        'match' => ['recipients.php', 'recipient_add.php', 'recipient_edit.php', 'recipient_delete.php']
    ],
    [
        'label' => 'Fronta',
        'icon'  => 'bi-list-check',
        'href'  => 'queue_list.php',
        'match' => ['queue_list.php']
    ],
    [
        'label' => 'Logy',
        'icon'  => 'bi-clock-history',
        'href'  => 'logs.php',
        'match' => ['logs.php']
    ],
    [
        'label' => 'Šablony',
        'icon'  => 'bi-layout-text-window',
        'href'  => 'templates.php',
        'match' => ['templates.php', 'template_add.php', 'template_edit.php', 'template_delete.php']
    ],
];
?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($items as $item): ?>
                <?php $active = in_array($current, $item['match'], true); ?>
                <a href="<?php echo $item['href']; ?>"
                   class="btn <?php echo $active ? 'btn-primary' : 'btn-outline-secondary'; ?> btn-sm">
                    <i class="bi <?php echo $item['icon']; ?>"></i>
                    <?php echo htmlspecialchars($item['label']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>