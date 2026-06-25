<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\ConfigKeyContractProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\ConfigReadIndex;
use JesseGall\CodeCommandments\Tests\TestCase;

class ConfigKeyContractProphetTest extends TestCase
{
    private ConfigKeyContractProphet $prophet;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new ConfigKeyContractProphet;
        ConfigReadIndex::flush();

        // A project whose config/ declares services.stripe.{key,secret}.
        $this->root = sys_get_temp_dir() . '/cc-ckc-' . uniqid();
        @mkdir($this->root . '/src', 0755, true);
        @mkdir($this->root . '/config', 0755, true);
        file_put_contents($this->root . '/composer.json', '{}');
        file_put_contents($this->root . '/config/services.php', "<?php\nreturn ['stripe' => ['key' => env('K'), 'secret' => env('S')]];\n");
    }

    public function test_flags_a_typo_within_an_owned_config_namespace(): void
    {
        $judgment = $this->judge("\$k = config('services.stripe.kye');"); // typo of 'key'

        $this->assertCount(1, $judgment->warnings);
        $this->assertSame('config-key-contract:services.stripe.kye', $judgment->warnings[0]->symbol);
        $this->assertStringContainsString('not a declared path', $judgment->warnings[0]->message);
    }

    public function test_lists_declared_siblings_as_candidate_corrections(): void
    {
        $judgment = $this->judge("\$k = config('services.stripe.kye');");

        $this->assertStringContainsString('services.stripe.key', $judgment->warnings[0]->message);
        $this->assertStringContainsString('services.stripe.secret', $judgment->warnings[0]->message);
    }

    public function test_does_not_flag_a_declared_path_or_node(): void
    {
        $this->assertTrue($this->judge("\$k = config('services.stripe.key');")->isRighteous());
        $this->assertTrue($this->judge("\$k = config('services.stripe');")->isRighteous(), 'a node path is declared too');
        $this->assertTrue($this->judge("\$k = \\Config::get('services.stripe.secret');")->isRighteous());
    }

    public function test_does_not_flag_a_framework_namespace_or_dynamic_key(): void
    {
        // 'app' has no config/app.php in this project → not owned → not checkable.
        $this->assertTrue($this->judge("\$k = config('app.name');")->isRighteous());
        // dynamic key → unresolvable → leave.
        $this->assertTrue($this->judge('$k = config($path);')->isRighteous());
        // a whole-file read has nothing to verify.
        $this->assertTrue($this->judge("\$k = config('services');")->isRighteous());
    }

    public function test_flags_a_bad_key_via_config_facade(): void
    {
        $this->assertCount(1, $this->judge("\$k = \\Config::get('services.stripe.bad');")->warnings);
    }

    public function test_does_not_fire_without_a_config_dir(): void
    {
        $file = sys_get_temp_dir() . '/cc-noconf-' . uniqid() . '.php';
        $code = "<?php\n\$k = config('services.stripe.kye');";
        file_put_contents($file, $code);

        $this->assertTrue($this->prophet->judge($file, $code)->isRighteous());
        @unlink($file);
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
