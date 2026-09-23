<?php

define('PLUGIN_ASSIGNMENTGUARD_VERSION', '0.1.0');
define('PLUGIN_ASSIGNMENTGUARD_MIN_GLPI_VERSION', '10.0.20');
define('PLUGIN_ASSIGNMENTGUARD_MAX_GLPI_VERSION', '10.0.27');

require_once __DIR__ . '/src/autoload.php';

function plugin_init_assignmentguard(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['assignmentguard'] = true;
    $PLUGIN_HOOKS['pre_item_update']['assignmentguard'] = [
        'Ticket' => 'plugin_assignmentguard_pre_item_update',
    ];
    $PLUGIN_HOOKS['config_page']['assignmentguard'] = 'front/config.form.php';
}

function plugin_version_assignmentguard(): array
{
    return [
        'name'         => 'Assignment Guard',
        'version'      => PLUGIN_ASSIGNMENTGUARD_VERSION,
        'author'       => '',
        'license'      => '',
        'homepage'     => '',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_ASSIGNMENTGUARD_MIN_GLPI_VERSION,
                'max' => PLUGIN_ASSIGNMENTGUARD_MAX_GLPI_VERSION,
            ],
        ],
    ];
}

function plugin_assignmentguard_check_prerequisites(): bool
{
    return true;
}

function plugin_assignmentguard_check_config(bool $verbose = false): bool
{
    return true;
}
