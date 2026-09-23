<?php

namespace GlpiPlugin\Assignmentguard;

final class PluginConfig
{
    public const CONTEXT = 'plugin:assignmentguard';

    private const DEFAULTS = [
        'standalone_group_replacement' => '1',
        'integration_behaviors_enabled' => '0',
        'integration_escalade_enabled' => '0',
        'diagnostic_logging' => '0',
    ];

    public static function getAll(): array
    {
        $stored = \Config::getConfigurationValues(self::CONTEXT, array_keys(self::DEFAULTS));
        return array_merge(self::DEFAULTS, $stored);
    }

    public static function getBool(string $name): bool
    {
        $values = self::getAll();
        return isset($values[$name]) && (string) $values[$name] === '1';
    }

    public static function save(array $values): void
    {
        $allowed = [];
        foreach (self::DEFAULTS as $name => $default) {
            if (array_key_exists($name, $values)) {
                $allowed[$name] = !empty($values[$name]) ? '1' : '0';
            }
        }
        \Config::setConfigurationValues(self::CONTEXT, $allowed);
    }

    public static function setDefaults(): void
    {
        $current = \Config::getConfigurationValues(self::CONTEXT, array_keys(self::DEFAULTS));
        $missing = [];
        foreach (self::DEFAULTS as $name => $value) {
            if (!array_key_exists($name, $current)) {
                $missing[$name] = $value;
            }
        }
        if ($missing) {
            \Config::setConfigurationValues(self::CONTEXT, $missing);
        }
    }

    public static function deleteAll(): void
    {
        \Config::deleteConfigurationValues(self::CONTEXT, array_keys(self::DEFAULTS));
    }
}
