<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\ConfigMapIndex;
use JesseGall\CodeCommandments\Tests\TestCase;

class ConfigMapIndexTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ConfigMapIndex::flush();
    }

    public function test_finds_a_data_driven_map_by_its_key_set(): void
    {
        $root = $this->tempProject([
            'config/services.php' => "<?php\nreturn ['providers' => ['anthropic' => ['key' => 'x'], 'openai' => ['key' => 'y']], 'timeout' => 30];\n",
        ]);

        $index = ConfigMapIndex::forFile($root . '/src/Foo.php');
        $hits = $index->mapsMatching(['openai', 'anthropic']); // order-insensitive

        $this->assertCount(1, $hits);
        $this->assertSame('services.providers', $hits[0]['path']);
    }

    public function test_a_list_or_single_key_map_is_not_a_registered_set(): void
    {
        $root = $this->tempProject([
            'config/services.php' => "<?php\nreturn ['hosts' => ['a.com', 'b.com'], 'only' => ['one' => 1]];\n",
        ]);

        $index = ConfigMapIndex::forFile($root . '/src/Foo.php');

        $this->assertSame([], $index->mapsMatching(['a.com', 'b.com']), 'a list (int keys) is not a map');
        $this->assertSame([], $index->mapsMatching(['one']), 'a single-key map is below the >= 2 bar');
    }

    public function test_returns_empty_when_no_config_dir_is_found(): void
    {
        // A path with no composer.json + config/ ancestor → no index.
        $this->assertSame([], ConfigMapIndex::forFile(sys_get_temp_dir() . '/nowhere-' . uniqid() . '/x.php')->maps());
    }

    /**
     * @param  array<string, string>  $files
     */
    private function tempProject(array $files): string
    {
        $root = sys_get_temp_dir() . '/cc-cfgidx-' . uniqid();
        @mkdir($root, 0755, true);
        file_put_contents($root . '/composer.json', '{}');

        foreach ($files as $relative => $content) {
            $full = $root . '/' . $relative;
            @mkdir(\dirname($full), 0755, true);
            file_put_contents($full, $content);
        }

        return $root;
    }
}
