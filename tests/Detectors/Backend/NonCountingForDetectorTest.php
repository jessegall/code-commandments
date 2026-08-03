<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\NonCountingForDetector;
use PHPUnit\Framework\TestCase;

final class NonCountingForDetectorTest extends TestCase
{
    public function test_flags_a_for_whose_step_assigns_the_next_thing(): void
    {
        $code = <<<'PHP'
        <?php
        class F {
            public function nearest(Renderable $renderable): string {
                for ($one = $renderable; $one !== null; $one = $one instanceof Traceable ? $one->getAbove() : null) {
                    if ($one instanceof Subjected) {
                        return $one->subject();
                    }
                }

                throw NoSubjectAbove::for($renderable);
            }
            public function walk(?Node $head): void {
                for ($cursor = $head; $cursor !== null; $cursor = $cursor->next) {
                    $this->visit($cursor);
                }
            }
            public function counted(array $rows): void {
                for ($i = 0; $i < count($rows); $i++) {
                    $this->visit($rows[$i]);
                }
            }
            public function countedBackwards(array $rows): void {
                for ($i = count($rows) - 1; $i >= 0; $i--) {
                    $this->visit($rows[$i]);
                }
            }
            public function countedInSteps(array $rows): void {
                for ($i = 0, $n = count($rows); $i < $n; $i += 2) {
                    $this->visit($rows[$i]);
                }
            }
            public function countedPrefix(int $n): void {
                for ($i = 0; $i < $n; ++$i) {
                    $this->tick();
                }
            }
            public function whileWalk(?Node $head): void {
                $cursor = $head;

                while ($cursor !== null) {
                    $this->visit($cursor);

                    $cursor = $cursor->next;
                }
            }
        }
        PHP;

        $hits = (new NonCountingForDetector)->find(Codebase::fromString($code));

        // Every counted shape advances an induction variable; only the two walks assign
        // the next thing in the step.
        $this->assertSame(['F::nearest', 'F::walk'], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
