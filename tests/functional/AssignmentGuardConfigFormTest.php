<?php

namespace tests\units;

use DbTestCase;

/**
 * Runs the configuration endpoint in a separate PHP process so its regular
 * GLPI bootstrap executes the non-API POST CSRF validation.
 */
class Config extends DbTestCase
{
    public function testConfigurationPostUsesNativeCsrf(): void
    {
        $this->preparePlugin();

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

        $this->login();
        $missingToken = $this->postConfigForm(session_id(), session_name(), [
            'update' => 1,
            'standalone_group_replacement' => 0,
        ]);
        $this->integer($missingToken['exit_code'])->isIdenticalTo(0);
        $this->string($missingToken['output'])->contains('The action you have requested is not allowed.');

        $this->login();
        $invalidToken = $this->postConfigForm(session_id(), session_name(), [
            'update' => 1,
            '_glpi_csrf_token' => 'invalid-token',
            'standalone_group_replacement' => 0,
        ]);
        $this->integer($invalidToken['exit_code'])->isIdenticalTo(0);
        $this->string($invalidToken['output'])->contains('The action you have requested is not allowed.');

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
