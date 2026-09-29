<?php

namespace GlpiPlugin\Assignmentguard;

final class AssignmentDecision
{
    public const POLICY_REPLACE = 'REPLACE';
    public const POLICY_ALLOW_MULTIPLE = 'ALLOW_MULTIPLE';
    public const POLICY_UNKNOWN = 'UNKNOWN';
    public const POLICY_COUPLED_ACTORS = 'COUPLED_ACTORS';
    public const RESOLUTION_CONFLICT = 'CONFLICT';

    public const ACTED_GROUP_REPLACEMENT = 'ACTED_GROUP_REPLACEMENT';
    public const NOT_ACTED_NO_GROUP_CHANGE = 'NOT_ACTED_NO_GROUP_CHANGE';
    public const NOT_ACTED_NO_EXISTING_GROUP = 'NOT_ACTED_NO_EXISTING_GROUP';
    public const NOT_ACTED_ALREADY_REPLACED = 'NOT_ACTED_ALREADY_REPLACED';
    public const NOT_ACTED_MULTIPLE_EXISTING_GROUPS = 'NOT_ACTED_MULTIPLE_EXISTING_GROUPS';
    public const NOT_ACTED_MULTIPLE_NEW_GROUPS = 'NOT_ACTED_MULTIPLE_NEW_GROUPS';
    public const NOT_ACTED_NO_NEW_GROUP = 'NOT_ACTED_NO_NEW_GROUP';
    public const NOT_ACTED_UNRECOGNIZED_ACTOR_INPUT = 'NOT_ACTED_UNRECOGNIZED_ACTOR_INPUT';
    public const NOT_ACTED_UNSUPPORTED_CONTEXT = 'NOT_ACTED_UNSUPPORTED_CONTEXT';
    public const NOT_ACTED_POLICY_ALLOWS_MULTIPLE = 'NOT_ACTED_POLICY_ALLOWS_MULTIPLE';
    public const NOT_ACTED_POLICY_CONFLICT = 'NOT_ACTED_POLICY_CONFLICT';
    public const NOT_ACTED_INTEGRATION_DISABLED = 'NOT_ACTED_INTEGRATION_DISABLED';
    public const NOT_ACTED_INTEGRATION_VERSION_UNSUPPORTED = 'NOT_ACTED_INTEGRATION_VERSION_UNSUPPORTED';
    public const NOT_ACTED_INTEGRATION_POLICY_UNKNOWN = 'NOT_ACTED_INTEGRATION_POLICY_UNKNOWN';
    public const NOT_ACTED_COUPLED_ACTORS = 'NOT_ACTED_COUPLED_ACTORS';
    public const ERROR_INTERNAL = 'ERROR_INTERNAL';

    /** @param array<string,mixed>|null $delta */
    public static function validateDelta(?array $delta): ?string
    {
        if ($delta === null || empty($delta['recognized'])) {
            $reason = $delta['reason'] ?? self::NOT_ACTED_UNSUPPORTED_CONTEXT;
            return is_string($reason) ? $reason : self::NOT_ACTED_UNSUPPORTED_CONTEXT;
        }
        if (!empty($delta['assign_users_changed'])) {
            return self::NOT_ACTED_COUPLED_ACTORS;
        }
        if (empty($delta['changed'])) {
            $reason = $delta['reason'] ?? self::NOT_ACTED_NO_GROUP_CHANGE;
            return is_string($reason) ? $reason : self::NOT_ACTED_NO_GROUP_CHANGE;
        }
        if (!isset($delta['existing_groups'], $delta['added_groups'], $delta['removed_groups'])) {
            return self::NOT_ACTED_UNSUPPORTED_CONTEXT;
        }
        if (!is_array($delta['existing_groups']) || !is_array($delta['added_groups']) || !is_array($delta['removed_groups'])) {
            return self::NOT_ACTED_UNSUPPORTED_CONTEXT;
        }
        $existingGroups = $delta['existing_groups'];
        $addedGroups = $delta['added_groups'];
        $removedGroups = $delta['removed_groups'];
        if (count($existingGroups) === 0) {
            return self::NOT_ACTED_NO_EXISTING_GROUP;
        }
        if (count($existingGroups) !== 1) {
            return self::NOT_ACTED_MULTIPLE_EXISTING_GROUPS;
        }
        if (count($addedGroups) === 0) {
            return count($removedGroups) > 0
                ? self::NOT_ACTED_ALREADY_REPLACED : self::NOT_ACTED_NO_NEW_GROUP;
        }
        if (count($addedGroups) !== 1) {
            return self::NOT_ACTED_MULTIPLE_NEW_GROUPS;
        }
        if (count($removedGroups) > 0) {
            return self::NOT_ACTED_ALREADY_REPLACED;
        }
        return null;
    }

    /**
     * @param array<string,mixed>|null $delta
     * @return array<string,mixed>
     */
    public static function standalone(?array $delta, bool $enabled): array
    {
        $reason = self::validateDelta($delta);
        if ($reason !== null) {
            return [
                'policy' => self::POLICY_UNKNOWN,
                'source' => 'standalone',
                'acted' => false,
                'reason' => $reason,
            ];
        }

        if (!$enabled) {
            return [
                'policy' => self::POLICY_ALLOW_MULTIPLE,
                'source' => 'standalone',
                'acted' => false,
                'reason' => self::NOT_ACTED_POLICY_ALLOWS_MULTIPLE,
            ];
        }

        return [
            'policy' => self::POLICY_REPLACE,
            'source' => 'standalone',
            'acted' => true,
            'reason' => self::ACTED_GROUP_REPLACEMENT,
        ];
    }
}
