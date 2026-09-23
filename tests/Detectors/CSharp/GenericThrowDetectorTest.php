<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\GenericThrowDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * An exception that names no failure, thrown with a message written at the throw, is flagged whether
 * it is thrown as a statement or an expression; a named exception, the argument family, and a generic
 * one thrown without a message are not.
 */
final class GenericThrowDetectorTest extends TestCase
{
    use NeedsTheBridge;

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function throws(): array
    {
        return [
            'new Exception with a message' => ['throw new Exception($"no carrier {name}");', 1],
            'new InvalidOperationException with a message' => ['throw new InvalidOperationException("not open");', 1],
            'a throw expression' => ['var found = name.Length > 0 ? name : throw new System.Exception("empty");', 1],
            'a named exception' => ['throw new UnknownCarrier(name);', 0],
            'the argument family' => ['throw new ArgumentException("empty", nameof(name));', 0],
            'a generic exception without a message' => ['throw new InvalidOperationException();', 0],
        ];
    }

    #[DataProvider('throws')]
    public function test_flags_a_generic_exception_described_at_the_throw(string $throw, int $flagged): void
    {
        $source = "using System;\n\npublic sealed class UnknownCarrier(string name) : InvalidOperationException(name);\n\npublic class Carriers\n{\n    public void Pick(string name)\n    {\n        {$throw}\n    }\n}\n";

        $this->assertCount($flagged, new GenericThrowDetector()->find(Codebase::fromString($source, 'Carriers.cs')), $source);
    }
}
