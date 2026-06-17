<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoOptionToNullProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoOptionToNullProphetTest extends TestCase
{
    private NoOptionToNullProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoOptionToNullProphet;
    }

    public function test_flags_get_or_null(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Resolver
        {
            public function go($port): void
            {
                $input = $this->inputByName($port)->getOr(null);

                if ($input?->socketType() === SocketType::Bag) {
                    // ...
                }
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('getOr(null)', $judgment->warnings[0]->message);
    }

    public function test_does_not_flag_get_or_with_a_real_default(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Resolver
        {
            public function go($port)
            {
                return $this->inputByName($port)->getOr(Input::empty());
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_get_or_throw_or_map_or_each(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Resolver
        {
            public function go($port): void
            {
                $a = $this->inputByName($port)->getOrThrow();
                $b = $this->inputByName($port)->map(fn ($i) => $i->socketType());
                $this->inputByName($port)->each(fn ($i) => $i->go());
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
