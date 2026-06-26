<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\DeNulledFinderDetector;
use PHPUnit\Framework\TestCase;

final class DeNulledFinderDetectorTest extends TestCase
{
    public function test_flags_a_nullable_finder_every_caller_de_nulls(): void
    {
        $code = <<<'PHP'
        <?php
        class Workflow { public function record(): void {} }
        class Job {
            public function workflowFor(int $id): ?Workflow { return null; }
            public function genuine(int $id): ?Workflow { return null; }
            public function total(int $id): Workflow { return new Workflow; }

            public function handle(int $id): void {
                $w = $this->workflowFor($id);
                if ($w === null) { return; }
                $w->record();
            }

            public function settle(int $id): void {
                $this->workflowFor($id)?->record();
            }

            public function risky(int $id): void {
                $this->genuine($id)->record();
            }
        }
        PHP;

        $hits = (new DeNulledFinderDetector)->find(Codebase::fromString($code));

        // workflowFor: both callers de-null (=== null, ?->) -> flagged.
        // genuine: a caller uses it raw -> not flagged. total: not nullable.
        $this->assertSame(['Job::workflowFor'], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
