<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

use JesseGall\CodeCommandments\Cli\State\Legend;
use JesseGall\CodeCommandments\Cli\State\StateFile;
use JesseGall\CodeCommandments\Workspace;

/**
 * The findings the per-edit check has already named this session, so each is said once. A file's
 * list is replaced every time it is checked: a finding fixed and later brought back is news again.
 */
final readonly class ReportedFindings
{
    public function __construct(private Workspace $workspace) {}

    /**
     * Of $found in $file — skill slug => "sin at path:line" — the ones not named before, and remember
     * $found as what $file holds now.
     *
     * @param  array<string, list<string>>  $found
     * @return array<string, list<string>>
     */
    public function unseen(string $file, array $found): array
    {
        $state = $this->state();
        $kept = $state->read()->items();
        $before = array_flip(array_filter($kept, static fn (string $item): bool => self::fileOf($item) === $file));
        $now = array_merge([], ...array_values($found));

        $state->write($state->read()->withItems([
            ...array_filter($kept, static fn (string $item): bool => self::fileOf($item) !== $file),
            ...array_map(static fn (string $finding): string => "{$file}\t{$finding}", $now),
        ]));

        return array_filter(array_map(
            static fn (array $sins): array => array_values(array_filter($sins, static fn (string $finding): bool => ! isset($before["{$file}\t{$finding}"]))),
            $found,
        ));
    }

    private static function fileOf(string $item): string
    {
        return explode("\t", $item)[0];
    }

    private function state(): StateFile
    {
        return new StateFile($this->workspace->path('.reported-findings'), new Legend(
            'Code-commandments — the findings the per-edit check has already named this session, so each is said once.',
            [],
            list: 'one finding per line: the file, a tab, then the finding as it was named',
            safe: 'the findings still standing are named once more',
        ));
    }
}
