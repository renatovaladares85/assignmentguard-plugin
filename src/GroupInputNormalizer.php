<?php

namespace GlpiPlugin\Assignmentguard;

final class GroupInputNormalizer
{
    public function normalize(array $input, array $delta): array
    {
        $newGroup = (int) $delta['added_groups'][0];
        $oldGroup = (int) $delta['existing_groups'][0];
        if ($delta['format'] === 'actors') {
            foreach ($input['_actors']['assign'] as $key => $actor) {
                if ($actor['itemtype'] === 'Group' && (int) $actor['items_id'] === $oldGroup) {
                    unset($input['_actors']['assign'][$key]);
                }
            }
            $input['_actors']['assign'] = array_values($input['_actors']['assign']);
            return $input;
        }

        $input['_groups_id_assign'] = [$newGroup];
        $deletedKey = '_groups_id_assign_deleted';
        $input[$deletedKey] = [];
        foreach ($delta['existing_rows'] as $row) {
            $input[$deletedKey][] = [
                'id' => (int) $row['id'],
                'itemtype' => 'Group',
                'items_id' => (int) $row['groups_id'],
            ];
        }
        return $input;
    }
}
