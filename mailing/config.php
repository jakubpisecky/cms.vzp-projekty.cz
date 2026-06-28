<?php

return [
    'title' => 'Mailing',
    'description' => 'Hromadná rozesílka',
    'icon' => 'bi bi-send',
    'url' => '/mailing/list.php',
    'permission' => 'mailing',
    'group' => 'Komunikace',
    'order' => 130,
    'show_in_menu' => true,
    'show_on_dashboard' => true,
    'count_query' => 'SELECT COUNT(*) FROM mailing_campaigns',
];