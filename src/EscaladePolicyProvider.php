<?php

namespace GlpiPlugin\Assignmentguard;

final class EscaladePolicyProvider
{
    private const SUPPORTED_VERSIONS = ['2.9.18', '2.9.19', '2.9.20', '2.9.21', '2.9.22'];

    public function resolve(array $input, ?array $delta = null): array
    {
        if (!in_array($this->version(), self::SUPPORTED_VERSIONS, true)) {
            return ['policy' => 'UNKNOWN', 'source' => 'escalade', 'reason' => 'NOT_ACTED_INTEGRATION_VERSION_UNSUPPORTED'];
        }
        $config = $_SESSION['glpi_plugins']['escalade']['config'] ?? null;
        if (!is_array($config) || !array_key_exists('remove_group', $config)) {
            return ['policy' => 'UNKNOWN', 'source' => 'escalade'];
        }
        if ((string) $config['remove_group'] === '0') {
            return ['policy' => 'ALLOW_MULTIPLE', 'source' => 'escalade'];
        }
        if ((string) $config['remove_group'] !== '1') {
            return ['policy' => 'UNKNOWN', 'source' => 'escalade'];
        }
        if (!$this->hasValidCouplingConfig($config)) {
            return ['policy' => 'UNKNOWN', 'source' => 'escalade'];
        }
        if ($this->hasCoupledEffect($config, $input, $delta)) {
            return ['policy' => 'COUPLED_ACTORS', 'source' => 'escalade'];
        }
        return ['policy' => 'REPLACE', 'source' => 'escalade'];
    }

    private function hasValidCouplingConfig(array $config): bool
    {
        foreach ([
            'remove_tech',
            'remove_requester',
            'use_assign_user_group_modification',
            'reassign_group_from_cat',
            'reassign_tech_from_cat',
            'solve_return_group',
        ] as $key) {
            if (!array_key_exists($key, $config) || !in_array((string) $config[$key], ['0', '1'], true)) {
                return false;
            }
        }

        if (!array_key_exists('use_assign_user_group', $config)
            || !in_array((string) $config['use_assign_user_group'], ['0', '1', '2'], true)) {
            return false;
        }

        if (!array_key_exists('ticket_last_status', $config)) {
            return false;
        }

        $status = (string) $config['ticket_last_status'];
        return $status === '-1' || ctype_digit($status);
    }

    private function hasCoupledEffect(array $config, array $input, ?array $delta): bool
    {
        foreach (['remove_tech', 'remove_requester'] as $key) {
            if (!empty($config[$key])) {
                return true;
            }
        }
        if (!array_key_exists('ticket_last_status', $config) || (int) $config['ticket_last_status'] !== -1) {
            return true;
        }
        if (!empty($config['use_assign_user_group']) && !empty($config['use_assign_user_group_modification'])
            && $this->changesAssignUsers($input, $delta)) {
            return true;
        }
        if ((!empty($config['reassign_group_from_cat']) || !empty($config['reassign_tech_from_cat']))
            && array_key_exists('itilcategories_id', $input)) {
            return true;
        }
        if (!empty($config['solve_return_group']) && array_key_exists('status', $input)) {
            return true;
        }
        return false;
    }

    private function changesAssignUsers(array $input, ?array $delta): bool
    {
        if ($delta !== null && array_key_exists('assign_users_changed', $delta)) {
            return !empty($delta['assign_users_changed']);
        }
        if (isset($input['_actors']['assign']) && is_array($input['_actors']['assign'])) {
            foreach ($input['_actors']['assign'] as $actor) {
                if (is_array($actor) && ($actor['itemtype'] ?? null) === 'User') {
                    return true;
                }
            }
        }
        return array_key_exists('_users_id_assign', $input)
            || array_key_exists('_additional_users_assign', $input);
    }

    private function version(): ?string
    {
        try {
            $version = \Plugin::getInfo('escalade', 'version');
            return is_string($version) ? $version : null;
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
