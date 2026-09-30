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
        $valid = $this->postConfigForm(session_id(), session_name(), [
            'update' => 1,
            '_glpi_csrf_token' => '__native__',
            'standalone_group_replacement' => 1,
            'integration_behaviors_enabled' => 1,
            'integration_escalade_enabled' => 1,
            'diagnostic_logging' => 1,
        ]);

        $this->assertRequestSucceeded($valid);
        $this->assertConfigurationValue($valid, 'standalone_group_replacement', '1');
        $this->assertConfigurationValue($valid, 'integration_behaviors_enabled', '1');
        $this->assertConfigurationValue($valid, 'integration_escalade_enabled', '1');
        $this->assertConfigurationValue($valid, 'diagnostic_logging', '1');

        $this->login();
        PluginConfig::save(['standalone_group_replacement' => 1]);
        $missingToken = $this->postConfigForm(session_id(), session_name(), [
            'update' => 1,
            'standalone_group_replacement' => 0,
        ]);
        $this->integer($missingToken['exit_code'])->isIdenticalTo(0);
        $this->string($missingToken['output'])->contains('The action you have requested is not allowed.');
        $this->assertConfigurationValue($missingToken, 'standalone_group_replacement', '1');

        $this->login();
        PluginConfig::save(['standalone_group_replacement' => 1]);
        $invalidToken = $this->postConfigForm(session_id(), session_name(), [
            'update' => 1,
            '_glpi_csrf_token' => 'invalid-token',
            'standalone_group_replacement' => 0,
        ]);
        $this->integer($invalidToken['exit_code'])->isIdenticalTo(0);
        $this->string($invalidToken['output'])->contains('The action you have requested is not allowed.');
        $this->assertConfigurationValue($invalidToken, 'standalone_group_replacement', '1');

        $this->login();
        PluginConfig::save(['standalone_group_replacement' => 1]);
        $limitedUser = $this->createLimitedUser();
        try {
            $this->login($limitedUser['name'], $limitedUser['password']);
            $this->boolean(\Session::haveRight('config', UPDATE))->isFalse();
            $forbidden = $this->postConfigForm(session_id(), session_name(), [
                'update' => 1,
                '_glpi_csrf_token' => '__native__',
                'standalone_group_replacement' => 0,
            ]);
            $this->integer($forbidden['exit_code'])->isIdenticalTo(0);
            $this->string($forbidden['output'])->contains('Access denied');
            $this->assertConfigurationValue($forbidden, 'standalone_group_replacement', '1');
        } finally {
            $this->login();
        }
    }

    private function preparePlugin(): void
    {
        $this->login();
        $plugin = new \Plugin();
        $plugin->checkPluginState('assignmentguard');
        $this->boolean($plugin->getFromDBByDir('assignmentguard'))->isTrue();
        $plugin->install($plugin->getID());
        $this->boolean($plugin->activate($plugin->getID()))->isTrue();
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

    /** @param array{exit_code:int,output:string,error:string} $request */
    private function assertConfigurationValue(array $request, string $name, string $value): void
    {
        $marker = 'ASSIGNMENTGUARD_CONFIG=';
        $position = strrpos($request['output'], $marker);

        if ($position === false) {
            throw new \RuntimeException('Configuration form runner did not return its configuration state.');
        }

        $config = json_decode(substr($request['output'], $position + strlen($marker)), true);
        if (!is_array($config)) {
            throw new \RuntimeException('Configuration form runner returned an invalid configuration state.');
        }

        $this->string((string) $config[$name])->isIdenticalTo($value);
    }

    /** @param array{exit_code:int,output:string,error:string} $request */
    private function assertRequestSucceeded(array $request): void
    {
        if (
            $request['exit_code'] !== 0
            || strpos($request['output'], 'The action you have requested is not allowed.') !== false
            || strpos($request['output'], 'Access denied') !== false
        ) {
            throw new \RuntimeException(sprintf(
                'Configuration form runner failed with exit code %d. Output: %s Error: %s',
                $request['exit_code'],
                $request['output'],
                $request['error'],
            ));
        }
    }
}
