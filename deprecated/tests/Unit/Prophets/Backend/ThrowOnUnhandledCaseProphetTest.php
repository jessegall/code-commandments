<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\ThrowOnUnhandledCaseProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class ThrowOnUnhandledCaseProphetTest extends TestCase
{
    private ThrowOnUnhandledCaseProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new ThrowOnUnhandledCaseProphet;
    }

    public function test_flags_default_null_where_every_case_yields_a_value(): void
    {
        $judgment = $this->judge('class C { public function r($t): ?Renderer { return match ($t) {
            NodeType::A => new RendererA(), NodeType::B => new RendererB(), default => null,
        }; } }');

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('unhandled', strtolower($judgment->warnings[0]->message));
        $this->assertStringContainsString('NodeType', $judgment->warnings[0]->message);
    }

    public function test_flags_null_arm_returning_option_none(): void
    {
        $judgment = $this->judge('class C { public function r($t) { return match ($t) {
            Kind::A => Option::some(1), Kind::B => Option::some(2), null => Option::none(),
        }; } }');

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_leaves_a_real_value_default(): void
    {
        $this->assertTrue($this->judge('class C { public function r($t): Renderer { return match ($t) {
            NodeType::A => new RendererA(), NodeType::B => new RendererB(), default => new Fallback(),
        }; } }')->isRighteous());
    }

    public function test_leaves_when_a_real_case_yields_none(): void
    {
        // A case arm itself returns null → genuine domain absence, not just the
        // fallthrough — PreferOptionOverNull's domain, not a throw.
        $this->assertTrue($this->judge('class C { public function r($t): ?X { return match ($t) {
            Kind::A => new X(), Kind::B => null, default => null,
        }; } }')->isRighteous());
    }

    public function test_leaves_an_exhaustive_match(): void
    {
        $this->assertTrue($this->judge('class C { public function r($t): X { return match ($t) {
            Kind::A => new A(), Kind::B => new B(), Kind::C => new C(),
        }; } }')->isRighteous());
    }

    public function test_leaves_match_true_and_single_case(): void
    {
        $this->assertTrue($this->judge('class C { public function r($t) { return match (true) {
            $t->a() => 1, default => null,
        }; } }')->isRighteous());

        $this->assertTrue($this->judge('class C { public function r($t): ?int { return match ($t) {
            Kind::A => 1, default => null,
        }; } }')->isRighteous());
    }

    public function test_describes_itself(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
        $this->assertNotNull($this->prophet->advisory());
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\nnamespace App;\n" . $body);
    }
}
