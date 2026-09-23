<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\CancelledCoalesceDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A `??` whose fallback is then compared against that same value cancels itself: absent and empty take one
 * branch without anyone saying so. A real fallback, or one that is not compared back, is fine.
 */
final class CancelledCoalesceDetectorTest extends TestCase
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
            'a blank compared back' => ['public bool Named(string? name) => (name ?? "") != "";', 1],
            'a zero compared back' => ['public bool Empty(int? count) => (count ?? 0) == 0;', 1],
            'the fallback on the right' => ['public bool Unset(string? code) => string.Empty == (code ?? string.Empty);', 1],
            'a real fallback compared' => ['public bool Named(string? name) => (name ?? "anonymous") != "";', 0],
            'a fallback used, not compared' => ['public string Shout(string? name) => (name ?? "") + "!";', 0],
            'a null test' => ['public bool Named(string? name) => name is not null && name != "";', 0],
        ];
    }

    #[DataProvider('members')]
    public function test_flags_a_fallback_compared_against_itself(string $member, int $flagged): void
    {
        $source = "using System;\n\npublic class Checks\n{\n    {$member}\n}\n";
        $this->assertCount($flagged, new CancelledCoalesceDetector()->find(Codebase::fromString($source, 'Checks.cs')), $source);
    }
}
