<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\BareStatePredicateDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A `bool` about the object's own state named as a claim — `Binds()`, `Spins` — where a question belongs; a
 * question, a check against an argument, and a contract's own name are fine.
 */
final class BareStatePredicateDetectorTest extends TestCase
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
            'a method' => ['public bool Binds() => bound;', 1],
            'a property' => ['public bool Spins => bound;', 1],
            'a question' => ['public bool IsBound() => bound;', 0],
            'a check against an argument' => ['public bool Contains(string key) => bound && key != "";', 0],
            'not a bool' => ['public int Counts() => 1;', 0],
            'an override' => ['public override bool Equals(object? other) => bound;', 0],
        ];
    }

    #[DataProvider('members')]
    public function test_flags_a_bool_about_state_named_as_a_claim(string $member, int $flagged): void
    {
        $source = "public sealed class Node\n{\n    private bool bound;\n    {$member}\n}\n";
        $this->assertCount($flagged, new BareStatePredicateDetector()->find(Codebase::fromString($source, 'Node.cs')), $source);
    }
}
