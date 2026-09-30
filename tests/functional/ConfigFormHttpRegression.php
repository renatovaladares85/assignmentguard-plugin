<?php

/**
 * Exercises the configuration endpoint over HTTP against GLPI's regular
 * database. It intentionally does not load tests/bootstrap.php or glpi_test.
 */
final class ConfigFormHttpRegression
{
    private const CONFIG_KEY = 'standalone_group_replacement';
    private const CSRF_FAILURE = 'The action you have requested is not allowed.';
    private const ACCESS_DENIED = 'Access denied';

    /** @var string */
    private $glpiRoot;

    /** @var string */
    private $baseUrl;

    /** @var array<string,mixed>|null */
    private $originalConfig;

    public function __construct(string $glpiRoot, string $baseUrl)
    {
        $this->glpiRoot = $glpiRoot;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->originalConfig = null;
    }

    public function run(): void
    {
        $this->bootNormalGlpi();
        $this->originalConfig = $this->readConfig();
        $original = (string) $this->originalConfig[self::CONFIG_KEY];
        $changed = $original === '1' ? '0' : '1';

        try {
            $administrator = $this->login('glpi', 'glpi');
            $token = $this->getToken($administrator, '/plugins/assignmentguard/front/config.form.php');
            $authorized = $administrator->post('/plugins/assignmentguard/front/config.form.php', [
                'update' => '1',
                '_glpi_csrf_token' => $token,
                self::CONFIG_KEY => $changed,
            ]);
            $this->assertNoDenial($authorized, 'authorized POST');
            $this->assertSame($changed, $this->readConfigValue(), 'authorized POST did not persist the value');

            $missingToken = $administrator->post('/plugins/assignmentguard/front/config.form.php', [
                'update' => '1',
                self::CONFIG_KEY => $original,
            ]);
            $this->assertContains(self::CSRF_FAILURE, $missingToken['body'], 'missing token was not rejected');
            $this->assertSame($changed, $this->readConfigValue(), 'missing token changed the persisted value');

            $invalidToken = $administrator->post('/plugins/assignmentguard/front/config.form.php', [
                'update' => '1',
                '_glpi_csrf_token' => 'assignmentguard-invalid-token',
                self::CONFIG_KEY => $original,
            ]);
            $this->assertContains(self::CSRF_FAILURE, $invalidToken['body'], 'invalid token was not rejected');
            $this->assertSame($changed, $this->readConfigValue(), 'invalid token changed the persisted value');

            $postOnly = $this->login('post-only', 'postonly');
            $protectedPage = $postOnly->get('/plugins/assignmentguard/front/config.form.php');
            $this->assertContains(
                self::ACCESS_DENIED,
                $protectedPage['body'],
                'post-only unexpectedly has config UPDATE'
            );
            $postOnlyToken = $this->getToken($postOnly, '/front/helpdesk.public.php?create_ticket=1');
            $unauthorized = $postOnly->post('/plugins/assignmentguard/front/config.form.php', [
                'update' => '1',
                '_glpi_csrf_token' => $postOnlyToken,
                self::CONFIG_KEY => $original,
            ]);
            $this->assertContains(self::ACCESS_DENIED, $unauthorized['body'], 'user without config UPDATE was not denied');
            $this->assertSame($changed, $this->readConfigValue(), 'user without config UPDATE changed the persisted value');

            echo "authorized_persistence=ok\n";
            echo "missing_token=blocked\n";
            echo "invalid_token=blocked\n";
            echo "config_update_denied=blocked\n";
        } finally {
            $this->restoreConfig();
        }
    }

    private function bootNormalGlpi(): void
    {
        putenv('GLPI_CONFIG_DIR');
        unset($_ENV['GLPI_CONFIG_DIR']);

        define('GLPI_ROOT', $this->glpiRoot);
        require_once GLPI_ROOT . '/inc/includes.php';
        require_once GLPI_ROOT . '/plugins/assignmentguard/src/autoload.php';
    }

    /** @return array<string,mixed> */
    private function readConfig(): array
    {
        return \GlpiPlugin\Assignmentguard\PluginConfig::getAll();
    }

    private function readConfigValue(): string
    {
        $config = $this->readConfig();
        return (string) $config[self::CONFIG_KEY];
    }

    private function restoreConfig(): void
    {
        if ($this->originalConfig === null) {
            return;
        }

        \GlpiPlugin\Assignmentguard\PluginConfig::save($this->originalConfig);
        $this->assertSame(
            (string) $this->originalConfig[self::CONFIG_KEY],
            $this->readConfigValue(),
            'cleanup did not restore the original value'
        );
    }

    private function login(string $login, string $password): ConfigFormHttpClient
    {
        $client = new ConfigFormHttpClient($this->baseUrl);
        $loginPage = $client->get('/index.php');
        $this->assertStatus(200, $loginPage, 'login page');

        $loginNameField = $this->getInputName($loginPage['body'], 'login_name');
        $passwordField = $this->getInputName($loginPage['body'], 'login_password');
        $response = $client->post('/front/login.php', [
            $loginNameField => $login,
            $passwordField => $password,
            '_glpi_csrf_token' => $this->getNativeToken($loginPage['body'], 'login page'),
        ]);
        $this->assertLoginResponse($response, sprintf('login for %s', $login));

        return $client;
    }

