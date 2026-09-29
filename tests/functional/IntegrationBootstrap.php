<?php

// Behaviors 2.7.8 and Escalade 2.9.x still use GLPI's deprecated DB API.
// Their supported integration behavior is tested here; strict deprecations stay
// enabled for the native Assignment Guard lifecycle regression.
define('GLPI_STRICT_DEPRECATED', false);
set_error_handler(static function (int $severity, string $message): bool {
    return $severity === E_WARNING
        && strpos($message, 'GLPI_STRICT_DEPRECATED') !== false
        && strpos($message, 'already defined') !== false;
});

require dirname(__DIR__, 4) . '/tests/bootstrap.php';
