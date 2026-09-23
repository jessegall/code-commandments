<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\InlineThrowDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A `?? throw` buried in a call's argument or used as a call's receiver hides a guard inside the work; one
 * that assigns or returns its value reads as the guard it is.
 */
final class InlineThrowDetectorTest extends TestCase
{
    use NeedsTheBridge;

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function bodies(): array
    {
        return [
            'handed to a call' => ['Console.WriteLine(name ?? throw new ArgumentNullException(nameof(name)));', 1],
            'called on' => ['Console.WriteLine((name ?? throw new ArgumentNullException(nameof(name))).Trim());', 1],
            'an assignment guard' => ['var given = name ?? throw new ArgumentNullException(nameof(name)); Console.WriteLine(given);', 0],
            'a returned guard' => ['Console.WriteLine(Must(name));', 0],
        ];
    }

    #[DataProvider('bodies')]
    public function test_flags_a_throw_buried_in_the_work(string $body, int $flagged): void
    {
        $source = "using System;\n\npublic class Greeter\n{\n    public void Greet(string? name)\n    {\n        {$body}\n    }\n\n    private static string Must(string? name) => name ?? throw new ArgumentNullException(nameof(name));\n}\n";
        $this->assertCount($flagged, new InlineThrowDetector()->find(Codebase::fromString($source, 'Greeter.cs')), $source);
    }
}
