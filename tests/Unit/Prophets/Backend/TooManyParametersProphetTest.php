<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\TooManyParametersProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class TooManyParametersProphetTest extends TestCase
{
    private TooManyParametersProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new TooManyParametersProphet;
    }

    public function test_flags_a_factory_with_too_many_parameters(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class A
        {
            public static function make($a, $b, $c, $d, $e, $f, $g): static
            {
                return static::from([]);
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('7 parameters', $judgment->warnings[0]->message);
    }

    public function test_does_not_flag_a_method_within_the_limit(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class A
        {
            public function m($a, $b, $c, $d, $e, $f): void {}
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_exempts_constructors_by_default(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class A
        {
            public function __construct($a, $b, $c, $d, $e, $f, $g, $h, $i) {}
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_flags_constructors_when_configured(): void
    {
        $prophet = (new TooManyParametersProphet)->configure(['include_constructors' => true]);

        $judgment = $prophet->judge('/x.php', "<?php\nclass A { public function __construct(\$a, \$b, \$c, \$d, \$e, \$f, \$g) {} }");

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_respects_a_configured_max(): void
    {
        $prophet = (new TooManyParametersProphet)->configure(['max_parameters' => 3]);

        $judgment = $prophet->judge('/x.php', "<?php\nclass A { public function m(\$a, \$b, \$c, \$d): void {} }");

        $this->assertCount(1, $judgment->warnings);
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
