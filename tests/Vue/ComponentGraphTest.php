<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Vue;

use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Vue\ComponentGraph;
use PHPUnit\Framework\TestCase;

final class ComponentGraphTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-graph-' . bin2hex(random_bytes(4));

        $parent = <<<'VUE'
            <script setup lang="ts">
            import Child from './Child.vue';
            </script>
            <template>
                <Child :total="order.sum" :label="name" @click="go" />
                <div>not a component</div>
            </template>
            VUE;

        foreach ([
            'package.json' => '{}',
            'resources/js/Parent.vue' => $parent,
            'resources/js/Child.vue' => '<template><span/></template>',
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

    public function test_indexes_who_renders_a_component_and_the_props_bound_there(): void
    {
        $graph = ComponentGraph::of(Codebase::scan($this->root . '/resources/js'));

        $usages = $graph->usagesOf($this->root . '/resources/js/Child.vue');

        $this->assertCount(1, $usages, 'Child is rendered once, by Parent');
        $this->assertSame(realpath($this->root . '/resources/js/Parent.vue'), realpath($usages[0]->parent->path));
        $this->assertSame(['total', 'label'], array_keys($usages[0]->bindings), 'the @click event is not a prop');
        $this->assertSame('order.sum', $usages[0]->bindings['total']->source());
    }

    public function test_a_component_no_one_renders_has_no_usages(): void
    {
        $graph = ComponentGraph::of(Codebase::scan($this->root . '/resources/js'));

        $this->assertSame([], $graph->usagesOf($this->root . '/resources/js/Parent.vue'));
    }
}
