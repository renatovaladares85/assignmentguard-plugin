<?php

require_once __DIR__ . '/src/autoload.php';

function plugin_assignmentguard_install(): bool
{
    \GlpiPlugin\Assignmentguard\PluginConfig::setDefaults();
    return true;
}

function plugin_assignmentguard_uninstall(): bool
{
    \GlpiPlugin\Assignmentguard\PluginConfig::deleteAll();
    return true;
}

function plugin_assignmentguard_pre_item_update($item): void
{
    \GlpiPlugin\Assignmentguard\AssignmentGuardHookHandler::handle($item);
}
