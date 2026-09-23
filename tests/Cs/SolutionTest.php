<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cs;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use PHPUnit\Framework\TestCase;

/**
 * A solution is read as MSBuild builds it: each project compiled on its own, so a name one project
 * declares never collides with another project's, and what a referenced project reaches — the projects
 * it references in turn — is the referencing project's too.
 */
final class SolutionTest extends TestCase
{
    use NeedsTheBridge;

    private string $root;

    protected function setUp(): void
    {
        $this->requireTheBridge();

        $this->root = sys_get_temp_dir() . '/cc-solution-' . uniqid();
        $this->write('Directory.Build.props', "<Project>\n  <PropertyGroup>\n    <TargetFramework>net8.0</TargetFramework>\n  </PropertyGroup>\n</Project>\n");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_two_projects_declaring_one_name_each_resolve_their_own(): void
    {
        $this->write('Shop/Shop.csproj', "<Project Sdk=\"Microsoft.NET.Sdk\">\n  <ItemGroup>\n    <Using Include=\"Shop.Model\" />\n  </ItemGroup>\n</Project>\n");
        $this->write('Shop/Model/Item.cs', "namespace Shop.Model;\n\npublic class Item { public int Weight() => 1; }\n");
        $this->write('Shop/Scale.cs', "public class Scale { public int Read(Item item) => item.Weight(); }\n");
        $this->write('Admin/Admin.csproj', "<Project Sdk=\"Microsoft.NET.Sdk\">\n  <ItemGroup>\n    <Using Include=\"Admin.Model\" />\n  </ItemGroup>\n</Project>\n");
        $this->write('Admin/Model/Item.cs', "namespace Admin.Model;\n\npublic class Item { public int Price() => 2; }\n");
        $this->write('Admin/Till.cs', "public class Till { public int Ring(Item item) => item.Price(); }\n");

        $this->assertSame(['global::Admin.Model.Item', 'global::Shop.Model.Item'], $this->callTargets());
    }

    public function test_a_project_reaches_what_its_referenced_projects_reference(): void
    {
        $this->write('Domain/Domain.csproj', "<Project Sdk=\"Microsoft.NET.Sdk\" />\n");
        $this->write('Domain/Order.cs', "namespace Domain;\n\npublic class Order { public int Total() => 3; }\n");
        $this->write('App/App.csproj', "<Project Sdk=\"Microsoft.NET.Sdk\">\n  <ItemGroup>\n    <ProjectReference Include=\"..\\Domain\\Domain.csproj\" />\n  </ItemGroup>\n</Project>\n");
        $this->write('Web/Web.csproj', "<Project Sdk=\"Microsoft.NET.Sdk\">\n  <ItemGroup>\n    <ProjectReference Include=\"..\\App\\App.csproj\" />\n  </ItemGroup>\n</Project>\n");
        $this->write('Web/Page.cs', "using Domain;\n\npublic class Page { public int Show(Order order) => order.Total(); }\n");

        $this->assertSame(['global::Domain.Order'], $this->callTargets());
    }

    /**
     * The type each call in the solution resolves into, in order.
     *
     * @return list<string|null>
     */
    private function callTargets(): array
    {
        $targets = array_map(static fn (NodeMatch $call): ?string => $call->node->target?->type, Codebase::scan($this->root)->whereCall()->get());
        sort($targets);

        return $targets;
    }

    private function write(string $path, string $contents): void
    {
        if (! is_dir(dirname("{$this->root}/{$path}"))) {
            mkdir(dirname("{$this->root}/{$path}"), 0777, true);
        }
        file_put_contents("{$this->root}/{$path}", $contents);
    }
}
