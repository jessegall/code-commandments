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

    public function test_two_member_union_is_a_warning(): void
    {
        $judgment = $this->judge('class A { public function m(string | int $x): void {} }');

        $this->assertCount(1, $judgment->warnings);
        $this->assertCount(0, $judgment->sins);
        // string | int is an all-scalar union → ScalarUnion is its home.
        $this->assertStringContainsString('ScalarUnion', $judgment->warnings[0]->message);
    }

    public function test_three_plus_member_union_is_a_sin(): void
    {
        $judgment = $this->judge('class A { public function m(array | string | null $x = null): void {} }');

        $this->assertTrue($judgment->isFallen());
        $this->assertCount(1, $judgment->sins);
        $this->assertCount(0, $judgment->warnings);
    }

    public function test_all_scalar_union_suggests_scalar_union(): void
    {
        $judgment = $this->judge('class A { public function m(string | int | float $x): void {} }');

        $this->assertCount(1, $judgment->sins);
        $this->assertStringContainsString('ScalarUnion', $judgment->sins[0]->message);
    }

    public function test_nullable_scalar_union_suggests_scalar_option(): void
    {
        $judgment = $this->judge('class A { public function m(string | int | null $x = null): void {} }');

        $this->assertCount(1, $judgment->sins);
        $this->assertStringContainsString('ScalarOption', $judgment->sins[0]->message);
    }

    public function test_non_scalar_union_keeps_general_guidance(): void
    {
        $judgment = $this->judge('class A { public function m(array | string $x): void {} }');

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringNotContainsString('ScalarUnion', $judgment->warnings[0]->message);
        $this->assertStringContainsString('Option', $judgment->warnings[0]->message);
    }

    public function test_docblock_three_member_with_spaces_is_a_sin(): void
    {
        // The space inside array<string, int> must not truncate the type.
        $judgment = $this->judge('class A { /** @param array<string, int>|string|null $x */ public function m($x) {} }');

        $this->assertCount(1, $judgment->sins);
    }

    public function test_does_not_flag_a_simple_nullable(): void
    {
        // `?T` is the idiomatic nullable (a NullableType, not a union) — exempt.
        $judgment = $this->judge('class A { public function m(?Thing $x = null): void {} }');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_spelled_out_nullable(): void
    {
        // `T | null` is the same type as `?T` — a simple nullable. Flagging one
        // syntax but not the other is inconsistent (issue #24). The reported
        // code: `paletteFor(WorkflowType | null $type)`.
        $judgment = $this->judge('class A { public function paletteFor(WorkflowType | null $type): array { return []; } }');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_spelled_out_nullable_in_docblock(): void
    {
        $judgment = $this->judge('class A { /** @param WorkflowType|null $type */ public function paletteFor($type): array { return []; } }');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_three_member_union_with_null_still_fires(): void
    {
        // A simple nullable is width-1-plus-null; this is width-2-plus-null —
        // still under-modelled, so the null exemption must NOT swallow it.
        $judgment = $this->judge('class A { public function m(array | string | null $x = null): void {} }');

        $this->assertCount(1, $judgment->sins);
    }

    public function test_does_not_flag_a_union_inside_an_attribute_class(): void
    {
        // Attribute ctor args must be constant expressions — an Option/Union can
        // never live there, so the suggestion is unactionable (issue #25 pt 1).
        $judgment = $this->judge(
            '#[\Attribute(\Attribute::TARGET_PROPERTY)] '
            . 'class ContextInput { public function __construct('
            . 'public array | string | null $options = null, '
            . 'public string | int $id = 0) {} }'
        );

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_flags_a_wide_union_in_a_plain_class_not_an_attribute(): void
    {
        // The same shape outside an attribute class still fires.
        $judgment = $this->judge('class Plain { public function __construct(public array | string | null $options = null) {} }');

        $this->assertCount(1, $judgment->sins);
    }

    public function test_does_not_flag_a_union_on_an_override_method(): void
    {
        // The signature is inherited from an interface/base — not the author's
        // to change, so the suggestion is unactionable (issue #25 pt 2).
        $judgment = $this->judge(
            'class A extends Base { #[\Override] public function morph(): array | string | null { return null; } }'
        );

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_flags_a_union_on_a_non_override_method(): void
    {
        $judgment = $this->judge('class A { public function morph(): array | string | null { return null; } }');

        $this->assertCount(1, $judgment->sins);
    }

    public function test_does_not_flag_a_union_inside_a_generic(): void
    {
        $judgment = $this->judge('class A { /** @return Option<array|string> */ public function m(): Option { return Option::none(); } }');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_warning_band_can_be_disabled(): void
    {
        $prophet = (new WideUnionTypeProphet)->configure(['warnings_enabled' => false]);

        // 2-member warning gone …
        $this->assertTrue($prophet->judge('/x.php', "<?php\nclass A { public function m(string | int \$x): void {} }")->isRighteous());
        // … but 3+ is still a sin.
        $this->assertCount(1, $prophet->judge('/x.php', "<?php\nclass A { public function m(array | string | null \$x = null): void {} }")->sins);
    }

    public function test_respects_configured_thresholds(): void
    {
        $prophet = (new WideUnionTypeProphet)->configure(['warn_at_types' => 3, 'sin_at_types' => 4]);

        // 2 now below the warning floor.
        $this->assertTrue($prophet->judge('/x.php', "<?php\nclass A { public function m(string | int \$x): void {} }")->isRighteous());
        // 3 is now a warning.
        $this->assertCount(1, $prophet->judge('/x.php', "<?php\nclass A { public function m(array | string | null \$x = null): void {} }")->warnings);
        // 4 is a sin.
        $this->assertCount(1, $prophet->judge('/x.php', "<?php\nclass A { public function m(array | string | int | null \$x = null): void {} }")->sins);
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
