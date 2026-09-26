<?php

namespace GlpiPlugin\Assignmentguard;

final class ActorInputParser
{
    public function parse($ticket, array $input): array
    {
        $existing = $this->existingGroups($ticket);
        if ($existing === null) {
            return ['recognized' => false, 'reason' => 'NOT_ACTED_UNSUPPORTED_CONTEXT'];
        }

        if (array_key_exists('_actors', $input)) {
            if (array_key_exists('_users_id_assign', $input) || array_key_exists('_users_id_assign_deleted', $input)) {
                return ['recognized' => false, 'reason' => 'NOT_ACTED_COUPLED_ACTORS'];
            }
            $existingUsers = $this->existingAssignUsers($ticket);
            if ($existingUsers === null) {
                return ['recognized' => false, 'reason' => 'NOT_ACTED_UNSUPPORTED_CONTEXT'];
            }
            return $this->parseActors($input, $existing, $existingUsers);
        }
        if (array_key_exists('_groups_id_assign', $input)) {
            if (array_key_exists('_users_id_assign', $input) || array_key_exists('_users_id_assign_deleted', $input)) {
                return ['recognized' => false, 'reason' => 'NOT_ACTED_COUPLED_ACTORS'];
            }
            return $this->parseLegacyGroups($input, $existing);
        }
        return [
            'recognized' => true,
            'changed' => false,
            'reason' => 'NOT_ACTED_NO_GROUP_CHANGE',
            'existing_groups' => array_keys($existing),
        ];
    }

    private function existingGroups($ticket): ?array
    {
        if (!method_exists($ticket, 'getGroups') || !defined('CommonITILActor::ASSIGN')) {
            return null;
        }
        if (method_exists($ticket, 'loadActors')) {
            $ticket->loadActors();
        }
        $groups = [];
        foreach ($ticket->getGroups(\CommonITILActor::ASSIGN) as $row) {
            if (!is_array($row) || !isset($row['id'], $row['groups_id']) || !ctype_digit((string) $row['groups_id'])) {
                return null;
            }
            $id = (int) $row['groups_id'];
            if ($id <= 0 || isset($groups[$id])) {
                return null;
            }
            $groups[$id] = $row;
        }
        return $groups;
    }

    private function existingAssignUsers($ticket): ?array
    {
        if (!method_exists($ticket, 'getUsers') || !defined('CommonITILActor::ASSIGN')) {
            return null;
        }
        try {
            $users = [];
            foreach ($ticket->getUsers(\CommonITILActor::ASSIGN) as $row) {
                if (!is_array($row) || !isset($row['id'], $row['users_id']) || !ctype_digit((string) $row['users_id'])) {
                    return null;
                }
                $id = (int) $row['users_id'];
                if ($id <= 0 || isset($users[$id])) {
                    return null;
                }
                $users[$id] = $row;
            }
            return $users;
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function parseActors(array $input, array $existing, array $existingUsers): array
    {
        if (!is_array($input['_actors']) || !array_key_exists('assign', $input['_actors']) || !is_array($input['_actors']['assign'])) {
            return ['recognized' => false, 'reason' => 'NOT_ACTED_UNRECOGNIZED_ACTOR_INPUT'];
        }

        $inputGroups = [];
        $inputUsers = [];
        foreach ($input['_actors']['assign'] as $actor) {
            if (!is_array($actor) || !isset($actor['itemtype'], $actor['items_id'])) {
                return ['recognized' => false, 'reason' => 'NOT_ACTED_UNRECOGNIZED_ACTOR_INPUT'];
            }
            if ($actor['itemtype'] !== 'Group') {
                if ($actor['itemtype'] === 'User') {
                    if (!ctype_digit((string) $actor['items_id']) || (int) $actor['items_id'] <= 0) {
                        return ['recognized' => false, 'reason' => 'NOT_ACTED_UNRECOGNIZED_ACTOR_INPUT'];
                    }
                    $id = (int) $actor['items_id'];
                    if (isset($inputUsers[$id])) {
                        return ['recognized' => false, 'reason' => 'NOT_ACTED_UNRECOGNIZED_ACTOR_INPUT'];
                    }
                    $inputUsers[$id] = true;
                }
                continue;
            }
            if (!ctype_digit((string) $actor['items_id']) || (int) $actor['items_id'] <= 0) {
                return ['recognized' => false, 'reason' => 'NOT_ACTED_UNRECOGNIZED_ACTOR_INPUT'];
            }
            $id = (int) $actor['items_id'];
            if (isset($inputGroups[$id])) {
                return ['recognized' => false, 'reason' => 'NOT_ACTED_UNRECOGNIZED_ACTOR_INPUT'];
            }
            $inputGroups[$id] = true;
        }

        $delta = $this->makeDelta('actors', $existing, array_keys($inputGroups), $input);
        $inputUserIds = array_keys($inputUsers);
        $existingUserIds = array_keys($existingUsers);
        $delta['assign_users_changed'] = count(array_diff($inputUserIds, $existingUserIds)) > 0
            || count(array_diff($existingUserIds, $inputUserIds)) > 0;
        return $delta;
    }

    private function parseLegacyGroups(array $input, array $existing): array
    {
        $value = $input['_groups_id_assign'];
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            $value = [(int) $value];
        }
        if (!is_array($value)) {
            return ['recognized' => false, 'reason' => 'NOT_ACTED_UNRECOGNIZED_ACTOR_INPUT'];
        }
        $groups = [];
        foreach ($value as $group) {
            if (!ctype_digit((string) $group) || (int) $group <= 0) {
                return ['recognized' => false, 'reason' => 'NOT_ACTED_UNRECOGNIZED_ACTOR_INPUT'];
            }
            $id = (int) $group;
            if (isset($groups[$id])) {
                return ['recognized' => false, 'reason' => 'NOT_ACTED_UNRECOGNIZED_ACTOR_INPUT'];
            }
            $groups[$id] = true;
        }
        return $this->makeDelta('legacy', $existing, array_keys($groups), $input);
    }

    private function makeDelta(string $format, array $existing, array $inputGroups, array $input): array
    {
        $existingIds = array_keys($existing);
        $added = array_values(array_diff($inputGroups, $existingIds));
        $removed = array_values(array_diff($existingIds, $inputGroups));
        return [
            'recognized' => true,
            'format' => $format,
            'changed' => count($added) > 0 || count($removed) > 0,
            'reason' => count($added) > 0 || count($removed) > 0 ? null : 'NOT_ACTED_NO_GROUP_CHANGE',
            'existing_groups' => $existingIds,
            'existing_rows' => $existing,
            'input_groups' => $inputGroups,
            'added_groups' => $added,
            'removed_groups' => $removed,
            'input' => $input,
        ];
    }
}
