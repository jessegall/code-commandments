<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Support;

use JesseGall\CodeCommandments\Support\FileTree;
use PHPUnit\Framework\TestCase;

/**
 * A walk reads a project's own sources and never what an installer or an interpreter left beside
 * them — for Python a virtual environment (known by its pyvenv.cfg, whatever its name), a bytecode
 * cache and a package's build metadata. A folder merely NAMED like one of them is still source.
 */
final class FileTreeTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-file-tree-' . uniqid('', true);

        foreach (['shop', 'shop/env', 'venv/lib', 'python-3.12/lib', 'shop/__pycache__', 'lib/site-packages/requests', 'shop.egg-info', '.venv/lib'] as $dir) {
            mkdir("{$this->root}/{$dir}", 0777, true);
            file_put_contents("{$this->root}/{$dir}/module.py", "x = 1\n");
        }

        foreach (['venv', 'python-3.12'] as $environment) {
            file_put_contents("{$this->root}/{$environment}/pyvenv.cfg", "home = /usr/bin\n");
        }
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_a_python_walk_reads_only_first_party_sources(): void
    {
        $found = array_map(fn (string $path): string => substr($path, strlen($this->root) + 1), iterator_to_array(FileTree::filesIn($this->root, 'py'), false));

        sort($found);

        $this->assertSame(['shop/env/module.py', 'shop/module.py'], $found);
    }
}
