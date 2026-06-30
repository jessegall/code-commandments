<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\LoopInvertedGuardDetector;
use PHPUnit\Framework\TestCase;

final class LoopInvertedGuardDetectorTest extends TestCase
{
    public function test_flags_a_loop_whose_whole_body_is_one_if_only(): void
    {
        $code = <<<'PHP'
        <?php
        class S {
            public function wrapped(array $rows): void {
                foreach ($rows as $row) {
                    if ($row->active) {
                        $this->process($row);
                        $this->log($row);
                    }
                }
            }
            public function guarded(array $rows): void {
                foreach ($rows as $row) {
                    if (! $row->active) { continue; }
                    $this->process($row);
                }
            }
            public function branched(array $rows): void {
                foreach ($rows as $row) {
                    if ($row->active) { $this->a($row); } else { $this->b($row); }
                }
            }
            public function thenMore(array $rows): void {
                foreach ($rows as $row) {
                    if ($row->active) { $this->process($row); }
                    $this->tally($row);
                }
            }
        }
        PHP;

        $hits = (new LoopInvertedGuardDetector)->find(Codebase::fromString($code));

        // only `wrapped` — guarded uses continue, branched has an else, thenMore has a 2nd statement.
        $this->assertSame(['S::wrapped'], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
