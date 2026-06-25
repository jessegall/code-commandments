<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Scaffolding;

/**
 * Renders scaffold-generation results through a writer callback, so the
 * Laravel command, the standalone command, and the sync auto-run all report
 * the same way.
 */
final class ScaffoldReporter
{
    /**
     * @param  list<array{name: string, class: string, status: string}>  $results
     * @param  callable(string): void  $write
     * @return int Number of classes created
     */
    public static function report(array $results, callable $write): int
    {
        $created = 0;

        foreach ($results as $result) {
            switch ($result['status']) {
                case ScaffoldGenerator::STATUS_CREATED:
                    $write("  + created {$result['class']}");
                    $created++;
                    break;
                case ScaffoldGenerator::STATUS_REWRITTEN:
                    $write("  ~ refreshed {$result['class']}");
                    break;
                case ScaffoldGenerator::STATUS_MISSING_STUB:
                    $write("  ! stub missing for {$result['name']} (skipped)");
                    break;
                // skipped (already present) is silent — nothing to report.
            }
        }

        return $created;
    }
}
