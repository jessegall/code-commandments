<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferTypeCoalesceProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferTypeCoalesceProphetTest extends TestCase
{
    private PreferTypeCoalesceProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PreferTypeCoalesceProphet;
    }

    public function test_flags_nullable_array_param_with_empty_array(): void
    {
        $j = $this->judge('class C { public function h(?array $x): array { return $x ?? []; } }');

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString('T_Array::coalesce', $j->warnings[0]->message);
        $this->assertTrue($j->warnings[0]->autoFixable);
    }

    public function test_flags_t_array_empty_constant(): void
    {
        $j = $this->judge('class C { public function h(?array $x): array { return $x ?? \JesseGall\PhpTypes\T_Array::EMPTY; } }');

        $this->assertCount(1, $j->warnings);
    }

    public function test_flags_each_scalar_wrapper(): void
    {
        $this->assertCount(1, $this->judge('class C { public function h(?string $x): string { return $x ?? ""; } }')->warnings);
        $this->assertCount(1, $this->judge('class C { public function h(?int $x): int { return $x ?? 0; } }')->warnings);
        $this->assertCount(1, $this->judge('class C { public function h(?float $x): float { return $x ?? 0.0; } }')->warnings);
        $this->assertCount(1, $this->judge('class C { public function h(?bool $x): bool { return $x ?? false; } }')->warnings);
    }

    public function test_flags_this_property(): void
    {
        $j = $this->judge('class C { private ?array $items = null; public function h(): array { return $this->items ?? []; } }');

        $this->assertCount(1, $j->warnings);
    }

    public function test_flags_union_null_form(): void
    {
        $j = $this->judge('class C { public function h(array|null $x): array { return $x ?? []; } }');

        $this->assertCount(1, $j->warnings);
    }

    public function test_ignores_untyped_or_mixed_left(): void
    {
        $this->assertTrue($this->judge('class C { public function h($x): array { return $x ?? []; } }')->isRighteous());
        $this->assertTrue($this->judge('class C { public function h(mixed $x): array { return $x ?? []; } }')->isRighteous());
    }

    public function test_ignores_non_nullable_left(): void
    {
        // A non-nullable array `?? []` is a dead coalesce, not a coalesce-helper case.
        $this->assertTrue($this->judge('class C { public function h(array $x): array { return $x ?? []; } }')->isRighteous());
    }

    public function test_ignores_type_mismatch(): void
    {
        // ?string left with `?? []` (array default) — types disagree, do not fire.
        $this->assertTrue($this->judge('class C { public function h(?string $x): mixed { return $x ?? []; } }')->isRighteous());
    }

    public function test_ignores_non_empty_default(): void
    {
        $this->assertTrue($this->judge('class C { public function h(?array $x): array { return $x ?? ["a"]; } }')->isRighteous());
        $this->assertTrue($this->judge('class C { public function h(?int $x): int { return $x ?? 5; } }')->isRighteous());
    }

    public function test_flags_nullable_array_foreach_guard(): void
    {
        // A nullable ARRAY must never stay as `?? []`, even in a foreach — it
        // becomes T_Array::coalesce(). (A non-array iterable wouldn't resolve to
        // `array` here, so it stays NoNullCoalesceToNull's `?? []` guard.)
        $j = $this->judge('class C { public function h(?array $x): void { foreach ($x ?? [] as $v) { echo $v; } } }');

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString('T_Array::coalesce', $j->warnings[0]->message);
    }

    public function test_repent_rewrites_to_coalesce(): void
    {
        $src = '<?php class C { public function h(?array $x): array { return $x ?? []; } }';

        $result = $this->prophet->repent('/x.php', $src);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('return T_Array::coalesce($x);', $result->newContent);
        $this->assertStringNotContainsString('?? []', $result->newContent);
    }

    public function test_repent_rewrites_string_property(): void
    {
        $src = '<?php class C { private ?string $label = null; public function h(): string { return $this->label ?? ""; } }';

        $result = $this->prophet->repent('/x.php', $src);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('T_String::coalesce($this->label)', $result->newContent);
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\n" . $body);
    }
}
