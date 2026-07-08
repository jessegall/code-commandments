<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\DuplicateFunctionDetector;
use PHPUnit\Framework\TestCase;

final class DuplicateFunctionDetectorTest extends TestCase
{
    public function test_flags_identical_functions_ignoring_formatting_and_comments(): void
    {
        $code = <<<'PHP'
        <?php
        class A {
            public function run(int $x): int {
                $sum = 0;
                for ($i = 0; $i < $x; $i++) { $sum += $i * 2; }
                return $sum;
            }
        }
        class B {
            public function run(int $x): int
            {
                // a copy with different whitespace and a comment
                $sum = 0;

                for ($i = 0; $i < $x; $i++) {
                    $sum += $i * 2;
                }

                return $sum;
            }
        }
        class C {
            public function different(int $x): int {
                $sum = 1;
                for ($i = 0; $i < $x; $i++) { $sum *= $i + 3; }
                return $sum;
            }
        }
        PHP;

        $hits = (new DuplicateFunctionDetector)->find(Codebase::fromString($code));
        $scopes = array_map(static fn ($m): string => $m->scope(), $hits);
        sort($scopes);

        $this->assertSame(['A::run', 'B::run'], $scopes);
    }

    public function test_ignores_trivial_methods_below_the_size_floor(): void
    {
        $code = <<<'PHP'
        <?php
        class A { public function id(): string { return $this->id; } }
        class B { public function id(): string { return $this->id; } }
        PHP;

        $this->assertSame([], (new DuplicateFunctionDetector)->find(Codebase::fromString($code)));
    }

    public function test_ignores_resolve_or_throw_guard_accessors_across_independent_classes(): void
    {
        // Two independent aggregates each guarding their own optional provider — a language idiom (differing
        // only in the exception thrown), not copy-pasted logic. Hoisting would couple unrelated classes.
        $code = <<<'PHP'
        <?php
        class Connection {
            private $provider;
            public function provider() {
                if ($this->provider === null) {
                    throw NotConnectedException::unconnected();
                }
                return $this->provider;
            }
        }
        class SpawnState {
            private $provider;
            public function provider() {
                if ($this->provider === null) {
                    throw NotStartedException::unstarted();
                }
                return $this->provider;
            }
        }
        PHP;

        $this->assertSame([], (new DuplicateFunctionDetector)->find(Codebase::fromString($code)));
    }

    public function test_still_flags_genuine_duplicate_logic_that_merely_contains_a_guard(): void
    {
        // A guard FOLLOWED BY real logic is not a bare accessor — copy-paste is still flagged.
        $code = <<<'PHP'
        <?php
        class A {
            public function total(array $rows): int {
                if ($rows === []) { throw new EmptyException(); }
                $sum = 0;
                foreach ($rows as $r) { $sum += $r * 2; }
                return $sum;
            }
        }
        class B {
            public function total(array $rows): int {
                if ($rows === []) { throw new EmptyException(); }
                $sum = 0;
                foreach ($rows as $r) { $sum += $r * 2; }
                return $sum;
            }
        }
        PHP;

        $scopes = array_map(static fn ($m): string => $m->scope(), (new DuplicateFunctionDetector)->find(Codebase::fromString($code)));
        sort($scopes);

        $this->assertSame(['A::total', 'B::total'], $scopes);
    }
}
