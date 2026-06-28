<?php

return [
    'title' => 'Fakturace',
    'description' => 'Správa faktur a zákazníků',
    'icon' => 'bi bi-receipt',
    'url' => '/invoices/list.php',
    'permission' => 'invoices',
    'group' => 'Fakturace',
    'order' => 150,
    'show_in_menu' => true,
    'show_on_dashboard' => true,
    'count_query' => 'SELECT COUNT(*) FROM invoices',
];