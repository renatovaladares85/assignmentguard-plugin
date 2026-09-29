<?php

namespace GlpiPlugin\Assignmentguard;

final class GroupInputNormalizer
{
    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $delta
     * @return array<string,mixed>
     */
    public function normalize(array $input, array $delta): array
    {
        $addedGroups = $delta['added_groups'] ?? null;
        $existingGroups = $delta['existing_groups'] ?? null;
        if (!is_array($addedGroups) || !is_array($existingGroups)) {
            return $input;
        }
        $newGroup = $this->positiveId($addedGroups[0] ?? null);
        $oldGroup = $this->positiveId($existingGroups[0] ?? null);
        if ($newGroup === null || $oldGroup === null) {
            return $input;
        }

        $normalized = $input;
        if ($delta['format'] === 'actors') {
            $actors = $normalized['_actors'] ?? null;
            if (!is_array($actors) || !isset($actors['assign']) || !is_array($actors['assign'])) {
                return $input;
            }
            foreach ($actors['assign'] as $key => $actor) {
                if (!is_array($actor) || !isset($actor['itemtype'], $actor['items_id'])) {
                    return $input;
                }
                if ($actor['itemtype'] === 'Group' && (int) $actor['items_id'] === $oldGroup) {
                    unset($actors['assign'][$key]);
                }
            }
            $actors['assign'] = array_values($actors['assign']);
            $normalized['_actors'] = $actors;
            return $normalized;
        }

        $existingRows = $delta['existing_rows'] ?? null;
        if (!is_array($existingRows)) {
            return $input;
        }
        $deletedKey = '_groups_id_assign_deleted';
        $deletedRows = [];
        foreach ($existingRows as $row) {
            if (!is_array($row) || !isset($row['id'], $row['groups_id'])) {
                return $input;
            }
            $actorId = $this->positiveId($row['id']);
            $groupId = $this->positiveId($row['groups_id']);
            if ($actorId === null || $groupId === null) {
                return $input;
            }
            $deletedRows[] = [
                'id' => $actorId,
                'itemtype' => 'Group',
                'items_id' => $groupId,
            ];
        }
        $normalized['_groups_id_assign'] = [$newGroup];
        $normalized[$deletedKey] = $deletedRows;
        return $normalized;
    }

    /** @param mixed $value */
    private function positiveId($value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (is_string($value) && ctype_digit($value)) {
            $id = (int) $value;
            return $id > 0 ? $id : null;
        }
        return null;
    }
}
