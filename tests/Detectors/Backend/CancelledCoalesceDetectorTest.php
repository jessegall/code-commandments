<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\CancelledCoalesceDetector;
use PHPUnit\Framework\TestCase;

/**
 * `($x ?? '') !== ''` — a fallback manufactured and then cancelled by the very comparison it was
 * manufactured for. The `??` cannot change the answer: both an absent value and an empty one reach
 * the same branch, so two different questions are collapsed into one and the code no longer says
 * which it meant.
 *
 * The signal is exact and needs no guessing at intent: the coalesce fallback and the thing it is
 * compared against are the SAME literal. A real default (`?? 'EUR'`) compared against something
 * else is a different expression entirely and never matches.
 */
final class CancelledCoalesceDetectorTest extends TestCase
{
    public function test_it_flags_a_fallback_cancelled_by_the_comparison(): void
    {
        $this->assertSame(['Probe::check'], $this->scopes(<<<'PHP'
            <?php
            final class Probe
            {
                public function check(?string $sessionId): bool
                {
                    return ($sessionId ?? '') !== '';
                }
            }
            PHP));
    }

    public function test_it_flags_it_whichever_side_the_comparison_puts_it_on(): void
    {
        $this->assertSame(['Probe::check'], $this->scopes(<<<'PHP'
            <?php
            final class Probe
            {
                public function check(?string $name): bool
                {
                    return '' === ($name ?? '');
                }
            }
            PHP));
    }

    public function test_it_flags_a_zero_fallback_cancelled_the_same_way(): void
    {
        $this->assertSame(['Probe::check'], $this->scopes(<<<'PHP'
            <?php
            final class Probe
            {
                public function check(?int $count): bool
                {
                    return ($count ?? 0) === 0;
                }
            }
            PHP));
    }

    public function test_a_real_default_is_not_cancelled(): void
    {
        // The fallback is a genuine value and the comparison asks a different question, so the `??`
        // changes the answer — nothing is being laundered.
        $this->assertSame([], $this->scopes(<<<'PHP'
            <?php
            final class Probe
            {
                public function check(?string $currency): bool
                {
                    return ($currency ?? 'EUR') !== '';
                }
            }
            PHP));
    }

    public function test_a_comparison_against_something_other_than_the_fallback_stands(): void
    {
        $this->assertSame([], $this->scopes(<<<'PHP'
            <?php
            final class Probe
            {
                public function check(?string $name, string $other): bool
                {
                    return ($name ?? '') !== $other;
                }
            }
            PHP));
    }

    public function test_an_honest_absence_test_is_not_flagged(): void
    {
        $this->assertSame([], $this->scopes(<<<'PHP'
            <?php
            final class Probe
            {
                public function check(?string $name): bool
                {
                    return $name !== null && $name !== '';
                }
            }
            PHP));
    }

    public function test_a_null_fallback_is_an_honest_read_not_a_fake(): void
    {
        // Found in calibration: `?? null` before `=== null` is the ordinary way to read a key that
        // may not be there. `null` is not a manufactured value — it IS the absence — so nothing is
        // being conflated and the two branches still mean one thing.
        $this->assertSame([], $this->scopes(<<<'PHP'
            <?php
            final class Probe
            {
                /**
                 * @param  array<string, mixed>  $row
                 */
                public function check(array $row, string $key): bool
                {
                    return ($row[$key] ?? null) === null;
                }
            }
            PHP));
    }

    public function test_an_empty_collection_fallback_asks_only_one_question(): void
    {
        // An empty array is the collection's own identity — "no items" — not a scalar impersonating
        // data. Absent and empty genuinely mean the same thing here, so nothing is conflated. The
        // sibling rule exempts it for the same reason (#398).
        $this->assertSame([], $this->scopes(<<<'PHP'
            <?php
            final class Probe
            {
                /**
                 * @param  array<string, list<string>>  $arrows
                 */
                public function isFloor(array $arrows, string $namespace): bool
                {
                    return ($arrows[$namespace] ?? []) === [];
                }
            }
            PHP));
    }

    public function test_a_fallback_that_is_passed_on_belongs_to_the_other_rule(): void
    {
        // Manufactured AND USED is `manufactured-fake-fill`; the two must never both fire on one line.
        $this->assertSame([], $this->scopes(<<<'PHP'
            <?php
            final class Probe
            {
                public function check(?string $name): string
                {
                    return $this->greet($name ?? '');
                }

                private function greet(string $who): string
                {
                    return $who;
                }
            }
            PHP));
    }

    /**
     * @return list<string>
     */
    private function scopes(string $php): array
    {
        $findings = new CancelledCoalesceDetector()->find(Codebase::fromString($php, '/probe/Probe.php'));

        return array_values(array_map(static fn (object $finding): string => $finding->scope(), $findings));
    }
}
