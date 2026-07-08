<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\ConditionalArraySpreadDetector;
use PHPUnit\Framework\TestCase;

final class ConditionalArraySpreadDetectorTest extends TestCase
{
    public function test_flags_a_conditional_element_spread_in_an_array_literal(): void
    {
        $this->assertSame(1, $this->hits(<<<'PHP'
        function build(array $base, $x): array {
            return [...$base, ...($x !== null ? ['k' => $x] : [])];
        }
        PHP));
    }

    public function test_flags_a_conditional_array_merge_argument(): void
    {
        $this->assertSame(1, $this->hits(<<<'PHP'
        function build(array $base, bool $on, $v): array {
            return array_merge($base, $on ? ['k' => $v] : []);
        }
        PHP));
    }

    public function test_flags_the_empty_first_filled_second_order_too(): void
    {
        $this->assertSame(1, $this->hits(<<<'PHP'
        function build(array $base, $x): array {
            return [...$base, ...($x === null ? [] : ['k' => $x])];
        }
        PHP));
    }

    public function test_does_not_flag_a_plain_spread(): void
    {
        $this->assertSame(0, $this->hits(<<<'PHP'
        function build(array $base, array $extra): array {
            return [...$base, ...$extra];
        }
        PHP));
    }

    public function test_does_not_flag_a_ternary_between_two_filled_arrays(): void
    {
        // Both branches carry keys — a real either/or, not a conditional include.
        $this->assertSame(0, $this->hits(<<<'PHP'
        function build(array $base, bool $on): array {
            return [...$base, ...($on ? ['a' => 1] : ['b' => 2])];
        }
        PHP));
    }

    public function test_does_not_flag_a_ternary_returning_a_scalar(): void
    {
        // A conditional scalar value, not a conditional array element.
        $this->assertSame(0, $this->hits(<<<'PHP'
        function build($x): array {
            return ['k' => $x !== null ? $x : 0];
        }
        PHP));
    }

    public function test_does_not_flag_a_conditional_array_outside_a_spread_or_merge(): void
    {
        // `$rows = $cond ? [...] : []` assigned to a variable is fine — it's not smuggled into another array.
        $this->assertSame(0, $this->hits(<<<'PHP'
        function build(bool $cond, $v): array {
            $rows = $cond ? ['k' => $v] : [];
            return $rows;
        }
        PHP));
    }

    private function hits(string $php): int
    {
        return count((new ConditionalArraySpreadDetector)->find(Codebase::fromString("<?php\n" . $php, '/proj/app/File.php')));
    }
}
