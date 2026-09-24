<?php

namespace GlpiPlugin\Assignmentguard;

final class DecisionLogger
{
    /** @var callable|null */
    private static $writer;

    public static function setWriterForTests($writer): void
    {
        self::$writer = $writer;
    }

    public static function log(array $decision): bool
    {
        try {
            $decision['timestamp'] = gmdate('c');
            $decision['plugin_version'] = defined('PLUGIN_ASSIGNMENTGUARD_VERSION')
                ? PLUGIN_ASSIGNMENTGUARD_VERSION : 'unknown';
            $decision['glpi_version'] = defined('GLPI_VERSION') ? GLPI_VERSION : 'unknown';

            $line = json_encode($decision, JSON_UNESCAPED_SLASHES);
            if ($line === false) {
                $line = '{"decision":"ERROR_INTERNAL","acted":false}';
            }
            if (self::$writer !== null) {
                call_user_func(self::$writer, $line, $decision);
                return true;
            }
            \Toolbox::logInFile('assignmentguard', $line, true);
            return true;
        } catch (\Throwable $exception) {
            // Logging must not block the native Ticket update flow.
            return false;
        }
    }
}
