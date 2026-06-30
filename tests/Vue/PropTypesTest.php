<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Vue;

use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Vue\ComponentGraph;
use JesseGall\CodeCommandments\Vue\PropTypes;
use PHPUnit\Framework\TestCase;

final class PropTypesTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-flow-' . bin2hex(random_bytes(4));

        // A page typed from the server, passing a value down through TWO untyped children.
        $page = <<<'VUE'
            <script setup lang="ts">
            import type { WorkflowData } from './types/generated';
            import Card from './Card.vue';
            defineProps<{ workflow: WorkflowData }>();
            </script>
            <template><Card :item="workflow" /></template>
            VUE;

        $card = <<<'VUE'
            <script setup lang="ts">
            import Row from './Row.vue';
            </script>
            <template><Row :record="item" /></template>
            VUE;

        foreach ([
            'package.json' => '{}',
            'resources/js/types/generated.ts' => 'export type WorkflowData = { id: string; title: string; };',
            'resources/js/Pages/Show.vue' => str_replace("'./Card.vue'", "'../Card.vue'", $page),
            'resources/js/Card.vue' => $card,
            'resources/js/Row.vue' => '<template><div>{{ record.title }}</div></template>',
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

    public function test_a_grandchild_prop_resolves_to_the_server_type_through_the_page(): void
    {
        $codebase = Codebase::scan($this->root . '/resources/js');
        $flow = new PropTypes(ComponentGraph::of($codebase));

        $row = $this->component($codebase, 'Row.vue');

        // Row's `record` has no local type. The flow climbs: record ← Card.item ← Show.workflow,
        // and Show declares `workflow: WorkflowData`. Everything resolves from the top.
        $this->assertSame('WorkflowData', $flow->typeOf($row, 'record'));
    }

    public function test_an_untraceable_prop_yields_null(): void
    {
        $codebase = Codebase::scan($this->root . '/resources/js');
        $flow = new PropTypes(ComponentGraph::of($codebase));

        $this->assertNull($flow->typeOf($this->component($codebase, 'Row.vue'), 'nonexistent'));
    }

    private function component(Codebase $codebase, string $endsWith): \JesseGall\CodeCommandments\Vue\Sfc
    {
        foreach ($codebase->components() as $component) {
            if (str_ends_with($component->path, $endsWith)) {
                return $component;
            }
        }

        $this->fail("component {$endsWith} not found");
    }
}
