<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\NullForgivenDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `!` on what is declared nullable — a field, a local, a parameter, a method's return — silences the
 * compiler and is flagged; `= null!` initialising a member, and `!` on what is not declared nullable,
 * are not.
 */
final class NullForgivenDetectorTest extends TestCase
{
    use NeedsTheBridge;

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function members(): array
    {
        return [
            'a nullable field' => ['public int Length() => name!.Length;', 1],
            'a nullable parameter' => ['public int Size(string? text) => text!.Length;', 1],
            'a method returning T?' => ['public int Found() => Find()!.Length;', 1],
            'a nullable local' => ['public int Local() { string? kept = Find(); return kept!.Length; }', 1],
            'a null! initialiser' => ['public string Required { get; set; } = null!;', 0],
            'a value not declared nullable' => ['public int Sure(string text) => text!.Length;', 0],
        ];
    }

    #[DataProvider('members')]
    public function test_flags_a_null_forgiving_bang_on_a_nullable_value(string $member, int $flagged): void
    {
        $source = "#nullable enable\npublic class Names\n{\n    private string? name;\n\n    private string? Find() => name;\n\n    {$member}\n}\n";

        $this->assertCount($flagged, new NullForgivenDetector()->find(Codebase::fromString($source, 'Names.cs')), $source);
    }
}
