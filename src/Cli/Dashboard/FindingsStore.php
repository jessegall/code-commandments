<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Dashboard;

use JesseGall\CodeCommandments\Finding;
use JesseGall\CodeCommandments\Support\File;
use JesseGall\CodeCommandments\Workspace;

/**
 * The latest findings of every judged file, kept beside the journal plugin's other state, so the Sins
 * dashboard shows the whole project whichever run last looked at it. A full run replaces everything; a
 * scoped run (`--changes`, `--branch`) replaces only the files it judged.
 */
final readonly class FindingsStore
{
    private const string FILE = 'dashboards/findings.json';

    public function __construct(private Workspace $workspace) {}

    /**
     * Record $findings as what the files in $judged hold now — every file when $judged is null — and
     * redraw the dashboard from the result.
     *
     * @param  list<Finding>  $findings
     * @param  array<string, true>|null  $judged  the files the run judged, by real path; null for all of them
     */
    public function record(array $findings, ?array $judged): void
    {
        $kept = $judged === null ? [] : array_values(array_filter($this->all(), static fn (StoredFinding $finding): bool => ! isset($judged[$finding->path])));
        $all = [...$kept, ...array_map(fn (Finding $finding) => StoredFinding::of($finding, $this->workspace->root()), $findings)];

        File::write($this->workspace->cache(self::FILE), (string) json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::write($this->workspace->cache(SinsDashboard::FILE), (string) json_encode(new SinsDashboard($all)->render(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return list<StoredFinding>
     */
    private function all(): array
    {
        $stored = json_decode((string) @file_get_contents($this->workspace->cache(self::FILE)), true);

        return is_array($stored) ? array_map(StoredFinding::fromStored(...), $stored) : [];
    }
}
