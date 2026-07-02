<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Vue;

use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Vue\TypeDeclarationMatch;
use PHPUnit\Framework\TestCase;

final class TypeDeclarationQueryTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-typedecl-' . bin2hex(random_bytes(4));

        foreach ([
            'resources/js/types.ts' => "export type ProductData = {\n  id: string;\n  name: string;\n  price: number;\n};\n",
            'resources/js/Widget.vue' => "<script setup lang=\"ts\">\ninterface OrderData {\n  id: string\n  total: number\n}\nconst x = 1\n</script>\n<template><div /></template>\n",
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

    public function test_it_enumerates_types_from_both_ts_files_and_vue_scripts(): void
    {
        $matches = Codebase::scan($this->root)->whereTypeDeclaration()->get();

        $byName = [];
        foreach ($matches as $match) {
            $byName[$match->name()] = $match;
        }

        $this->assertArrayHasKey('ProductData', $byName);
        $this->assertArrayHasKey('OrderData', $byName);
        $this->assertSame(['id', 'name', 'price'], $byName['ProductData']->fields());
        $this->assertSame(['id', 'total'], $byName['OrderData']->fields());
    }

    public function test_a_vue_script_type_reports_its_file_line(): void
    {
        $matches = Codebase::scan($this->root)->whereTypeDeclaration()->get();

        $order = null;
        foreach ($matches as $match) {
            if ($match->name() === 'OrderData') {
                $order = $match;
            }
        }

        $this->assertNotNull($order);
        // `interface OrderData` sits on line 2 of Widget.vue (line 1 is `<script setup>`).
        $this->assertStringEndsWith('Widget.vue:2', $order->location());
        $this->assertSame('type OrderData', $order->scope());
    }

    public function test_the_field_floor_selector_drops_thin_types(): void
    {
        $matches = Codebase::scan($this->root)->whereTypeDeclaration()->havingAtLeastFields(3)->get();

        $names = array_map(static fn (TypeDeclarationMatch $m): string => $m->name(), $matches);

        $this->assertContains('ProductData', $names); // 3 fields
        $this->assertNotContains('OrderData', $names); // 2 fields
    }
}
