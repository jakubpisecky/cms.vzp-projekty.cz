<?php

return [
    'title' => 'Přesměrování',
    'description' => 'Správa redirectů',
    'icon' => 'bi bi-arrow-left-right',
    'url' => '/redirects/list.php',
    'permission' => 'redirects',
    'group' => 'SEO',
    'order' => 110,
    'show_in_menu' => true,
    'show_on_dashboard' => true,
    'count_query' => 'SELECT COUNT(*) FROM redirects',
];