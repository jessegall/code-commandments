<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\ConfigReadIndex;
use JesseGall\CodeCommandments\Tests\TestCase;

class ConfigReadIndexTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ConfigReadIndex::flush();
    }

    public function test_indexes_every_node_and_leaf_path_and_owned_top_levels(): void
    {
        $root = $this->tempProject([
            'config/services.php' => "<?php\nreturn ['stripe' => ['key' => env('K'), 'secret' => env('S')], 'mailgun' => ['domain' => 'x']];\n",
        ]);

        $index = ConfigReadIndex::forFile($root . '/src/Foo.php');

        $this->assertTrue($index->ownsTopLevel('services'));
        $this->assertFalse($index->ownsTopLevel('app'));

        // nodes AND leaves are declared paths
        $this->assertTrue($index->hasPath('services.stripe'));
        $this->assertTrue($index->hasPath('services.stripe.key'));
        $this->assertTrue($index->hasPath('services.mailgun.domain'));

        // a missing leaf is absent
        $this->assertFalse($index->hasPath('services.stripe.kye'));
    }

    public function test_siblings_lists_the_candidate_corrections(): void
    {
        $root = $this->tempProject([
            'config/services.php' => "<?php\nreturn ['stripe' => ['key' => 1, 'secret' => 2]];\n",
        ]);

        $siblings = ConfigReadIndex::forFile($root . '/src/Foo.php')->siblingsOf('services.stripe.kye');

        $this->assertSame(['services.stripe.key', 'services.stripe.secret'], $siblings);
    }

    public function test_is_empty_without_a_config_dir(): void
    {
        $this->assertTrue(ConfigReadIndex::forFile(sys_get_temp_dir() . '/nowhere-' . uniqid() . '/x.php')->isEmpty());
    }

    /**
     * @param  array<string, string>  $files
     */
    private function tempProject(array $files): string
    {
        $root = sys_get_temp_dir() . '/cc-cri-' . uniqid();
        @mkdir($root . '/src', 0755, true);
        file_put_contents($root . '/composer.json', '{}');

        foreach ($files as $relative => $content) {
            $full = $root . '/' . $relative;
            @mkdir(\dirname($full), 0755, true);
            file_put_contents($full, $content);
        }

        return $root;
    }
}
