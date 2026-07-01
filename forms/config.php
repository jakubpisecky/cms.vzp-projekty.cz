<?php

return [
    'title' => 'Formuláře',
    'description' => 'Správa formulářů a přijatých zpráv',
    'icon' => 'bi bi-ui-checks',
    'url' => '/forms/list.php',
    'permission' => 'forms',
    'group' => 'Komunikace',
    'order' => 85,
    'show_in_menu' => true,
    'show_on_dashboard' => true,
    'count_query' => 'SELECT COUNT(*) FROM forms',
];