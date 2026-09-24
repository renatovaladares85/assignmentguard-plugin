<?php

namespace GlpiPlugin\Assignmentguard;

final class AssignmentGuardHookHandler
{
    public static function handle($item): void
    {
        if (!$item instanceof \Ticket || $item->isNewItem()) {
            return;
        }
        $original = $item->input;
        $base = [
            'ticket_id' => isset($item->fields['id']) ? (int) $item->fields['id'] : null,
            'acted' => false,
            'policy_source' => 'none',
        ];
        try {
            $parser = new ActorInputParser();
            $delta = $parser->parse($item, $original);
            if (empty($delta['recognized'])) {
                self::log($base + ['decision' => $delta['reason']]);
                return;
            }
            $base += self::groupFields($delta);
            if (empty($delta['changed'])) {
                self::log($base + ['decision' => $delta['reason']]);
                return;
            }
            $decision = self::validateDelta($delta);
            if ($decision !== null) {
                self::log($base + ['decision' => $decision]);
                return;
            }
            $policy = (new PolicyResolver())->resolve($original);
            $base['policy_source'] = $policy['source'];
            if ($policy['policy'] !== 'REPLACE') {
                self::log($base + ['decision' => $policy['reason'] ?? 'NOT_ACTED_POLICY_ALLOWS_MULTIPLE']);
                return;
            }
            $normalized = (new GroupInputNormalizer())->normalize($original, $delta);
            if ($normalized === $original) {
                throw new \RuntimeException('Normalization did not alter input.');
            }
            $item->input = $normalized;
            if (!self::log($base + [
                'acted' => true,
                'decision' => 'ACTED_GROUP_REPLACEMENT',
                'normalized_groups' => $delta['added_groups'],
            ])) {
                $item->input = $original;
                self::log($base + ['decision' => 'ERROR_INTERNAL']);
            }
        } catch (\Throwable $exception) {
            $item->input = $original;
            self::log($base + ['decision' => 'ERROR_INTERNAL']);
        }
    }

    private static function validateDelta(array $delta): ?string
    {
        if (count($delta['existing_groups']) === 0) {
            return 'NOT_ACTED_NO_EXISTING_GROUP';
        }
        if (count($delta['existing_groups']) !== 1) {
            return 'NOT_ACTED_MULTIPLE_EXISTING_GROUPS';
        }
        if (count($delta['added_groups']) === 0) {
            return count($delta['removed_groups']) > 0
                ? 'NOT_ACTED_ALREADY_REPLACED' : 'NOT_ACTED_NO_NEW_GROUP';
        }
        if (count($delta['added_groups']) !== 1) {
            return 'NOT_ACTED_MULTIPLE_NEW_GROUPS';
        }
        if (count($delta['removed_groups']) > 0) {
            return 'NOT_ACTED_ALREADY_REPLACED';
        }
        return null;
    }

    private static function groupFields(array $delta): array
    {
        $fields = [];
        foreach (['existing_groups', 'input_groups', 'added_groups', 'removed_groups'] as $name) {
            if (isset($delta[$name])) {
                $fields[$name] = array_values($delta[$name]);
            }
        }
        return $fields;
    }

    private static function log(array $decision): bool
    {
        return DecisionLogger::log($decision);
    }
}
