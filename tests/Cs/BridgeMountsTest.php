<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cs;

use JesseGall\CodeCommandments\Cs\Bridge;
use PHPUnit\Framework\TestCase;

final class BridgeMountsTest extends TestCase
{
    private string $solution;

    protected function setUp(): void
    {
        $this->solution = (string) realpath(sys_get_temp_dir()) . '/cc-mounts-' . uniqid();
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->solution));
    }

    public function test_a_project_is_mounted_with_every_project_it_references(): void
    {
        $this->project('tests/App.Tests', '<ItemGroup><ProjectReference Include="..\..\src\App\App.csproj" /></ItemGroup>');
        $this->project('src/App', '<ItemGroup><ProjectReference Include="../Core/Core.csproj" /></ItemGroup>');
        $this->project('src/Core', '');
        $this->project('src/Unrelated', '');

        $this->assertSame(
            ["{$this->solution}/tests/App.Tests", "{$this->solution}/src/App", "{$this->solution}/src/Core"],
            Bridge::mountsFor(["{$this->solution}/tests/App.Tests"]),
        );
    }

    public function test_a_reference_is_read_at_any_depth_and_in_the_msbuild_namespace(): void
    {
        $this->project('src/App', '<Choose><When Condition="true"><ItemGroup><ProjectReference Include="../Core/Core.csproj" /></ItemGroup></When></Choose>');
        $this->project('src/Core', '<ItemGroup><ProjectReference Include="../Legacy/Legacy.csproj" /></ItemGroup>', 'http://schemas.microsoft.com/developer/msbuild/2003');
        $this->project('src/Legacy', '');

        $this->assertSame(
            ["{$this->solution}/src/App", "{$this->solution}/src/Core", "{$this->solution}/src/Legacy"],
            Bridge::mountsFor(["{$this->solution}/src/App"]),
        );
    }

    private function project(string $folder, string $body, string $namespace = ''): void
    {
        mkdir("{$this->solution}/{$folder}", 0o755, true);
        $xmlns = $namespace === '' ? '' : " xmlns=\"{$namespace}\"";
        file_put_contents("{$this->solution}/{$folder}/" . basename($folder) . '.csproj', "<Project Sdk=\"Microsoft.NET.Sdk\"{$xmlns}>{$body}</Project>");
    }
}
