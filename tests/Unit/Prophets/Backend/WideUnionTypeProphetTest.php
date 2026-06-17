<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\WideUnionTypeProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class WideUnionTypeProphetTest extends TestCase
{
    private WideUnionTypeProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new WideUnionTypeProphet;
    }

    public function test_flags_a_native_three_member_union(): void
    {
        $judgment = $this->judge('class A { public function m(array | string | null $x = null) {} }');

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('Option', $judgment->warnings[0]->message);
    }

    public function test_flags_a_docblock_union_with_spaces_in_generics(): void
    {
        // The space inside array<string, int> must not truncate the type.
        $judgment = $this->judge('class A { /** @param array<string, int>|string|null $x */ public function m($x) {} }');

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_does_not_flag_a_simple_nullable(): void
    {
        $two = $this->judge('class A { public function m(string | null $x = null) {} }');
        $nullable = $this->judge('class A { public function m(?Thing $x = null) {} }');

        $this->assertTrue($two->isRighteous());
        $this->assertTrue($nullable->isRighteous());
    }

    public function test_does_not_flag_a_union_inside_a_generic(): void
    {
        $judgment = $this->judge('class A { /** @return Option<array|string> */ public function m(): Option { return Option::none(); } }');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_sin_mode_blocks(): void
    {
        $prophet = (new WideUnionTypeProphet)->configure(['severity' => 'sin']);

        $judgment = $prophet->judge('/x.php', "<?php\nclass A { public function m(array | string | null \$x = null) {} }");

        $this->assertTrue($judgment->isFallen());
        $this->assertCount(1, $judgment->sins);
        $this->assertNull($prophet->advisory());
    }

    public function test_respects_a_configured_max(): void
    {
        $prophet = (new WideUnionTypeProphet)->configure(['max_types' => 3]);

        // 3 members is now within the limit.
        $this->assertTrue($prophet->judge('/x.php', "<?php\nclass A { public function m(array | string | null \$x = null) {} }")->isRighteous());
        // 4 members still flagged.
        $this->assertCount(1, $prophet->judge('/x.php', "<?php\nclass A { public function m(array | string | int | null \$x = null) {} }")->warnings);
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
