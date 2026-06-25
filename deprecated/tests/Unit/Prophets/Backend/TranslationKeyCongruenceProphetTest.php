<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\TranslationKeyCongruenceProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\LangKeyIndex;
use JesseGall\CodeCommandments\Tests\TestCase;

class TranslationKeyCongruenceProphetTest extends TestCase
{
    private TranslationKeyCongruenceProphet $prophet;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new TranslationKeyCongruenceProphet;
        LangKeyIndex::flush();

        $this->root = sys_get_temp_dir() . '/cc-tkc-' . uniqid();
        @mkdir($this->root . '/src', 0755, true);
        @mkdir($this->root . '/lang/en', 0755, true);
        file_put_contents($this->root . '/composer.json', '{}');
        file_put_contents($this->root . '/lang/en/common.php', "<?php\nreturn ['equals' => 'Equals', 'greater_than' => 'Greater than', 'nested' => ['deep' => 'X']];\n");
    }

    public function test_flags_a_missing_key_in_an_owned_group(): void
    {
        $judgment = $this->judge("\$x = __('common.equlas');"); // typo of 'equals'

        $this->assertCount(1, $judgment->warnings);
        $this->assertSame('translation-key-congruence:common.equlas', $judgment->warnings[0]->symbol);
        $this->assertStringContainsString('NOT declared in any lang file', $judgment->warnings[0]->message);
    }

    public function test_does_not_flag_a_declared_key_or_node(): void
    {
        $this->assertTrue($this->judge("\$x = __('common.equals');")->isRighteous());
        $this->assertTrue($this->judge("\$x = trans('common.greater_than');")->isRighteous());
        $this->assertTrue($this->judge("\$x = __('common.nested.deep');")->isRighteous());
        $this->assertTrue($this->judge("\$x = trans_choice('common.equals', 2);")->isRighteous());
    }

    public function test_does_not_flag_framework_group_vendor_namespace_or_dynamic(): void
    {
        // 'auth' has no lang/en/auth.php here → not owned → not checkable.
        $this->assertTrue($this->judge("\$x = __('auth.failed');")->isRighteous());
        // vendor namespace
        $this->assertTrue($this->judge("\$x = __('package::common.equlas');")->isRighteous());
        // dynamic key
        $this->assertTrue($this->judge('$x = __($key);')->isRighteous());
        // JSON/string key (no group)
        $this->assertTrue($this->judge("\$x = __('Just a sentence');")->isRighteous());
    }

    public function test_does_not_fire_without_a_lang_dir(): void
    {
        $file = sys_get_temp_dir() . '/cc-nolang-' . uniqid() . '.php';
        $code = "<?php\n\$x = __('common.equlas');";
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
