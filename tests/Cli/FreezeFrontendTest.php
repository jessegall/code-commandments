<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Ast\Support\Frozen;
use JesseGall\CodeCommandments\Cli\Freeze;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Scope\Scope;
use JesseGall\CodeCommandments\Language;
use PHPUnit\Framework\TestCase;

/**
 * A Vue component and a TypeScript module freeze like a PHP file: the stamp is written in the file's
 * OWN comment syntax, at its top, and the scope reads it back. The stamp used to be a PHP `//` line
 * after line one — template text in a component — and the reader only understood PHP comments, so a
 * frozen component or module was judged anyway.
 */
final class FreezeFrontendTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/cc-freeze-frontend-' . uniqid('', true);
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function test_a_frozen_typescript_module_is_stamped_with_a_line_comment_and_out_of_scope(): void
    {
        $file = $this->write('orders.ts', "export const total = 1\n");

        $this->assertSame(0, new Freeze()->run(Input::of('freeze', [$file])));

        $this->assertStringStartsWith('// ' . Frozen::FILE_MARKER, (string) file_get_contents($file));
        $this->assertFalse(Scope::everything()->includes($file));
    }

    public function test_a_frozen_component_is_stamped_with_an_html_comment_and_out_of_scope(): void
    {
        $file = $this->write('OrderCard.vue', "<template>\n    <p>Order</p>\n</template>\n");

        $this->assertSame(0, new Freeze()->run(Input::of('freeze', [$file])));

        $this->assertStringStartsWith('<!-- ' . Frozen::FILE_MARKER, (string) file_get_contents($file));
        $this->assertFalse(Scope::everything()->includes($file));
    }

    public function test_unfreezing_a_component_restores_it(): void
    {
        $original = "<template>\n    <p>Order</p>\n</template>\n";
        $file = $this->write('OrderCard.vue', $original);

        new Freeze()->run(Input::of('freeze', [$file]));
        new Freeze()->run(Input::of('unfreeze', [$file]));

        $this->assertSame($original, file_get_contents($file));
        $this->assertTrue(Scope::everything()->includes($file));
    }

    public function test_a_frozen_python_module_is_stamped_below_its_shebang_and_out_of_scope(): void
    {
        $file = $this->write('orders.py', "#!/usr/bin/env python3\ntotal = 1\n");

        $this->assertSame(0, new Freeze()->run(Input::of('freeze', [$file])));

        $lines = explode("\n", (string) file_get_contents($file));
        $this->assertSame('#!/usr/bin/env python3', $lines[0]);
        $this->assertStringStartsWith('# ' . Frozen::FILE_MARKER, $lines[1]);
        $this->assertFalse(Scope::everything()->includes($file));
    }

    public function test_unfreezing_a_python_module_restores_it(): void
    {
        $original = "total = 1\n";
        $file = $this->write('orders.py', $original);

        new Freeze()->run(Input::of('freeze', [$file]));
        $this->assertStringStartsWith('# ' . Frozen::FILE_MARKER, (string) file_get_contents($file));
        new Freeze()->run(Input::of('unfreeze', [$file]));

        $this->assertSame($original, file_get_contents($file));
    }

    public function test_a_frozen_tag_written_by_hand_in_a_module_freezes_it(): void
    {
        $this->assertTrue(Frozen::isFrozen("/** @frozen */\nexport const total = 1\n", Language::TypeScript));
        $this->assertFalse(Frozen::isFrozen("export const note = '@frozen is only a word here'\n", Language::TypeScript));
    }

    private function write(string $name, string $source): string
    {
        file_put_contents("{$this->dir}/{$name}", $source);

        return "{$this->dir}/{$name}";
    }
}
