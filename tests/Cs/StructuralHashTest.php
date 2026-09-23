<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cs;

use JesseGall\CodeCommandments\Cs\Bridge;
use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A C# method's body fingerprint is blind to formatting, comments, attributes and the name it is
 * declared under — two methods doing the same thing are the same code. The SHAPE fingerprint also blanks
 * local names and string, number and character literals, and keeps what is called, which members are
 * read and which types are made.
 */
final class StructuralHashTest extends TestCase
{
    private const string SOURCE = <<<'CS'
        using System.Collections.Generic;

        public class Line { public int Price; public int Quantity; public int Weight; }

        public class Ledger
        {
            public int Total(List<Line> lines)
            {
                var total = 0;
                foreach (var line in lines) { total += line.Price * line.Quantity; }
                return total;
            }

            [System.Obsolete("kept for callers")]
            public int Reformatted(List<Line> lines)
            {
                var total = 0; // running total
                foreach (var line in lines)
                {
                    total += line.Price
                        * line.Quantity;
                }
                return total;
            }

            public int Renamed(List<Line> lines)
            {
                var sum = 0;
                foreach (var entry in lines) { sum += entry.Price * entry.Quantity; }
                return sum;
            }

            public int OtherMember(List<Line> lines)
            {
                var total = 0;
                foreach (var line in lines) { total += line.Price * line.Weight; }
                return total;
            }

            public string Greeting() => Format("hello", 1);

            public string Farewell() => Format("goodbye", 2);

            public string Shouted() => Upper("hello", 1);

            public int FirstOver(List<int> ids, int limit)
            {
                foreach (var id in ids) { if (id > limit) { return id; } }
                return 0;
            }

            public int FirstOverUnbraced(List<int> ids, int limit)
            {
                foreach (var id in ids)
                    if (id > limit) return id;
                return 0;
            }

            public ICollection<int> Listed() => new List<int>();

            public ICollection<int> Setted() => new HashSet<int>();

            private static string Format(string word, int times) => word;

            private static string Upper(string word, int times) => word;
        }
        CS;

    /**
     * @var array<string, NodeMatch>
     */
    private array $methods = [];

    protected function setUp(): void
    {
        if (Bridge::located()->isNone()) {
            $this->markTestSkipped('the .NET SDK is not installed, so there is no bridge to read C# with');
        }

        foreach (Codebase::fromString(self::SOURCE, 'Ledger.cs')->whereMethodDeclaration()->get() as $method) {
            $this->methods[$method->name()] = $method;
        }
    }

    public function test_formatting_comments_and_attributes_do_not_change_the_body_hash(): void
    {
        $this->assertSame($this->methods['Total']->bodyHash(), $this->methods['Reformatted']->bodyHash());
    }

    public function test_braces_around_a_single_statement_do_not_change_the_body_hash(): void
    {
        $this->assertSame($this->methods['FirstOver']->bodyHash(), $this->methods['FirstOverUnbraced']->bodyHash());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function blanked(): array
    {
        return [
            'renamed locals' => ['Total', 'Renamed'],
            'different literals' => ['Greeting', 'Farewell'],
        ];
    }

    #[DataProvider('blanked')]
    public function test_what_normalising_blanks_changes_the_body_but_not_the_shape(string $one, string $other): void
    {
        $this->assertNotSame($this->methods[$one]->bodyHash(), $this->methods[$other]->bodyHash());
        $this->assertSame($this->methods[$one]->shapeHash(), $this->methods[$other]->shapeHash());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function kept(): array
    {
        return [
            'a different member read' => ['Total', 'OtherMember'],
            'a different method called' => ['Greeting', 'Shouted'],
            'a different type made' => ['Listed', 'Setted'],
        ];
    }

    #[DataProvider('kept')]
    public function test_what_a_body_does_changes_the_shape(string $one, string $other): void
    {
        $this->assertNotSame($this->methods[$one]->shapeHash(), $this->methods[$other]->shapeHash());
    }

    public function test_the_body_weight_counts_statements_and_expressions(): void
    {
        $this->assertSame($this->methods['Total']->bodyNodeCount(), $this->methods['Reformatted']->bodyNodeCount());
        $this->assertGreaterThan($this->methods['Greeting']->bodyNodeCount(), $this->methods['Total']->bodyNodeCount());
    }
}
