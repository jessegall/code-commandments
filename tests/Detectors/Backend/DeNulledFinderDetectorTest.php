<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

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
            public function localOnly(int $id): ?Workflow { return null; }
            public function risky(int $id): ?Workflow { return null; }
            public function total(int $id): Workflow { return new Workflow; }

            public function handle(int $id): void {
                $w = $this->workflowFor($id);
                if ($w === null) { return; }
                $w->record();
            }

            public function settle(int $id): void {
                $this->workflowFor($id)?->record();
            }

            public function once(int $id): void {
                $this->localOnly($id)?->record();
            }

            public function rawTwice(int $id): void {
                $this->risky($id)->record();
                $this->risky($id)?->record();
            }
        }
        PHP;

        $hits = (new DeNulledFinderDetector)->find(Codebase::fromString($code));

        // workflowFor: de-nulled at 2 sites (travels) -> flagged.
        // localOnly: a single local caller checks it -> honest null, not flagged.
        // risky: 2 callers but one uses it raw -> not "every caller", not flagged.
        // total: not nullable.
        $this->assertSame(['Job::workflowFor'], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
