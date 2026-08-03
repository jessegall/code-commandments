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

    public function test_flags_a_check_or_throw_but_never_a_throw_that_feeds_a_value(): void
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
            public function lowPrecedenceThrow(object $renderable): void {
                $renderable->isReady() or throw NotReady::for($renderable);
            }
            public function fedToAValue(array $config): string {
                $region = $config['region'] ?? throw RegionMissing::of();

                return $region;
            }
            public function fedToAProperty(array $config): void {
                $this->region = $config['region'] ?? throw RegionMissing::of();
            }
        }
        PHP;

        $hits = (new ShortCircuitStatementDetector)->find(Codebase::fromString($code));

        // A check-or-throw statement is an `if` check and is written as one; a `?? throw` FEEDING
        // a value discards nothing, so no branch is in disguise there.
        $this->assertSame(
            ['S::dispatch', 'S::lowPrecedenceThrow'],
            array_map(static fn ($m): string => $m->scope(), $hits),
        );
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
