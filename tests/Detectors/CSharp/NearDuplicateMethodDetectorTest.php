<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\Detectors\CSharp\NearDuplicateMethodDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\TestCase;

/**
 * Two C# bodies with one skeleton that differ only in their names and literals are one method waiting
 * for a parameter; a byte-identical copy is the exact rule's, a different call is different code, and
 * shapes alike by construction — an override, a lookup table, a constructor — are left alone.
 */
final class NearDuplicateMethodDetectorTest extends TestCase
{
    use NeedsTheBridge;

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    public function test_flags_two_bodies_that_differ_only_in_a_literal_and_their_names(): void
    {
        $found = $this->findIn(self::reader('Orders', 'orders.csv', 'Read') . self::reader('Invoices', 'invoices.csv', 'Load'));

        $this->assertSame(['MethodDeclaration Read', 'MethodDeclaration Load'], $found);
    }

    public function test_leaves_byte_identical_copies_to_the_exact_rule(): void
    {
        $this->assertSame([], $this->findIn(self::reader('Orders', 'orders.csv', 'Read') . self::reader('Invoices', 'orders.csv', 'Read')));
    }

    public function test_a_different_call_is_different_code(): void
    {
        $other = str_replace('Trim()', 'ToUpperInvariant()', self::reader('Invoices', 'invoices.csv', 'Load'));

        $this->assertSame([], $this->findIn(self::reader('Orders', 'orders.csv', 'Read') . $other));
    }

    public function test_leaves_an_override_to_its_contract(): void
    {
        $base = "public abstract class Reader\n{\n    public abstract List<string> Read(string[] lines);\n}\n\n";
        $overriding = str_replace(['public class Orders', 'public List<string> Read'], ['public class Orders : Reader', 'public override List<string> Read'], self::reader('Orders', 'orders.csv', 'Read'));

        $this->assertSame([], $this->findIn($base . $overriding . self::reader('Invoices', 'invoices.csv', 'Load')));
    }

    /**
     * A class whose one method reads $file's rows — the skeleton every test here varies.
     */
    private static function reader(string $class, string $file, string $method): string
    {
        return <<<CS
            public class {$class}
            {
                public List<string> {$method}(string[] lines)
                {
                    var rows = new List<string>();
                    foreach (var line in lines)
                    {
                        if (line.StartsWith("#") || line.Length == 0) { continue; }
                        rows.Add(line.Trim() + " ({$file})");
                    }
                    return rows;
                }
            }

            CS;
    }

    /**
     * @return list<string>
     */
    private function findIn(string $source): array
    {
        return array_map(static fn (NodeMatch $match): string => $match->scope(), new NearDuplicateMethodDetector()->find(Codebase::fromString("using System.Collections.Generic;\n\n{$source}", 'Readers.cs')));
    }
}
