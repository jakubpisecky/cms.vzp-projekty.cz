<?php

return [
    'title' => 'Dokumenty',
    'description' => 'Správa dokumentů',
    'icon' => 'bi bi-file-earmark-text',
    'url' => '/documents/list.php',
    'permission' => 'documents',
    'group' => 'Média',
    'order' => 50,
    'show_in_menu' => true,
    'show_on_dashboard' => true,
    'count_query' => 'SELECT COUNT(*) FROM documents',
];