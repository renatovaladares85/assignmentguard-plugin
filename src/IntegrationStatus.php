<?php

namespace GlpiPlugin\Assignmentguard;

final class IntegrationStatus
{
    private const SUPPORTED_VERSIONS = [
        'behaviors' => ['2.7.8'],
        'escalade' => ['2.9.18', '2.9.19', '2.9.20', '2.9.21', '2.9.22'],
    ];

    /**
     * @param mixed $provider
     * @return array<string,mixed>
     */
    public static function resolve(
        string $name,
        bool $installed,
        bool $active,
        ?string $version,
        bool $authorized,
        $provider
    ): array {
        $supported = $installed && self::isSupported($name, $version);
        $status = [
            'installed' => $installed,
            'active' => $active,
            'version' => $version,
            'supported' => $supported,
            'authorized' => $authorized,
            'policy' => AssignmentDecision::POLICY_UNKNOWN,
        ];

        if (!$installed) {
            return self::withState($status, 'not_installed');
        }
        if (!$active) {
            return self::withState($status, 'inactive');
        }
        if (!$supported) {
            return self::withState($status, 'unsupported');
        }
        if (!$authorized) {
            return self::withState($status, 'not_authorized');
        }
        if (!is_object($provider) || !method_exists($provider, 'resolve')) {
            return self::withState($status, 'blocked');
        }

        try {
            $result = $provider->resolve([]);
            $policy = is_array($result) && is_string($result['policy'] ?? null)
                ? $result['policy'] : AssignmentDecision::POLICY_UNKNOWN;
        } catch (\Throwable $exception) {
            $policy = AssignmentDecision::POLICY_UNKNOWN;
        }

        $status['policy'] = $policy;
        $state = in_array($policy, [
            AssignmentDecision::POLICY_UNKNOWN,
            AssignmentDecision::POLICY_COUPLED_ACTORS,
        ], true) ? 'blocked' : 'ready';
        return self::withState($status, $state);
    }

    /**
     * @param array<string,mixed> $status
     * @return array<string,mixed>
     */
    private static function withState(array $status, string $state): array
    {
        $status['state'] = $state;
        $status['severity'] = self::severityForState($state);
        return $status;
    }

    private static function severityForState(string $state): string
    {
        if (in_array($state, ['not_installed', 'inactive'], true)) {
            return 'warning';
        }

        if ($state === 'ready') {
            return 'ok';
        }

        return 'blocked';
    }

    private static function isSupported(string $name, ?string $version): bool
    {
        return $version !== null
            && isset(self::SUPPORTED_VERSIONS[$name])
            && in_array($version, self::SUPPORTED_VERSIONS[$name], true);
    }
}
