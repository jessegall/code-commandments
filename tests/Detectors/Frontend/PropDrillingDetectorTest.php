<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Frontend;

use JesseGall\CodeCommandments\Detectors\Frontend\PropDrillingDetector;
use JesseGall\CodeCommandments\Vue\Codebase;
use PHPUnit\Framework\TestCase;

final class PropDrillingDetectorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-drill-' . bin2hex(random_bytes(4));

        // Panel forwards `user` to Card (which pipes it onward — a chain) and `thing` to Used
        // (which renders it — composition). Card forwards `user` to a leaf; Used consumes it.
        foreach ([
            'package.json' => '{}',
            'resources/js/Panel.vue' => <<<'VUE'
                <script setup lang="ts">
                import Card from './Card.vue';
                import Used from './Used.vue';
                defineProps<{ user: User; thing: Thing }>();
                </script>
                <template>
                  <div>
                    <Card :user="user" />
                    <Used :value="thing" />
                  </div>
                </template>
                VUE,
            'resources/js/Card.vue' => <<<'VUE'
                <script setup lang="ts">
                import Avatar from './Avatar.vue';
                defineProps<{ user: User }>();
                </script>
                <template><Avatar :profile="user" /></template>
                VUE,
            'resources/js/Used.vue' => <<<'VUE'
                <script setup lang="ts">
                defineProps<{ value: Thing }>();
                </script>
                <template><span>{{ value.label }}</span></template>
                VUE,
            'resources/js/Avatar.vue' => '<template><img /></template>',
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

    public function test_flags_a_prop_piped_through_a_chain_but_not_one_a_child_consumes(): void
    {
        $found = new PropDrillingDetector()->find(Codebase::scan($this->root . '/resources/js'));

        // Only Panel's `<Card :user="user" />` is drilling — Card pipes `user` onward. The
        // `<Used :value="thing" />` forward is composition (Used renders it), so it's spared.
        $this->assertCount(1, $found);
        $this->assertSame('Card', $found[0]->tag);
        $this->assertStringEndsWith('Panel.vue', $found[0]->sfc->path);
    }

    public function test_does_not_flag_a_prop_the_parent_also_reads(): void
    {
        file_put_contents(
            $this->root . '/resources/js/Panel.vue',
            "<script setup lang=\"ts\">\nimport Card from './Card.vue';\ndefineProps<{ user: User }>();\n</script>\n"
            . "<template><div><h1>{{ user.name }}</h1><Card :user=\"user\" /></div></template>"
        );

        $found = new PropDrillingDetector()->find(Codebase::scan($this->root . '/resources/js'));

        $this->assertSame([], $found, 'Panel reads user (user.name), so it is not a conduit');
    }
}
