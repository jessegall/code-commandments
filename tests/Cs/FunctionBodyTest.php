<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cs;

use JesseGall\CodeCommandments\Cs\Bridge;
use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What a clone rule reads off a C# member's body to leave alone the shapes that are alike by
 * construction: a constructor, a one-statement body — an expression body included, which IS its one
 * statement — a lookup table, a member with no body, and one whose shape its contract dictates.
 */
final class FunctionBodyTest extends TestCase
{
    private const string SOURCE = <<<'CS'
        public enum Color { Red, Green }

        public interface IPriced { int Price(); }

        public abstract class Shape
        {
            public abstract double Area();

            public virtual string Label() => "shape";
        }

        public partial class Square : Shape, IPriced
        {
            private readonly double side;

            private readonly System.Collections.Generic.List<string> log = new();

            public Square(double side) { this.side = side; }

            public override double Area() => side * side;

            public int Price() { return 3; }

            public double Doubled() { return side * 2; }

            public void Record() { log.Add("x"); }

            public void Clear() => log.Clear();

            public string Named(Color color) => color switch { Color.Red => "red", Color.Green => "green", _ => "none" };

            public string Picked(bool big) => big ? "large" : "small";

            public string Spelled(Color color)
            {
                switch (color)
                {
                    case Color.Red: return "red";
                    default: return "other";
                }
            }

            public string Computed(Color color) => color switch { Color.Red => color.ToString(), _ => "none" };

            public double Worked()
            {
                var total = side;
                total += side;
                return total;
            }

            public double Unfinished() => throw new System.NotImplementedException();

            public double Pending()
            {
                throw new System.NotImplementedException();
            }

            public double Refused() => throw new System.InvalidOperationException();

            partial void Changed();
        }
        CS;

    /**
     * @var array<string, NodeMatch>
     */
    private array $members = [];

    protected function setUp(): void
    {
        if (Bridge::located()->isNone()) {
            $this->markTestSkipped('the .NET SDK is not installed, so there is no bridge to read C# with');
        }

        foreach (Codebase::fromString(self::SOURCE, 'Square.cs')->whereFunction()->get() as $member) {
            $this->members[$member->name() ?: $member->node->kind] ??= $member;
        }
    }

    public function test_a_constructor_is_a_constructor(): void
    {
        $this->assertTrue($this->members['Square']->isConstructorDeclaration());
        $this->assertFalse($this->members['Worked']->isConstructorDeclaration());
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function returns(): array
    {
        return [
            'a block that only returns' => ['Doubled', true],
            'an expression body' => ['Area', true],
            'a void expression body' => ['Clear', false],
            'a body of three statements' => ['Worked', false],
        ];
    }

    #[DataProvider('returns')]
    public function test_a_sole_return_is_a_block_or_an_expression_body_that_hands_back_a_value(string $member, bool $sole): void
    {
        $this->assertSame($sole, $this->members[$member]->isSoleReturnExpression());
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function statements(): array
    {
        return [
            'a block of one call' => ['Record', true],
            'a void expression body' => ['Clear', true],
            'an expression body that returns' => ['Area', false],
        ];
    }

    #[DataProvider('statements')]
    public function test_a_sole_expression_statement_is_a_block_or_a_void_expression_body(string $member, bool $sole): void
    {
        $this->assertSame($sole, $this->members[$member]->isSoleExpressionStatement());
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function lookups(): array
    {
        return [
            'a switch expression of constants' => ['Named', true],
            'a conditional of constants' => ['Picked', true],
            'a switch statement returning constants' => ['Spelled', true],
            'a switch expression with a call in an arm' => ['Computed', false],
            'a computed body' => ['Worked', false],
        ];
    }

    #[DataProvider('lookups')]
    public function test_a_lookup_table_answers_only_with_constants(string $member, bool $lookup): void
    {
        $this->assertSame($lookup, $this->members[$member]->isLiteralLookup());
    }

    public function test_a_member_without_a_body_is_no_function(): void
    {
        $this->assertArrayNotHasKey('Changed', $this->members, 'a partial with no body');
        $this->assertTrue($this->members['Area']->isOverride(), 'the Area found is the one with a body, not the abstract one');
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function stubs(): array
    {
        return [
            'an expression body that throws NotImplementedException' => ['Unfinished', true],
            'a block that throws NotImplementedException' => ['Pending', true],
            'a body that throws anything else' => ['Refused', false],
            'a computed body' => ['Worked', false],
        ];
    }

    #[DataProvider('stubs')]
    public function test_a_stub_only_says_it_is_not_written_yet(string $member, bool $stub): void
    {
        $this->assertSame($stub, $this->members[$member]->isStub());
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function overrides(): array
    {
        return [
            'an override of an abstract member' => ['Area', true],
            'an interface implementation' => ['Price', true],
            'a member of its own' => ['Worked', false],
        ];
    }

    #[DataProvider('overrides')]
    public function test_an_override_or_implementation_is_read_from_the_compiler(string $member, bool $override): void
    {
        $this->assertSame($override, $this->members[$member]->isOverride());
    }
}
