<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\SwallowedExceptionDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A `catch` that takes every failure — no type, or `Exception` however it is spelled — and hands back
 * nothing is flagged; one that names or filters the failure it expects, or does something with it, is
 * a decision.
 */
final class SwallowedExceptionDetectorTest extends TestCase
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
            'a bare catch that is empty' => ['catch { }', 1],
            'catch Exception returning null' => ['catch (Exception) { return null; }', 1],
            'catch System.Exception returning an empty list' => ['catch (System.Exception error) { return []; }', 1],
            'catch Exception returning Array.Empty' => ['catch (Exception) { return Array.Empty<string>(); }', 1],
            'a named failure' => ['catch (IOException) { return null; }', 0],
            'a filtered catch' => ['catch (Exception error) when (error is IOException) { return null; }', 0],
            'a catch that rethrows' => ['catch (Exception) { throw; }', 0],
            'a catch that does something' => ['catch (Exception error) { Console.Error.WriteLine(error); return null; }', 0],
        ];
    }

    #[DataProvider('catches')]
    public function test_flags_a_catch_that_makes_every_failure_vanish(string $catch, int $flagged): void
    {
        $source = "using System;\nusing System.IO;\n\npublic class Loader\n{\n    public string[]? Load(string path)\n    {\n        try\n        {\n            return File.ReadAllLines(path);\n        }\n        {$catch}\n    }\n}\n";

        $this->assertCount($flagged, new SwallowedExceptionDetector()->find(Codebase::fromString($source, 'Loader.cs')), $source);
    }
}
