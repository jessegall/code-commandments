<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\MixedConfigValueUsedTypedProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\ConfigReadIndex;
use JesseGall\CodeCommandments\Tests\TestCase;

class MixedConfigValueUsedTypedProphetTest extends TestCase
{
    private MixedConfigValueUsedTypedProphet $prophet;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new MixedConfigValueUsedTypedProphet;
        ConfigReadIndex::flush();

        $this->root = sys_get_temp_dir() . '/cc-mcv-' . uniqid();
        @mkdir($this->root . '/src', 0755, true);
        @mkdir($this->root . '/config', 0755, true);
        file_put_contents($this->root . '/composer.json', '{}');
        file_put_contents($this->root . '/config/cache.php', "<?php\nreturn ['ttl' => env('CACHE_TTL'), 'store' => 'redis', 'lit' => 3600];\n");
    }

    public function test_flags_env_backed_value_strict_compared_to_a_number(): void
    {
        $judgment = $this->judge("\$x = config('cache.ttl') === 3600;");

        $this->assertCount(1, $judgment->warnings);
        $this->assertSame('mixed-config-typed:cache.ttl', $judgment->warnings[0]->symbol);
        $this->assertStringContainsString('always false', $judgment->warnings[0]->message);
    }

    public function test_flags_either_operand_order_and_not_identical(): void
    {
        $this->assertCount(1, $this->judge("\$x = 3600 === config('cache.ttl');")->warnings);
        $this->assertStringContainsString('always true', $this->judge("if (config('cache.ttl') !== 0) {}")->warnings[0]->message);
    }

    public function test_does_not_flag_a_literal_typed_leaf(): void
    {
        $this->assertTrue($this->judge("\$x = config('cache.lit') === 3600;")->isRighteous(), 'cache.lit is a literal int in config, not env-backed');
    }

    public function test_does_not_flag_string_compare_loose_compare_or_cast(): void
    {
        $this->assertTrue($this->judge("\$x = config('cache.ttl') === '3600';")->isRighteous());
        $this->assertTrue($this->judge("\$x = config('cache.ttl') == 3600;")->isRighteous());
        $this->assertTrue($this->judge("\$x = (int) config('cache.ttl') === 3600;")->isRighteous());
    }

    public function test_does_not_flag_a_framework_namespace(): void
    {
        // No config/app.php in this project → app.* not owned → unknown env-status.
        $this->assertTrue($this->judge("\$x = config('app.debug') === 1;")->isRighteous());
    }

    public function test_describes_itself(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
        $this->assertNotNull($this->prophet->advisory());
    }

    private function judge(string $body): Judgment
    {
        $file = $this->root . '/src/X.php';
        $code = "<?php\n" . $body;
        file_put_contents($file, $code);

        return $this->prophet->judge($file, $code);
    }
}
