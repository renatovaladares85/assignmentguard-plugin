<?php

namespace tests\units;

use DbTestCase;
use GlpiPlugin\Assignmentguard\PluginConfig;

/**
 * Runs the configuration endpoint in a separate PHP process so its regular
 * GLPI bootstrap executes the non-API POST CSRF validation.
 */
class Config extends DbTestCase
{
    public function testConfigurationPostUsesNativeCsrfAndChecksPermissions(): void
    {
        $this->preparePlugin();
        PluginConfig::save([
            'standalone_group_replacement' => 0,
            'integration_behaviors_enabled' => 0,
            'integration_escalade_enabled' => 0,
            'diagnostic_logging' => 0,
        ]);

        $this->login();
        $token = \Session::getNewCSRFToken();
        $valid = $this->postConfigForm(session_id(), session_name(), [
            'update' => 1,
            '_glpi_csrf_token' => $token,
            'standalone_group_replacement' => 1,
            'integration_behaviors_enabled' => 1,
            'integration_escalade_enabled' => 1,
            'diagnostic_logging' => 1,
        ]);

        $this->integer($valid['exit_code'])->isIdenticalTo(0);
        \Session::start();
        $this->variable($_SESSION['glpicsrftokens'][$token] ?? null)->isNull();
        $this->assertConfigurationValue('standalone_group_replacement', '1');
        $this->assertConfigurationValue('integration_behaviors_enabled', '1');
        $this->assertConfigurationValue('integration_escalade_enabled', '1');
        $this->assertConfigurationValue('diagnostic_logging', '1');

        $this->login();
        $missingToken = $this->postConfigForm(session_id(), session_name(), [
            'update' => 1,
            'standalone_group_replacement' => 0,
        ]);
        $this->integer($missingToken['exit_code'])->isIdenticalTo(0);
        $this->string($missingToken['output'])->contains('The action you have requested is not allowed.');
        $this->assertConfigurationValue('standalone_group_replacement', '1');

        $this->login();
        $invalidToken = $this->postConfigForm(session_id(), session_name(), [
            'update' => 1,
            '_glpi_csrf_token' => 'invalid-token',
            'standalone_group_replacement' => 0,
        ]);
        $this->integer($invalidToken['exit_code'])->isIdenticalTo(0);
        $this->string($invalidToken['output'])->contains('The action you have requested is not allowed.');
        $this->assertConfigurationValue('standalone_group_replacement', '1');

        $this->login();
        $limitedUser = $this->createLimitedUser();
        try {
            $this->login($limitedUser['name'], $limitedUser['password']);
            $this->boolean(\Session::haveRight('config', UPDATE))->isFalse();
            $token = \Session::getNewCSRFToken();
            $forbidden = $this->postConfigForm(session_id(), session_name(), [
                'update' => 1,
                '_glpi_csrf_token' => $token,
                'standalone_group_replacement' => 0,
            ]);
            $this->integer($forbidden['exit_code'])->isIdenticalTo(0);
            $this->string($forbidden['output'])->contains('Access denied');
            $this->assertConfigurationValue('standalone_group_replacement', '1');
        } finally {
            $this->login();
        }
    }

    private function preparePlugin(): void
    {
        $this->login();
        $plugin = new \Plugin();
        $this->boolean($plugin->getFromDBByDir('assignmentguard'))->isTrue();
        if (!\Plugin::isPluginActive('assignmentguard')) {
            $this->boolean($plugin->activate($plugin->getID()))->isTrue();
        }
        $plugin->init(true);
    }

    /** @return array{name:string,password:string} */
    private function createLimitedUser(): array
    {
        $profile = $this->createItem(\Profile::class, [
            'name' => $this->getUniqueString(),
        ]);
        $password = 'AssignmentGuard_1!';
        $user = $this->createItem(\User::class, [
            'name' => $this->getUniqueString(),
            'password' => $password,
            'password2' => $password,
            'entities_id' => 0,
        ], ['password']);
        $this->createItem(\Profile_User::class, [
            'users_id' => $user->getID(),
            'profiles_id' => $profile->getID(),
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);

        return [
            'name' => $user->fields['name'],
            'password' => $password,
        ];
    }

    /** @param array<string,mixed> $post
     *  @return array{exit_code:int,output:string,error:string}
     */
    private function postConfigForm(string $sessionId, string $sessionName, array $post): array
    {
        $glpiRoot = dirname(__DIR__, 4);
        $runner = __DIR__ . '/ConfigFormRequestRunner.php';
        $command = implode(' ', array_map('escapeshellarg', [
            PHP_BINARY,
            $runner,
            $glpiRoot,
            $sessionId,
            $sessionName,
            base64_encode((string) json_encode($post)),
        ]));
        session_write_close();
        $process = proc_open($command, [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, $glpiRoot);

        if (!is_resource($process)) {
            throw new \RuntimeException('Unable to start configuration form request runner.');
        }

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        return [
            'exit_code' => proc_close($process),
            'output' => $output,
            'error' => $error,
        ];
    }

    private function assertConfigurationValue(string $name, string $value): void
    {
        $config = PluginConfig::getAll();
        $this->string((string) $config[$name])->isIdenticalTo($value);
    }
}
