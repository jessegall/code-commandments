<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Support;

use JesseGall\CodeCommandments\Support\FileTree;
use JesseGall\CodeCommandments\Tests\Concerns\TemporaryFolder;
use PHPUnit\Framework\TestCase;

/**
 * A walk reads a project's own sources and never what an installer or an interpreter left beside
 * them — for Python a virtual environment (known by its pyvenv.cfg, whatever its name), a bytecode
 * cache and a package's build metadata; for C# the `bin`/`obj` a build writes beside its project file.
 * A folder merely NAMED like one of them is still source.
 */
final class FileTreeTest extends TestCase
{
    use TemporaryFolder;

    protected function setUp(): void
    {
        foreach (['shop', 'shop/env', 'venv/lib', 'python-3.12/lib', 'shop/__pycache__', 'lib/site-packages/requests', 'shop.egg-info', '.venv/lib', '.journal/plugins/shop'] as $dir) {
            mkdir("{$this->root}/{$dir}", 0777, true);
            file_put_contents("{$this->root}/{$dir}/module.py", "x = 1\n");
        }

        foreach (['venv', 'python-3.12'] as $environment) {
            file_put_contents("{$this->root}/{$environment}/pyvenv.cfg", "home = /usr/bin\n");
        }
    }

    /**
     * `.journal/plugins/shop` stands for a tool's installed COPY of the project, like the ~580 gitignored
     * duplicates beside agent-journal's 438 sources: a hidden folder is tooling, never walked, so a scan
     * reads exactly what git tracks (measured on agent-journal: the walk reads nothing git ignores).
     */
    public function test_a_python_walk_reads_only_first_party_sources(): void
    {
        $found = array_map(fn (string $path): string => substr($path, strlen($this->root) + 1), iterator_to_array(FileTree::filesIn($this->root, 'py'), false));

        sort($found);

        $this->assertSame(['shop/env/module.py', 'shop/module.py'], $found);
    }

    /**
     * `dotnet build` writes generated `.cs` files into `obj/` beside the `.csproj`; a `bin/` with no
     * project file beside it is a folder of scripts, and read.
     */
    public function test_a_csharp_walk_skips_the_build_output_beside_a_project_file(): void
    {
        foreach (['Api', 'Api/bin/Debug', 'Api/obj', 'tools/bin'] as $dir) {
            mkdir("{$this->root}/{$dir}", 0777, true);
            file_put_contents("{$this->root}/{$dir}/Code.cs", "class C {}\n");
        }

        file_put_contents("{$this->root}/Api/Api.csproj", "<Project Sdk=\"Microsoft.NET.Sdk\" />\n");

        $found = array_map(fn (string $path): string => substr($path, strlen($this->root) + 1), iterator_to_array(FileTree::filesIn($this->root, 'cs'), false));

        sort($found);

        $this->assertSame(['Api/Code.cs', 'tools/bin/Code.cs'], $found);
    }
}
