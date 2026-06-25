<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\HardcodedLiteralShouldBeConfigProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\ConfigConsumerCensus;
use JesseGall\CodeCommandments\Support\ConfigReadIndex;
use JesseGall\CodeCommandments\Tests\TestCase;

class HardcodedLiteralShouldBeConfigProphetTest extends TestCase
{
    private HardcodedLiteralShouldBeConfigProphet $prophet;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new HardcodedLiteralShouldBeConfigProphet;
        ConfigReadIndex::flush();
        ConfigConsumerCensus::flush();

        $this->root = sys_get_temp_dir() . '/cc-h11-' . uniqid();
        @mkdir($this->root . '/src', 0755, true);
        @mkdir($this->root . '/config', 0755, true);
        file_put_contents($this->root . '/composer.json', '{}');
        file_put_contents($this->root . '/config/queue.php', "<?php\nreturn ['assistants' => 'ai-assistants', 'disk' => 'local', 'rate' => 'throttle:60,1'];\n");
        // The congruent config-read site: onQueue() reads from config elsewhere.
        $this->write('Reader.php', '<?php namespace App; class Reader { function r($d) { $d->onQueue(config("queue.assistants")); $d->throttle(config("queue.rate")); } }');
    }

    public function test_flags_a_literal_hardcoded_into_a_consumer_that_reads_config_elsewhere(): void
    {
        $this->write('B.php', '<?php namespace App; class B { function b($d) { $d->onQueue("ai-assistants"); } }');

        $judgment = $this->judge('B.php');

        $this->assertCount(1, $judgment->warnings);
        $this->assertSame('hardcoded-literal-config:onqueue:ai-assistants', $judgment->warnings[0]->symbol);
        $this->assertStringContainsString("config('queue.assistants')", $judgment->warnings[0]->message);
    }

    public function test_flags_a_colon_compound_token_too(): void
    {
        $this->write('C.php', '<?php namespace App; class C { function c($d) { $d->throttle("throttle:60,1"); } }');

        $this->assertTrue($this->judge('C.php')->hasWarnings());
    }

    public function test_does_not_flag_the_config_read_site_itself(): void
    {
        $this->assertTrue($this->judge('Reader.php')->isRighteous());
    }

    public function test_does_not_flag_a_bare_default_value_even_if_congruent(): void
    {
        // onQueue is congruent for ai-assistants, but 'local' (config queue.disk) is a
        // bare word with no separator → distinctiveness excludes it.
        $this->write('D.php', '<?php namespace App; class D { function d($s) { $s->onQueue(config("queue.disk")); $s->onQueue("local"); } }');

        $this->assertTrue($this->judge('D.php')->isRighteous());
    }

    public function test_does_not_flag_without_a_congruent_consumer(): void
    {
        // 'ai-assistants' hardcoded into a DIFFERENT consumer (label) that never reads config.
        $this->write('E.php', '<?php namespace App; class E { function e($d) { $d->label("ai-assistants"); } }');

        $this->assertTrue($this->judge('E.php')->isRighteous(), 'no config read into label() → coincidental, not drift');
    }

    public function test_describes_itself(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
        $this->assertNotNull($this->prophet->advisory());
    }

    private function write(string $name, string $code): void
    {
        file_put_contents($this->root . '/src/' . $name, $code);
    }

    private function judge(string $name): Judgment
    {
        $file = $this->root . '/src/' . $name;

        return $this->prophet->judge($file, (string) file_get_contents($file));
    }
}
