<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Vue;

use JesseGall\CodeCommandments\Vue\ModuleResolver;
use PHPUnit\Framework\TestCase;

final class ModuleResolverTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-modres-' . bin2hex(random_bytes(4));

        foreach ([
            'resources/js/components/Card.vue' => '<template/>',
            'resources/js/composables/useX.ts' => 'export const useX = () => {};',
            'resources/js/composables/index.ts' => "export * from './useX';",
            'resources/js/pages/Home.vue' => '<template/>',
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

    public function test_resolves_a_relative_import_with_an_inferred_extension(): void
    {
        $resolver = new ModuleResolver();
        $from = $this->root . '/resources/js/pages/Home.vue';

        $this->assertSame(
            realpath($this->root . '/resources/js/components/Card.vue'),
            $resolver->resolve($from, '../components/Card'),
        );
    }

    public function test_resolves_an_aliased_import_longest_prefix_first(): void
    {
        $js = $this->root . '/resources/js';
        $resolver = new ModuleResolver([
            '@app' => $js,
            '@app/composables' => $js . '/composables', // longer — must win
        ]);
        $from = $this->root . '/resources/js/pages/Home.vue';

        $this->assertSame(
            realpath($js . '/composables/useX.ts'),
            $resolver->resolve($from, '@app/composables/useX'),
        );
    }

    public function test_resolves_a_barrel_to_its_index(): void
    {
        $js = $this->root . '/resources/js';
        $resolver = new ModuleResolver(['@app' => $js]);
        $from = $this->root . '/resources/js/pages/Home.vue';

        $this->assertSame(
            realpath($js . '/composables/index.ts'),
            $resolver->resolve($from, '@app/composables'),
        );
    }

    public function test_a_bare_or_missing_import_is_unresolved(): void
    {
        $resolver = new ModuleResolver(['@app' => $this->root . '/resources/js']);
        $from = $this->root . '/resources/js/pages/Home.vue';

        $this->assertNull($resolver->resolve($from, 'vue'), 'a node_modules import is out of scope');
        $this->assertNull($resolver->resolve($from, './Nope'), 'a missing relative file is null');
    }
}
