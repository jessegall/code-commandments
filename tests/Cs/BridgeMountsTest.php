<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cs;

use JesseGall\CodeCommandments\Cs\Bridge;
use PHPUnit\Framework\TestCase;

final class BridgeMountsTest extends TestCase
{
    public function test_a_project_is_mounted_with_every_project_it_references(): void
    {
        $solution = (string) realpath(sys_get_temp_dir()) . '/cc-mounts-' . uniqid();
        $project = static function (string $folder, string $references) use ($solution): void {
            mkdir("{$solution}/{$folder}", 0o755, true);
            file_put_contents("{$solution}/{$folder}/" . basename($folder) . '.csproj', "<Project Sdk=\"Microsoft.NET.Sdk\"><ItemGroup>{$references}</ItemGroup></Project>");
        };
        $project('tests/App.Tests', '<ProjectReference Include="..\..\src\App\App.csproj" />');
        $project('src/App', '<ProjectReference Include="../Core/Core.csproj" />');
        $project('src/Core', '');
        $project('src/Unrelated', '');

        $this->assertSame(
            ["{$solution}/tests/App.Tests", "{$solution}/src/App", "{$solution}/src/Core"],
            Bridge::mountsFor(["{$solution}/tests/App.Tests"]),
        );
    }
}
