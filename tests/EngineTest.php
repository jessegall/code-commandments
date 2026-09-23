<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests;

use JesseGall\CodeCommandments\Cs\Codebase as CSharpCodebase;
use JesseGall\CodeCommandments\Engine;
use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Py\Codebase as PythonCodebase;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use JesseGall\CodeCommandments\Vue\Codebase as VueCodebase;
use PHPUnit\Framework\TestCase;

/**
 * Which engine reads a file is a fact about its language, and an engine scans a path into its own
 * codebase — so a per-edit check or a run reaches the right rules without guessing between two.
 */
final class EngineTest extends TestCase
{
    use NeedsTheBridge;

    public function test_each_language_is_read_by_its_engine(): void
    {
        $this->assertSame(Engine::Backend, Language::Php->engine());
        $this->assertSame(Engine::Frontend, Language::Vue->engine());
        $this->assertSame(Engine::Frontend, Language::TypeScript->engine());
        $this->assertSame(Engine::Python, Language::Python->engine());
        $this->assertSame(Engine::CSharp, Language::CSharp->engine());
    }

    public function test_an_engine_scans_a_path_into_its_own_codebase(): void
    {
        $root = sys_get_temp_dir() . '/cc-engine-scan-' . uniqid();
        mkdir($root);
        file_put_contents("{$root}/orders.py", "def total():\n    return 1\n");

        $python = Engine::Python->scan($root);
        $frontend = Engine::Frontend->scan($root);

        exec('rm -rf ' . escapeshellarg($root));

        $this->assertInstanceOf(PythonCodebase::class, $python);
        $this->assertCount(1, $python->whereFunction()->get());
        $this->assertInstanceOf(VueCodebase::class, $frontend);
    }

    public function test_the_csharp_engine_scans_through_the_bridge(): void
    {
        $this->requireTheBridge();

        $root = sys_get_temp_dir() . '/cc-engine-scan-' . uniqid();
        mkdir($root);
        file_put_contents("{$root}/Orders.cs", "public class Orders\n{\n    public int Total() => 1;\n}\n");

        $csharp = Engine::CSharp->scan($root);

        exec('rm -rf ' . escapeshellarg($root));

        $this->assertInstanceOf(CSharpCodebase::class, $csharp);
        $this->assertCount(1, $csharp->whereFunction()->get());
    }
}
