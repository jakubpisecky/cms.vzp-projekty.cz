<?php
$current = basename($_SERVER['PHP_SELF']);

$menuItems = [
    [
        'label' => 'Faktury',
        'icon'  => 'bi-receipt',
        'href'  => 'list.php',
        'match' => ['list.php', 'add.php', 'edit.php', 'delete.php', 'detail.php']
    ],
    [
        'label' => 'Odběratelé',
        'icon'  => 'bi-people',
        'href'  => 'customers.php',
        'match' => ['customers.php', 'customer_add.php', 'customer_edit.php', 'customer_delete.php']
    ],
];
?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($menuItems as $item): ?>
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