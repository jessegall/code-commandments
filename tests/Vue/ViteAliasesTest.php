<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Vue;

use JesseGall\CodeCommandments\Vue\ViteAliases;
use PHPUnit\Framework\TestCase;

final class ViteAliasesTest extends TestCase
{
    public function test_reads_the_alias_map_resolving_base_variables(): void
    {
        // Mirrors the real consumer configs: a base var traced through `resolve`, `__dirname`
        // as the root, a bare base var, and a longer prefix beside its parent.
        $config = <<<'TS'
            import path from 'node:path';
            const dir = path.dirname(fileURLToPath(import.meta.url));
            const src = path.resolve(dir, 'resources/js');
            export default defineConfig({
                plugins: [vue()],
                resolve: {
                    alias: {
                        '@app/ui': path.resolve(src, 'components/ui'),
                        '@app/composables': path.resolve(src, 'composables'),
                        '@app': src,
                        '@root': resolve(__dirname, 'resources'),
                    },
                },
            });
            TS;

        $aliases = ViteAliases::fromSource($config, '/project');

        $this->assertSame('/project/resources/js/components/ui', $aliases['@app/ui']);
        $this->assertSame('/project/resources/js/composables', $aliases['@app/composables']);
        $this->assertSame('/project/resources/js', $aliases['@app']);
        $this->assertSame('/project/resources', $aliases['@root']);
    }

    public function test_no_alias_block_yields_an_empty_map(): void
    {
        $this->assertSame([], ViteAliases::fromSource('export default defineConfig({ plugins: [] });', '/project'));
    }
}
