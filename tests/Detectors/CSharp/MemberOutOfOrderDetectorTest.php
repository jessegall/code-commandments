<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\MemberOutOfOrderDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A constant declared below a field or a stored property reads the top of the type out of order; constants
 * first, then fields and stored properties, is fine.
 */
final class MemberOutOfOrderDetectorTest extends TestCase
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
            'a const below a field' => ['private int count; private const int Limit = 3;', 1],
            'a static readonly below a property' => ['public string Name { get; init; } = ""; private static readonly TimeSpan Timeout = TimeSpan.FromSeconds(5);', 1],
            'constants first' => ['private const int Limit = 3; private static readonly TimeSpan Timeout = TimeSpan.FromSeconds(5); private int count; public string Name { get; init; } = "";', 0],
            'a const below a static field' => ['private static int created; private const int Limit = 3;', 0],
        ];
    }

    #[DataProvider('bodies')]
    public function test_flags_a_constant_below_instance_state(string $body, int $flagged): void
    {
        $source = "using System;\npublic sealed class Client\n{\n    {$body}\n}\n";
        $this->assertCount($flagged, new MemberOutOfOrderDetector()->find(Codebase::fromString($source, 'Client.cs')), $source);
    }
}
