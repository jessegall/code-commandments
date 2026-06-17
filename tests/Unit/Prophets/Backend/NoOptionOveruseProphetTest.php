<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoOptionOveruseProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoOptionOveruseProphetTest extends TestCase
{
    private NoOptionOveruseProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoOptionOveruseProphet;
    }

    public function test_flags_a_method_that_always_returns_some(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class C
        {
            public function current(): Option
            {
                return Option::some($this->value);
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('never empty', $judgment->warnings[0]->message);
    }

    public function test_flags_construct_then_unwrap(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class C
        {
            public function go()
            {
                return Option::some($this->compute())->getOrThrow();
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('immediately unwrapped', $judgment->warnings[0]->message);
    }

    public function test_does_not_flag_a_method_with_a_none_path(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class C
        {
            public function find($name): Option
            {
                foreach ($this->items as $item) {
                    if ($item->name === $name) {
                        return Option::some($item);
                    }
                }
                return Option::none();
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_returning_a_plain_value(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class C
        {
            public function current(): Value
            {
                return $this->value;
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_unwrapping_a_received_option(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class C
        {
            public function go($name)
            {
                return $this->find($name)->getOrThrow();
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_mapped_option_return(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class C
        {
            public function go($name): Option
            {
                return $this->find($name)->map(fn ($v) => $v->x);
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
