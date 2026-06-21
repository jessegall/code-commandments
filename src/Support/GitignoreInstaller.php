<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\PhpTypes\T_String;

/**
 * Idempotently keeps a managed block in the consumer's `.gitignore` that
 * excludes every file code-commandments generates to track local runtime
 * state — absolutions, the finding cache, the report ledger, and the sync
 * baseline. These are per-developer artifacts and must never be committed.
 *
 * The block is delimited by markers so it can sit alongside the project's own
 * `.gitignore` entries without clobbering them, and so the list can be
 * refreshed in place when the package starts generating new state files (the
 * installer is re-run on every `init`, `install-hooks`, and `sync`, the last
 * of which fires automatically via the post-merge hook on package update).
 */
final class GitignoreInstaller
{
    public const STATUS_INSTALLED = 'installed';
    public const STATUS_APPENDED = 'appended';
    public const STATUS_UPDATED = 'updated';
    public const STATUS_ALREADY_PRESENT = 'already_present';
    public const STATUS_WRITE_FAILED = 'write_failed';

    private const BEGIN = '# >>> code-commandments generated state >>>';
    private const END = '# <<< code-commandments generated state <<<';

    /**
     * Paths, relative to the project root, that code-commandments writes as
     * local tracking state. Keep this list in sync with where the trackers
     * persist (JsonConfessionTracker, ReportLedger, VersionResolver).
     */
    private const ENTRIES = [
        '.commandments/',
        '.commandments-reports.json',
        '.commandments-last-synced',
        '.claude/plan-active',
        'HANDOFF.md',
    ];

    /**
     * The installed skills tree, ignored ONLY when skills are tool-owned
     * (skills.auto_refresh is on — they are regenerated, not committed). When
     * auto-refresh is off the skills are committed like CLAUDE.md, so the entry
     * is omitted (and removed in place on a re-assert).
     */
    private const SKILLS_ENTRY = '.claude/skills/commandments-*/';

    public function ensure(string $basePath, bool $ignoreSkills = false): string
    {
        $path = rtrim($basePath, '/') . '/.gitignore';
        $block = $this->block($ignoreSkills);

        if (! is_file($path)) {
            if (@file_put_contents($path, $block . T_String::NEWLINE) === false) {
                return self::STATUS_WRITE_FAILED;
            }

            return self::STATUS_INSTALLED;
        }

        $existing = (string) @file_get_contents($path);

        if (str_contains($existing, self::BEGIN)) {
            $replaced = (string) preg_replace(
                '/' . preg_quote(self::BEGIN, '/') . '.*?' . preg_quote(self::END, '/') . '/s',
                $block,
                $existing,
            );

            if ($replaced === $existing) {
                return self::STATUS_ALREADY_PRESENT;
            }

            if (@file_put_contents($path, $replaced) === false) {
                return self::STATUS_WRITE_FAILED;
            }

            return self::STATUS_UPDATED;
        }

        // Append our block to the project's existing .gitignore.
        if (@file_put_contents($path, rtrim($existing, T_String::NEWLINE) . T_String::PARAGRAPH . $block . T_String::NEWLINE) === false) {
            return self::STATUS_WRITE_FAILED;
        }

        return self::STATUS_APPENDED;
    }

    private function block(bool $ignoreSkills = false): string
    {
        $entries = self::ENTRIES;

        // The skills tree is ignored only when it is tool-owned (auto-refresh);
        // otherwise it is committed like CLAUDE.md, so the entry is omitted.
        if ($ignoreSkills) {
            $entries[] = self::SKILLS_ENTRY;
        }

        return self::BEGIN . T_String::NEWLINE
            . '# Local runtime/tracking state — absolutions, finding cache, report' . T_String::NEWLINE
            . '# ledger, sync baseline. Per-developer; never commit.' . T_String::NEWLINE
            . implode(T_String::NEWLINE, $entries) . T_String::NEWLINE
            . self::END;
    }
}
