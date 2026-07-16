<?php

declare(strict_types=1);

use Glpi\Plugin\Hooks;
use GlpiPlugin\Quickactions\PanelRenderer;

define('PLUGIN_QUICKACTIONS_VERSION', '1.0.4');
define('PLUGIN_QUICKACTIONS_MIN_GLPI', '11.0.0');
define('PLUGIN_QUICKACTIONS_MAX_GLPI', '12.0.0');

/**
 * Load plugin classes without requiring a generated Composer vendor tree.
 */
function plugin_quickactions_autoload(string $class): void
{
    $prefix = 'GlpiPlugin\\Quickactions\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
}

spl_autoload_register('plugin_quickactions_autoload');

function plugin_init_quickactions(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS[Hooks::ADD_CSS]['quickactions'] = 'css/quickactions.css';
    $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['quickactions'] = 'js/quickactions.js';
    $PLUGIN_HOOKS[Hooks::POST_ITIL_INFO_SECTION]['quickactions'] = [PanelRenderer::class, 'render'];
}

function plugin_version_quickactions(): array
{
    return [
        'name'           => 'Ticket Quick Actions',
        'version'        => PLUGIN_QUICKACTIONS_VERSION,
        'author'         => 'Clint Swartzlander',
        'license'        => 'GPL-3.0-or-later',
        'homepage'       => 'https://github.com/clintswartzlander/glpi-ticket-quick-actions',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_QUICKACTIONS_MIN_GLPI,
                'max' => PLUGIN_QUICKACTIONS_MAX_GLPI,
            ],
            'php' => [
                'min' => '8.2.0',
            ],
        ],
    ];
}

function plugin_quickactions_check_prerequisites(): bool
{
    if (PHP_VERSION_ID < 80200) {
        echo 'Ticket Quick Actions requires PHP 8.2 or newer.';
        return false;
    }

    if (!defined('GLPI_VERSION')) {
        echo 'Ticket Quick Actions must be loaded by GLPI.';
        return false;
    }

    if (
        version_compare(GLPI_VERSION, PLUGIN_QUICKACTIONS_MIN_GLPI, '<')
        || version_compare(GLPI_VERSION, PLUGIN_QUICKACTIONS_MAX_GLPI, '>=')
    ) {
        echo 'Ticket Quick Actions requires GLPI 11.0.x.';
        return false;
    }

    return true;
}

function plugin_quickactions_check_config(bool $verbose = false): bool
{
    return true;
}
