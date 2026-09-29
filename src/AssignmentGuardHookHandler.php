<?php

namespace GlpiPlugin\Assignmentguard;

final class AssignmentGuardHookHandler
{
    /** @param mixed $item */
    public static function handle($item): void
    {
        if (!$item instanceof \Ticket || $item->isNewItem()) {
            return;
        }
        $original = $item->input;
        $ticketId = $item->fields['id'] ?? null;
        $base = [
            'ticket_id' => is_int($ticketId) || (is_string($ticketId) && ctype_digit($ticketId)) ? (int) $ticketId : null,
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
            $decision = AssignmentDecision::validateDelta($delta);
            if ($decision !== null) {
                self::log($base + ['decision' => $decision]);
                return;
            }
            $policy = (new PolicyResolver())->resolve($original, $delta);
            $base['policy_source'] = $policy['source'];
            if ($policy['policy'] !== AssignmentDecision::POLICY_REPLACE) {
                self::log($base + ['decision' => $policy['reason'] ?? AssignmentDecision::NOT_ACTED_POLICY_ALLOWS_MULTIPLE]);
                return;
            }
            $normalized = (new GroupInputNormalizer())->normalize($original, $delta);
            if ($normalized === $original) {
                throw new \RuntimeException('Normalization did not alter input.');
            }
            $item->input = $normalized;
            if (!self::log(array_merge($base, [
                'acted' => true,
                'decision' => AssignmentDecision::ACTED_GROUP_REPLACEMENT,
                'normalized_groups' => $delta['added_groups'],
            ]))) {
                $item->input = $original;
                self::log($base + ['decision' => AssignmentDecision::ERROR_INTERNAL]);
            }
        } catch (\Throwable $exception) {
            $item->input = $original;
            self::log($base + ['decision' => AssignmentDecision::ERROR_INTERNAL]);
        }
    }

    /**
     * @param array<string,mixed> $delta
     * @return array<string,mixed>
     */
    private static function groupFields(array $delta): array
    {
        $fields = [];
        foreach (['existing_groups', 'input_groups', 'added_groups', 'removed_groups'] as $name) {
            if (isset($delta[$name]) && is_array($delta[$name])) {
                $fields[$name] = array_values($delta[$name]);
            }
        }
        return $fields;
    }

    /** @param array<string,mixed> $decision */
    private static function log(array $decision): bool
    {
        return DecisionLogger::log($decision);
    }
}
