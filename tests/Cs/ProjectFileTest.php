<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cs;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\Tests\Concerns\TemporaryFolder;
use PHPUnit\Framework\TestCase;

/**
 * An unrestored project is read as MSBuild composes it: what a solution states once in its
 * Directory.Build.props — the target framework, implicit usings — holds for every project beneath it,
 * so a file that relies on an implicit using still resolves its calls.
 */
final class ProjectFileTest extends TestCase
{
    use NeedsTheBridge;
    use TemporaryFolder;

    protected function setUp(): void
    {
        $this->requireTheBridge();

        mkdir("{$this->root}/src/Shop", 0777, true);
        file_put_contents("{$this->root}/Directory.Build.props", <<<'XML'
            <Project>
              <PropertyGroup>
                <TargetFramework>net8.0</TargetFramework>
                <ImplicitUsings>enable</ImplicitUsings>
              </PropertyGroup>
            </Project>
            XML);
        file_put_contents("{$this->root}/src/Shop/Shop.csproj", "<Project Sdk=\"Microsoft.NET.Sdk\" />\n");
        file_put_contents("{$this->root}/src/Shop/Basket.cs", <<<'CS'
            namespace Shop;

            public class Basket
            {
                private readonly List<int> prices = [];

                public void Add(int price) => prices.Add(price);
            }
            CS);
    }

    public function test_an_implicit_using_set_in_directory_build_props_resolves_the_call(): void
    {
        $calls = Codebase::scan("{$this->root}/src")->whereCall()->get();

        $this->assertSame(['global::System.Collections.Generic.List<global::System.Int32>'], array_map(static fn (NodeMatch $call): ?string => $call->node->target?->type, $calls));
    }
}
