<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoCompactProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoCompactProphetTest extends TestCase
{
    private NoCompactProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoCompactProphet;
    }

    public function test_flags_compact_into_from(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class A
        {
            public function m($name, $type)
            {
                return static::from(compact('name', 'type'));
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('compact()', $judgment->warnings[0]->message);
    }

    public function test_flags_extract(): void
    {
        $judgment = $this->judge(<<<'PHP'
        function f(array $payload) { extract($payload); }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('extract()', $judgment->warnings[0]->message);
    }

    public function test_does_not_flag_an_explicit_array(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class A
        {
            public function m($name)
            {
                return static::from(['name' => $name]);
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_compact_method_on_an_object(): void
    {
        // $collection->compact() is unrelated to the compact() function.
        $judgment = $this->judge(<<<'PHP'
        class A
        {
            public function m($collection)
            {
                return $collection->compact();
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_describes_itself(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
        $this->assertNotNull($this->prophet->advisory());
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\n" . $body);
    }
}
