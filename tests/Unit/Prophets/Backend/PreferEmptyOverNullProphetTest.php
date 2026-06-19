<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferEmptyOverNullProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferEmptyOverNullProphetTest extends TestCase
{
    private PreferEmptyOverNullProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PreferEmptyOverNullProphet;
    }

    public function test_flags_nullable_array_return(): void
    {
        $judgment = $this->judge('class A { public function rows(): array | null { return null; } }');

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('empty identity', $judgment->warnings[0]->message);
    }

    public function test_flags_nullable_collection_shorthand(): void
    {
        $judgment = $this->judge('class A { public function rows(): ?Collection { return null; } }');

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_flags_nullable_collection_property(): void
    {
        $judgment = $this->judge('class A { public Collection | null $items = null; }');

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_does_not_flag_scalar_or_single_object_nullable(): void
    {
        // Scalar / single-object absence is PreferOptionOverNull's Option case.
        $this->assertTrue($this->judge('class A { public function a(): ?string { return null; } }')->isRighteous());
        $this->assertTrue($this->judge('class A { public function b(): ?int { return null; } }')->isRighteous());
        $this->assertTrue($this->judge('class A { public function c(): ?User { return null; } }')->isRighteous());
    }

    public function test_does_not_flag_a_three_member_union(): void
    {
        // 3+ members defer to WideUnionType.
        $this->assertTrue($this->judge('class A { public function a(): Collection | array | null { return null; } }')->isRighteous());
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\n" . $body);
    }
}
