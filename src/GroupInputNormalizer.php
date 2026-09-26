<?php

namespace GlpiPlugin\Assignmentguard;

final class GroupInputNormalizer
{
    public function normalize(array $input, array $delta): array
    {
        $normalized = $input;
        $newGroup = (int) $delta['added_groups'][0];
        $oldGroup = (int) $delta['existing_groups'][0];
        if ($delta['format'] === 'actors') {
            foreach ($normalized['_actors']['assign'] as $key => $actor) {
                if ($actor['itemtype'] === 'Group' && (int) $actor['items_id'] === $oldGroup) {
                    unset($normalized['_actors']['assign'][$key]);
                }
            }
            $normalized['_actors']['assign'] = array_values($normalized['_actors']['assign']);
            return $normalized;
        }

        $normalized['_groups_id_assign'] = [$newGroup];
        $deletedKey = '_groups_id_assign_deleted';
        $normalized[$deletedKey] = [];
        foreach ($delta['existing_rows'] as $row) {
            $normalized[$deletedKey][] = [
                'id' => (int) $row['id'],
                'itemtype' => 'Group',
                'items_id' => (int) $row['groups_id'],
            ];
        }
        return $normalized;
    }
}
