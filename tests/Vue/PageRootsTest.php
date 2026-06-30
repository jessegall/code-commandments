<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Vue;

use JesseGall\CodeCommandments\Vue\PageRoots;
use JesseGall\CodeCommandments\Vue\Script;
use PHPUnit\Framework\TestCase;

final class PageRootsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-pages-' . bin2hex(random_bytes(4));

        $app = <<<'TS'
            import { createInertiaApp } from '@inertiajs/vue3';
            createInertiaApp({
                resolve: (name) => resolvePageComponent(
                    `./Pages/${name}.vue`,
                    import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
                ),
            });
            TS;

        foreach ([
            'resources/js/app.ts' => $app,
            'resources/js/Pages/Home.vue' => '<template/>',
            'resources/js/Pages/Settings/Profile.vue' => '<template/>',
            'resources/js/components/Ignored.vue' => '<template/>', // not under Pages/
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

    public function test_call_string_arg_reads_the_glob_pattern_past_a_generic(): void
    {
        $script = new Script("import.meta.glob<DefineComponent>('./Pages/**/*.vue');");

        $this->assertSame('./Pages/**/*.vue', $script->callStringArg('glob'));
    }

    public function test_discovers_every_page_under_the_inertia_glob(): void
    {
        $pages = PageRoots::discover($this->root);

        $this->assertSame([
            realpath($this->root . '/resources/js/Pages/Home.vue'),
            realpath($this->root . '/resources/js/Pages/Settings/Profile.vue'),
        ], $pages, 'every .vue under Pages/, and nothing outside it');
    }
}
