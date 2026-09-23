<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\WrappingWithoutCauseDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A new exception thrown from a catch must carry the caught one as its inner exception; one that drops
 * it loses the original stack trace. A rethrow and a wrap that passes the cause are fine.
 */
final class WrappingWithoutCauseDetectorTest extends TestCase
{
    use NeedsTheBridge;

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function catches(): array
    {
        return [
            'a wrap that drops the caught exception' => ['catch (IOException e) { throw new InvalidOperationException("Could not load " + e.Message); }', 1],
            'a wrap from a catch with no variable' => ['catch (FormatException) { throw new ArgumentException("Not a number"); }', 1],
            'a wrap inside an if' => ['catch (IOException e) { if (e.HResult > 0) { throw new InvalidOperationException("Could not load"); } throw; }', 1],
            'a wrap that passes the cause' => ['catch (IOException e) { throw new InvalidOperationException("Could not load", e); }', 0],
            'a rethrow' => ['catch (IOException) { Console.WriteLine("failed"); throw; }', 0],
            'a new exception thrown from a lambda defined in the catch' => ['catch (IOException e) { Action later = () => throw new InvalidOperationException("later"); later(); throw; }', 0],
        ];
    }

    #[DataProvider('catches')]
    public function test_flags_a_wrap_that_drops_the_caught_exception(string $catch, int $flagged): void
    {
        $source = "using System;\nusing System.IO;\n\npublic class Loader\n{\n    public string Load(string path)\n    {\n        try\n        {\n            return File.ReadAllText(path);\n        }\n        {$catch}\n    }\n}\n";
        $this->assertCount($flagged, new WrappingWithoutCauseDetector()->find(Codebase::fromString($source, 'Loader.cs')), $source);
    }
}
