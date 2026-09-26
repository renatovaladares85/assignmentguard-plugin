<?php

namespace GlpiPlugin\Assignmentguard;

final class IntegrationStatus
{
    private const SUPPORTED_VERSIONS = [
        'behaviors' => ['2.7.8'],
        'escalade' => ['2.9.18', '2.9.19', '2.9.20', '2.9.21', '2.9.22'],
    ];

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
            return $status + ['state' => 'not_installed'];
        }
        if (!$active) {
            return $status + ['state' => 'inactive'];
        }
        if (!$supported) {
            return $status + ['state' => 'unsupported'];
        }
        if (!$authorized) {
            return $status + ['state' => 'not_authorized'];
        }

        try {
            $result = $provider->resolve([]);
            $policy = $result['policy'] ?? AssignmentDecision::POLICY_UNKNOWN;
        } catch (\Throwable $exception) {
            $policy = AssignmentDecision::POLICY_UNKNOWN;
        }

        $status['policy'] = $policy;
        $status['state'] = in_array($policy, [
            AssignmentDecision::POLICY_UNKNOWN,
            AssignmentDecision::POLICY_COUPLED_ACTORS,
        ], true) ? 'blocked' : 'ready';
        return $status;
    }

    private static function isSupported(string $name, ?string $version): bool
    {
        return $version !== null
            && isset(self::SUPPORTED_VERSIONS[$name])
            && in_array($version, self::SUPPORTED_VERSIONS[$name], true);
    }
}
