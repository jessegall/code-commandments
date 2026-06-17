<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoOptionInUnionProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoOptionInUnionProphetTest extends TestCase
{
    private NoOptionInUnionProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoOptionInUnionProphet;
    }

    public function test_flags_option_in_a_union_parameter(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class A
        {
            public function m(Option | array | string | null $isVisibleRule = null) {}
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('Option', $judgment->warnings[0]->message);
    }

    public function test_flags_nullable_option_return(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class A
        {
            public function find(): ?Option { return Option::none(); }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_flags_option_null_property(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class A
        {
            private Option | null $x;
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_does_not_flag_a_bare_option(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class A
        {
            public function m(Option $x): Option { return $x; }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_plain_nullable_without_option(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class A
        {
            public function find(): ?Thing { return null; }
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
