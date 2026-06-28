<?php

return [
    'title' => 'Tým',
    'description' => 'Správa týmových pracovníků',
    'icon' => 'bi bi-people',
    'url' => '/team/list.php',
    'permission' => 'team',
    'group' => 'Obsah',
    'order' => 35,
    'show_in_menu' => true,
    'show_on_dashboard' => true,
    'count_query' => 'SELECT COUNT(*) FROM team_members',
];