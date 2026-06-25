<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Skills;

/**
 * Renders skill-installation results through a writer callback, so the Laravel
 * command, the standalone command, and the sync auto-run all report the same
 * way. The literal twin of
 * {@see \JesseGall\CodeCommandments\Support\Scaffolding\ScaffoldReporter}.
 */
final class SkillReporter
{
    /**
     * @param  list<array{slug: string, status: string, files: int}>  $results
     * @param  callable(string): void  $write
     * @return int Number of skills installed (created)
     */
    public static function report(array $results, callable $write): int
    {
        $installed = 0;

        foreach ($results as $result) {
            switch ($result['status']) {
                case SkillInstaller::STATUS_CREATED:
                    $write("  + installed {$result['slug']} skill ({$result['files']} file(s))");
                    $installed++;
                    break;
                case SkillInstaller::STATUS_REWRITTEN:
                    $write("  ~ refreshed {$result['slug']} skill ({$result['files']} file(s))");
                    break;
                case SkillInstaller::STATUS_MISSING_STUB:
                    $write("  ! stub missing for {$result['slug']} skill (skipped)");
                    break;
                // skipped (already present) is silent — nothing to report.
            }
        }

        return $installed;
    }
}
