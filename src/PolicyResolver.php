<?php

namespace GlpiPlugin\Assignmentguard;

final class PolicyResolver
{
    public function resolve(array $input, ?array $delta = null): array
    {
        $active = [
            'behaviors' => $this->isActive('behaviors'),
            'escalade' => $this->isActive('escalade'),
        ];
        $config = PluginConfig::getAll();
        $providers = [];

        foreach ($active as $name => $isActive) {
            if (!$isActive) {
                continue;
            }
            $enabled = $config['integration_' . $name . '_enabled'] === '1';
            if (!$enabled) {
                return [
                    'policy' => AssignmentDecision::POLICY_UNKNOWN,
                    'source' => $name,
                    'reason' => AssignmentDecision::NOT_ACTED_INTEGRATION_DISABLED,
                ];
            }
            $provider = $name === 'behaviors'
                ? new BehaviorsPolicyProvider() : new EscaladePolicyProvider();
            $result = $provider->resolve($input);
            if ($result['policy'] === AssignmentDecision::POLICY_UNKNOWN) {
                return $result + ['reason' => AssignmentDecision::NOT_ACTED_INTEGRATION_POLICY_UNKNOWN];
            }
            if ($result['policy'] === AssignmentDecision::POLICY_COUPLED_ACTORS) {
                return $result + ['reason' => AssignmentDecision::NOT_ACTED_COUPLED_ACTORS];
            }
            $providers[] = $result;
        }

        if (!$providers) {
            return AssignmentDecision::standalone(
                $delta,
                $config['standalone_group_replacement'] === '1'
            );
        }

        $policy = $providers[0]['policy'];
        foreach ($providers as $provider) {
            if ($provider['policy'] !== $policy) {
                return [
                    'policy' => AssignmentDecision::POLICY_CONFLICT,
                    'source' => 'combined',
                    'reason' => AssignmentDecision::NOT_ACTED_POLICY_CONFLICT,
                ];
            }
        }
        return ['policy' => $policy, 'source' => count($providers) === 1 ? $providers[0]['source'] : 'combined'];
    }

    private function isActive(string $plugin): bool
    {
        try {
            return class_exists('Plugin') && \Plugin::isPluginActive($plugin);
        } catch (\Throwable $exception) {
            return true;
        }
    }
}