    private function getToken(ConfigFormHttpClient $client, string $path): string
    {
        $response = $client->get($path);
        $this->assertStatus(200, $response, sprintf('GET %s', $path));
        $this->assertNoDenial($response, sprintf('GET %s', $path));

        return $this->getNativeToken($response['body'], $path);
    }

    private function getNativeToken(string $html, string $context): string
    {
        if (!preg_match('/<input[^>]+name=["\']_glpi_csrf_token["\'][^>]+value=["\']([^"\']+)["\']/i', $html, $matches)) {
            throw new RuntimeException(sprintf('No native CSRF token was rendered by %s.', $context));
        }

        return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
    }

    private function getInputName(string $html, string $id): string
    {
        $pattern = sprintf('/<input[^>]+id=["\']%s["\'][^>]+name=["\']([^"\']+)["\']/i', preg_quote($id, '/'));
        if (!preg_match($pattern, $html, $matches)) {
            throw new RuntimeException(sprintf('Login field %s was not rendered.', $id));
        }

        return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
    }

    /** @param array{status:int,headers:string,body:string} $response */
    private function assertStatus(int $expected, array $response, string $context): void
    {
        if ($response['status'] !== $expected) {
            throw new RuntimeException(sprintf('%s returned HTTP %d instead of %d.', $context, $response['status'], $expected));
        }
    }

    /** @param array{status:int,headers:string,body:string} $response */
    private function assertLoginResponse(array $response, string $context): void
    {
        if (
            $response['status'] !== 200
            && ($response['status'] < 300 || $response['status'] >= 400)
        ) {
            throw new RuntimeException(sprintf('%s failed (HTTP %d).', $context, $response['status']));
        }
    }

    /** @param array{status:int,headers:string,body:string} $response */
    private function assertNoDenial(array $response, string $context): void
    {
        $output = $response['headers'] . $response['body'];
        if (strpos($output, self::CSRF_FAILURE) !== false || strpos($output, self::ACCESS_DENIED) !== false) {
            throw new RuntimeException(sprintf('%s was denied by GLPI.', $context));
        }
    }

    private function assertContains(string $needle, string $haystack, string $message): void
    {
        if (strpos($haystack, $needle) === false) {
            throw new RuntimeException($message);
        }
    }

    private function assertSame(string $expected, string $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(sprintf('%s Expected %s, got %s.', $message, $expected, $actual));
        }
    }

}

final class ConfigFormHttpClient
{
    /** @var string */
    private $baseUrl;

    /** @var string */
    private $cookieFile;

    public function __construct(string $baseUrl)
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('The functional HTTP regression requires the PHP cURL extension.');
        }

        $this->baseUrl = $baseUrl;
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'assignmentguard-cookies-');
        if ($this->cookieFile === false) {
            throw new RuntimeException('Unable to create the HTTP cookie jar.');
        }
    }

    public function __destruct()
    {
        if (is_file($this->cookieFile)) {
            unlink($this->cookieFile);
        }
    }

    /** @return array{status:int,headers:string,body:string} */
    public function get(string $path): array
    {
        return $this->request($path, null);
    }

    /** @param array<string,string> $fields
     *  @return array{status:int,headers:string,body:string}
     */
    public function post(string $path, array $fields): array
    {
        return $this->request($path, http_build_query($fields, '', '&'));
    }

    /** @return array{name:string,value:string} */
    public function getSessionCookie(): array
    {
        $lines = file($this->cookieFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new RuntimeException('Unable to read the HTTP cookie jar.');
        }

        foreach ($lines as $line) {
            $line = preg_replace('/^#HttpOnly_/', '', $line);
            $parts = explode("\t", $line);
            if (count($parts) === 7 && strpos($parts[5], 'glpi_') === 0) {
                return [
                    'name' => $parts[5],
                    'value' => $parts[6],
                ];
            }
        }

        throw new RuntimeException('GLPI session cookie was not created.');
    }

    /** @return array{status:int,headers:string,body:string} */
    private function request(string $path, ?string $postFields): array
    {
        $handle = curl_init($this->baseUrl . $path);
        if ($handle === false) {
            throw new RuntimeException('Unable to initialize the HTTP client.');
        }

        curl_setopt_array($handle, [
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        if ($postFields !== null) {
            curl_setopt($handle, CURLOPT_POST, true);
            curl_setopt($handle, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($handle, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        }

        $rawResponse = curl_exec($handle);
        if ($rawResponse === false) {
            $error = curl_error($handle);
            curl_close($handle);
            throw new RuntimeException(sprintf('HTTP request to %s failed: %s', $path, $error));
        }

        $headerSize = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return [
            'status' => $status,
            'headers' => substr($rawResponse, 0, $headerSize),
            'body' => substr($rawResponse, $headerSize),
        ];
    }
}

if ($argc !== 3) {
    fwrite(STDERR, "Usage: ConfigFormHttpRegression.php <glpi-root> <base-url>\n");
    exit(64);
}

$glpiRoot = realpath($argv[1]);
if ($glpiRoot === false || !preg_match('#^https?://#', $argv[2])) {
    fwrite(STDERR, "Invalid GLPI root or base URL.\n");
    exit(65);
}

try {
    (new ConfigFormHttpRegression($glpiRoot, $argv[2]))->run();
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
