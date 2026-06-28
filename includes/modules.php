<?php

function adminModulesPath(): string
{
    return dirname(__DIR__);
}

function loadAdminModules(): array
{
    static $modules = null;

    if ($modules !== null) {
        return $modules;
    }

    $modules = [];
    $adminPath = adminModulesPath();

    foreach (glob(adminModulesPath() . '/*/config.php') as $file) {
        $moduleKey = basename(dirname($file));
        $config = require $file;

        if (!is_array($config)) {
            continue;
        }

        $config['key'] = $moduleKey;
        $config['url'] = $config['url'] ?? '/' . $moduleKey . '/list.php';
        $config['permission'] = $config['permission'] ?? $moduleKey;
        $config['title'] = $config['title'] ?? ucfirst($moduleKey);
        $config['icon'] = $config['icon'] ?? 'bi bi-folder';
        $config['group'] = $config['group'] ?? 'Ostatní';
        $config['order'] = (int)($config['order'] ?? 999);
        $config['show_in_menu'] = $config['show_in_menu'] ?? true;
        $config['show_on_dashboard'] = $config['show_on_dashboard'] ?? true;

        $modules[$moduleKey] = $config;
    }

    uasort($modules, function ($a, $b) {
        return ($a['order'] <=> $b['order'])
            ?: strcmp($a['title'], $b['title']);
    });

    return $modules;
}

function getVisibleAdminModules(): array
{
    $visible = [];

    foreach (loadAdminModules() as $key => $module) {
        if (empty($module['show_in_menu'])) {
            continue;
        }

        $permission = $module['permission'] ?? $key;

        if (function_exists('hasPermission') && !hasPermission($permission)) {
            continue;
        }

        $visible[$key] = $module;
    }

    return $visible;
}

function getDashboardAdminModules(): array
{
    $visible = [];

    foreach (loadAdminModules() as $key => $module) {
        if (empty($module['show_on_dashboard'])) {
            continue;
        }

        $permission = $module['permission'] ?? $key;

        if (function_exists('hasPermission') && !hasPermission($permission)) {
            continue;
        }

        $visible[$key] = $module;
    }

    return $visible;
}

function groupAdminModules(array $modules): array
{
    $groups = [];

    foreach ($modules as $module) {
        $group = $module['group'] ?? 'Ostatní';
        $groups[$group][] = $module;
    }

    return $groups;
}