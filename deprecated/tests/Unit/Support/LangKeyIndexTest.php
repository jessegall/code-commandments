<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\LangKeyIndex;
use JesseGall\CodeCommandments\Tests\TestCase;

class LangKeyIndexTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        LangKeyIndex::flush();
    }

    public function test_unions_keys_across_locales_and_tracks_owned_groups(): void
    {
        $root = $this->tempProject([
            'lang/en/common.php' => "<?php\nreturn ['equals' => 'Equals', 'nested' => ['deep' => 'X']];\n",
            'lang/nl/common.php' => "<?php\nreturn ['equals' => 'Gelijk aan', 'only_nl' => 'Alleen NL'];\n",
        ]);

        $index = LangKeyIndex::forFile($root . '/src/Foo.php');

        $this->assertTrue($index->ownsGroup('common'));
        $this->assertFalse($index->ownsGroup('auth'));

        $this->assertTrue($index->hasKey('common.equals'));
        $this->assertTrue($index->hasKey('common.nested.deep'));
        $this->assertTrue($index->hasKey('common.only_nl'), 'a key present in any locale is known');
        $this->assertFalse($index->hasKey('common.missing'));
    }

    public function test_supports_resources_lang_layout(): void
    {
        $root = $this->tempProject([
            'resources/lang/en/messages.php' => "<?php\nreturn ['hello' => 'Hi'];\n",
        ]);

        $index = LangKeyIndex::forFile($root . '/src/Foo.php');

        $this->assertTrue($index->ownsGroup('messages'));
        $this->assertTrue($index->hasKey('messages.hello'));
    }

    public function test_is_empty_without_a_lang_dir(): void
    {
        $this->assertTrue(LangKeyIndex::forFile(sys_get_temp_dir() . '/none-' . uniqid() . '/x.php')->isEmpty());
    }

    /**
     * @param  array<string, string>  $files
     */
    private function tempProject(array $files): string
    {
        $root = sys_get_temp_dir() . '/cc-lki-' . uniqid();
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
