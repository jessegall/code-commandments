<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\ShortCircuitStatementDetector;
use PHPUnit\Framework\TestCase;

final class ShortCircuitStatementDetectorTest extends TestCase
{
    public function test_flags_a_bare_short_circuit_used_as_control_flow(): void
    {
        $code = <<<'PHP'
        <?php
        class S {
            public function andChain(Node $node): void {
                $node->isBuilt() && $node->built()->forgetComposition();
            }
            public function orChain(Node $node): void {
                $node->isBuilt() || $node->build();
            }
            public function lowPrecedence(Node $node): void {
                $node->isBuilt() or $node->build();
            }
            public function wrapped(Node $node): void {
                if ($node->isBuilt()) {
                    $node->built()->forgetComposition();
                }
            }
            public function condition(Node $node): void {
                if ($node->isBuilt() && $node->isFresh()) {
                    $node->forget();
                }
            }
            public function assigned(Node $node): bool {
                $ready = $node->isBuilt() && $node->isFresh();

                return $ready;
            }
            public function returned(Node $node): bool {
                return $node->isBuilt() && $node->isFresh();
            }
            public function argument(Node $node): void {
                $this->assert($node->isBuilt() && $node->isFresh());
            }
        }
        PHP;

        $hits = (new ShortCircuitStatementDetector)->find(Codebase::fromString($code));

        $this->assertSame(
            ['S::andChain', 'S::orChain', 'S::lowPrecedence'],
            array_map(static fn ($m): string => $m->scope(), $hits),
        );
    }

    public function test_spares_an_assertion_whose_only_outcome_is_leaving(): void
    {
        $code = <<<'PHP'
        <?php
        class S {
            public function dispatch(object $renderable): void {
                $renderable instanceof Interactive || throw TargetCannotReceiveSignal::for(
                    $this->target,
                    $renderable::class,
                );

                $renderable->receive($this->signal);
            }
            public function assertThenWork(object $renderable): void {
                $renderable->isReady() or throw NotReady::for($renderable);
            }
            public function guardsRealWork(object $renderable): void {
                $renderable->isReady() || $renderable->prepare();
            }
        }
        PHP;

        $hits = (new ShortCircuitStatementDetector)->find(Codebase::fromString($code));

        // A `throw` on the right hides no branch — only `guardsRealWork` conceals a consequence.
        $this->assertSame(['S::guardsRealWork'], array_map(static fn ($m): string => $m->scope(), $hits));
    }

    public function test_reports_the_outermost_short_circuit_only(): void
    {
        $code = <<<'PHP'
        <?php
        class S {
            public function chained(Node $node): void {
                $node->isBuilt() && $node->isFresh() && $node->forget();
            }
        }
        PHP;

        $hits = (new ShortCircuitStatementDetector)->find(Codebase::fromString($code));

        $this->assertCount(1, $hits);
    }
}
