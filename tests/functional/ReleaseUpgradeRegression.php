<?php

/**
 * Exercises the released 0.1.0 to candidate upgrade against GLPI's regular
 * bootstrap and database. It intentionally does not load tests/bootstrap.php
 * or glpi_test.
 */
final class ReleaseUpgradeRegression
{
    private const CONFIG_KEY = 'standalone_group_replacement';
    private const CANDIDATE_VERSION = '0.1.1';

    /** @var string */
    private $glpiRoot;

    public function __construct(string $glpiRoot)
    {
        $this->glpiRoot = $glpiRoot;
    }

    public function seed(): void
    {
        $this->bootNormalGlpi();
        $config = \GlpiPlugin\Assignmentguard\PluginConfig::getAll();
        $value = (string) $config[self::CONFIG_KEY] === '1' ? '0' : '1';
        $config[self::CONFIG_KEY] = $value;
        \GlpiPlugin\Assignmentguard\PluginConfig::save($config);

        $this->assertSame($value, $this->readConfigValue(), 'The 0.1.0 configuration was not persisted.');
        echo "upgrade_config_seeded={$value}\n";
    }

    public function verify(string $expectedValue): void
    {
        $this->bootNormalGlpi();

        $plugin = new \Plugin();
        if (!$plugin->getFromDBByDir('assignmentguard')) {
            throw new RuntimeException('Assignment Guard was not found after the native upgrade.');
        }

        $this->assertSame(
            self::CANDIDATE_VERSION,
            (string) $plugin->fields['version'],
            'The native upgrade did not register the candidate version.',
        );
        $this->assertSame(
            $expectedValue,
            $this->readConfigValue(),
            'The native upgrade did not preserve the configured value.',
        );

        echo "upgrade_version=" . self::CANDIDATE_VERSION . "\n";
        echo "upgrade_configuration_preserved=ok\n";
    }

    private function bootNormalGlpi(): void
    {
        putenv('GLPI_CONFIG_DIR');
        unset($_ENV['GLPI_CONFIG_DIR']);

        define('GLPI_ROOT', $this->glpiRoot);
        require_once GLPI_ROOT . '/inc/includes.php';
        require_once GLPI_ROOT . '/plugins/assignmentguard/src/autoload.php';
    }

    private function readConfigValue(): string
    {
        $config = \GlpiPlugin\Assignmentguard\PluginConfig::getAll();

        return (string) $config[self::CONFIG_KEY];
    }

    private function assertSame(string $expected, string $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(sprintf('%s Expected %s, got %s.', $message, $expected, $actual));
        }
    }
}

if ($argc < 3 || $argc > 4) {
    fwrite(STDERR, "Usage: ReleaseUpgradeRegression.php <glpi-root> <seed|verify> [expected-value]\n");
    exit(64);
}

$glpiRoot = realpath($argv[1]);
if ($glpiRoot === false) {
    fwrite(STDERR, "Invalid GLPI root.\n");
    exit(65);
}

try {
    $regression = new ReleaseUpgradeRegression($glpiRoot);
    if ($argv[2] === 'seed' && $argc === 3) {
        $regression->seed();
    } elseif ($argv[2] === 'verify' && $argc === 4) {
        $regression->verify($argv[3]);
    } else {
        throw new RuntimeException('Invalid upgrade regression arguments.');
    }
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
