<?php

declare(strict_types=1);

/**
 * The plugin intentionally owns no persistent schema.
 */
function plugin_quickactions_install(): bool
{
    return true;
}

/**
 * There is no plugin data to remove.
 */
function plugin_quickactions_uninstall(): bool
{
    return true;
}
