<?php

namespace GlpiPlugin\Assignmentguard;

final class BehaviorsPolicyProvider
{
    public function resolve(array $input): array
    {
        $version = $this->version();
        if ($version !== '2.7.8') {
            return ['policy' => 'UNKNOWN', 'source' => 'behaviors', 'reason' => 'NOT_ACTED_INTEGRATION_VERSION_UNSUPPORTED'];
        }
        if (!class_exists('PluginBehaviorsConfig')) {
            return ['policy' => 'UNKNOWN', 'source' => 'behaviors'];
        }
        try {
            $config = \PluginBehaviorsConfig::getInstance();
            $mode = $config->getField('single_tech_mode');
            if ((string) $mode === '0') {
                return ['policy' => 'ALLOW_MULTIPLE', 'source' => 'behaviors'];
            }
            if ((string) $mode === '1') {
                return ['policy' => 'REPLACE', 'source' => 'behaviors'];
            }
            if ((string) $mode === '2') {
                return ['policy' => 'COUPLED_ACTORS', 'source' => 'behaviors'];
            }
        } catch (\Throwable $exception) {
        }
        return ['policy' => 'UNKNOWN', 'source' => 'behaviors'];
    }

    private function version(): ?string
    {
        try {
            $version = \Plugin::getInfo('behaviors', 'version');
            return is_string($version) ? $version : null;
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
