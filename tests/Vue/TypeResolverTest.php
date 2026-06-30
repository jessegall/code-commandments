<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Vue;

use JesseGall\CodeCommandments\Vue\Script;
use JesseGall\CodeCommandments\Vue\TypeResolver;
use PHPUnit\Framework\TestCase;

final class TypeResolverTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-typeres-' . bin2hex(random_bytes(4));

        foreach ([
            'package.json' => '{}', // marks the project root for ModuleResolver
            'resources/js/widget.ts' => "import type { FooData } from './types';\nconst x = 1;",
            'resources/js/types/index.ts' => "export * from './generated';\nexport type Local = { a: string };",
            'resources/js/types/generated.ts' => 'export type FooData = { id: string; count: number; ready(): void; };',
        ] as $relative => $body) {
            $path = $this->root . '/' . $relative;
            @mkdir(dirname($path), 0o777, true);
            file_put_contents($path, $body);
        }
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($it as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }

        @rmdir($this->root);
    }

    public function test_resolves_an_imported_type_through_a_barrel_reexport(): void
    {
        $file = $this->root . '/resources/js/widget.ts';
        $script = new Script((string) file_get_contents($file));

        // widget.ts imports FooData from './types' (a barrel that `export *`s './generated',
        // where FooData actually lives). The trace follows import → barrel → declaration.
        $this->assertSame(
            ['id' => 'string', 'count' => 'number', 'ready' => '() => void'],
            TypeResolver::fields('FooData', $file, $script),
        );
    }

    public function test_a_locally_declared_type_is_returned_directly(): void
    {
        $file = $this->root . '/resources/js/types/index.ts';
        $script = new Script((string) file_get_contents($file));

        $this->assertSame(['a' => 'string'], TypeResolver::fields('Local', $file, $script));
    }

    public function test_an_unreachable_type_resolves_to_empty(): void
    {
        $file = $this->root . '/resources/js/widget.ts';
        $script = new Script((string) file_get_contents($file));

        $this->assertSame([], TypeResolver::fields('Nonexistent', $file, $script));
    }
}
