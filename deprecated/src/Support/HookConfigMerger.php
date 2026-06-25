<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * Merges our Claude-hook entries into an existing hooks config WITHOUT
 * clobbering entries the user added under the same event. Each of our entries
 * is appended only when none of its commands are already present, so
 * re-running install/init is idempotent (no duplicates) and never removes a
 * hand-added hook (e.g. a plan-loop Stop hook).
 */
final class HookConfigMerger
{
    /**
     * @param  array<string, list<array<string, mixed>>>  $existing  existing hooks (by event)
     * @param  array<string, list<array<string, mixed>>>  $ours      the hook events we own
     * @return array<string, list<array<string, mixed>>>
     */
    public static function merge(array $existing, array $ours): array
    {
        $result = $existing;

        foreach ($ours as $event => $ourEntries) {
            $entries = $result[$event] ?? [];
            $present = self::commandsIn($entries);

            foreach ($ourEntries as $entry) {
                $commands = self::commandsIn([$entry]);

                if (array_intersect($commands, $present) !== []) {
                    continue; // one of this entry's commands is already configured
                }

                $entries[] = $entry;
                $present = array_merge($present, $commands);
            }

            $result[$event] = $entries;
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return list<string>
     */
    private static function commandsIn(array $entries): array
    {
        $commands = [];

        foreach ($entries as $entry) {
            foreach (($entry['hooks'] ?? []) as $hook) {
                if (isset($hook['command']) && is_string($hook['command'])) {
                    $commands[] = $hook['command'];
                }
            }
        }

        return $commands;
    }
}
