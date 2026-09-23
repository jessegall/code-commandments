<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Sins;

use JesseGall\CodeCommandments\Sins\Ecosystem;
use JesseGall\CodeCommandments\Tests\Concerns\TemporaryFolder;
use PHPUnit\Framework\TestCase;

/**
 * Which manifest answers "is this package installed" is the ecosystem's own knowledge — Composer's
 * installed set, a package.json, a Python project's requirements and pyproject, or the package references
 * of a .NET solution's project files — and a project whose
 * manifest cannot be read is never over-filtered.
 */
final class EcosystemTest extends TestCase
{
    use TemporaryFolder;

    public function test_pip_reads_requirements_files(): void
    {
        file_put_contents("{$this->root}/requirements.txt", "# pinned\nDjango>=4.2\nrequests[security]==2.31 ; python_version > '3.8'\n");
        file_put_contents("{$this->root}/requirements-dev.txt", "pytest\n");

        $this->assertTrue(Ecosystem::Pip->hasInstalled('django', $this->root));
        $this->assertTrue(Ecosystem::Pip->hasInstalled('requests', $this->root));
        $this->assertTrue(Ecosystem::Pip->hasInstalled('pytest', $this->root));
        $this->assertFalse(Ecosystem::Pip->hasInstalled('flask', $this->root));
    }

    public function test_pip_reads_a_pep_621_pyproject(): void
    {
        file_put_contents("{$this->root}/pyproject.toml", <<<'TOML'
            [project]
            name = "shop"
            dependencies = [
                "pydantic>=2",
                "Typing_Extensions",
            ]
            TOML);

        $this->assertTrue(Ecosystem::Pip->hasInstalled('pydantic', $this->root));
        $this->assertTrue(Ecosystem::Pip->hasInstalled('typing-extensions', $this->root));
        $this->assertFalse(Ecosystem::Pip->hasInstalled('shop', $this->root));
    }

    public function test_pip_reads_poetry_dependencies(): void
    {
        file_put_contents("{$this->root}/pyproject.toml", <<<'TOML'
            [tool.poetry.dependencies]
            python = "^3.12"
            fastapi = "^0.110"

            [tool.poetry.group.dev.dependencies]
            ruff = "*"
            TOML);

        $this->assertTrue(Ecosystem::Pip->hasInstalled('fastapi', $this->root));
        $this->assertTrue(Ecosystem::Pip->hasInstalled('ruff', $this->root));
        $this->assertFalse(Ecosystem::Pip->hasInstalled('django', $this->root));
    }

    public function test_without_a_manifest_every_package_counts_as_present(): void
    {
        $this->assertTrue(Ecosystem::Pip->hasInstalled('anything', $this->root));
        $this->assertTrue(Ecosystem::Npm->hasInstalled('anything', $this->root));
        $this->assertTrue(Ecosystem::NuGet->hasInstalled('anything', $this->root));
    }

    public function test_npm_reads_package_json(): void
    {
        file_put_contents("{$this->root}/package.json", json_encode(['devDependencies' => ['vue' => '^3']]));

        $this->assertTrue(Ecosystem::Npm->hasInstalled('vue', $this->root));
        $this->assertFalse(Ecosystem::Npm->hasInstalled('react', $this->root));
    }

    /**
     * A solution references packages from every project under it, from a `Directory.Build.props` that
     * hands one to them all, and from an old-style project in MSBuild's namespace — NuGet ids compare
     * case-blind, and a central `PackageVersion` pins a version without referencing anything.
     */
    public function test_nuget_reads_the_package_references_of_every_project(): void
    {
        mkdir("{$this->root}/src/Api", 0777, true);
        mkdir("{$this->root}/src/Legacy", 0777, true);
        file_put_contents("{$this->root}/src/Api/Api.csproj", <<<'XML'
            <Project Sdk="Microsoft.NET.Sdk.Web">
              <ItemGroup>
                <PackageReference Include="MediatR" Version="12.2.0" />
              </ItemGroup>
            </Project>
            XML);
        file_put_contents("{$this->root}/src/Legacy/Legacy.csproj", <<<'XML'
            <?xml version="1.0" encoding="utf-8"?>
            <Project ToolsVersion="15.0" xmlns="http://schemas.microsoft.com/developer/msbuild/2003">
              <ItemGroup>
                <PackageReference Include="Newtonsoft.Json">
                  <Version>13.0.3</Version>
                </PackageReference>
              </ItemGroup>
            </Project>
            XML);
        file_put_contents("{$this->root}/Directory.Build.props", <<<'XML'
            <Project>
              <ItemGroup>
                <PackageReference Include="StyleCop.Analyzers" PrivateAssets="all" />
              </ItemGroup>
            </Project>
            XML);
        file_put_contents("{$this->root}/Directory.Packages.props", <<<'XML'
            <Project>
              <ItemGroup>
                <PackageVersion Include="Serilog" Version="3.1.1" />
              </ItemGroup>
            </Project>
            XML);

        $this->assertTrue(Ecosystem::NuGet->hasInstalled('mediatr', $this->root));
        $this->assertTrue(Ecosystem::NuGet->hasInstalled('Newtonsoft.Json', $this->root));
        $this->assertTrue(Ecosystem::NuGet->hasInstalled('StyleCop.Analyzers', $this->root));
        $this->assertFalse(Ecosystem::NuGet->hasInstalled('Serilog', $this->root));
        $this->assertFalse(Ecosystem::NuGet->hasInstalled('AutoMapper', $this->root));
    }
}
