<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\UnnamedVocabularyLiteralDetector;
use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A raw string handed to a parameter the codebase elsewhere fills from a named constant, where a constant of that
 * vocabulary already names the value, is the codebase contradicting itself; a string no constant names, or a slot
 * never spelled by name, is not.
 */
final class UnnamedVocabularyLiteralDetectorTest extends TestCase
{
    use NeedsTheBridge;

    private const string TYPES = "public static class Token\n{\n    public const string Colon = \":\";\n    public const string BraceOpen = \"{\";\n}\npublic sealed class Parser\n{\n    public void Expect(string token) { }\n    public void Skip(string text) { }\n}\n";

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    /**
     * @return array<string, array{string, list<int>}>
     */
    public static function calls(): array
    {
        return [
            'a raw value a constant already names' => ["parser.Expect(Token.Colon);\n        parser.Expect(\"{\");", [16]],
            'a raw value no constant names' => ["parser.Expect(Token.Colon);\n        parser.Expect(\"[\");", []],
            'a library parameter' => ["System.Console.WriteLine(Token.Colon);\n        System.Console.WriteLine(\"{\");", []],
            'a slot never spelled by name' => ["parser.Skip(\":\");\n        parser.Skip(\"{\");", []],
        ];
    }

    /**
     * @param  list<int>  $lines
     */
    #[DataProvider('calls')]
    public function test_flags_a_raw_value_a_named_vocabulary_already_names(string $calls, array $lines): void
    {
        $source = self::TYPES . "public sealed class Reader(Parser parser)\n{\n    public void Read()\n    {\n        {$calls}\n    }\n}\n";
        $found = new UnnamedVocabularyLiteralDetector()->find(Codebase::fromString($source, 'Reader.cs'));

        $this->assertSame($lines, array_map(static fn (Located $match): int => $match->line(), $found), $source);
    }
}
