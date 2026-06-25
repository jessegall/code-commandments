<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoNullCoalesceToNullProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoNullCoalesceToNullProphetTest extends TestCase
{
    private NoNullCoalesceToNullProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoNullCoalesceToNullProphet;
    }

    public function test_flags_noop_coalesce_to_null(): void
    {
        $judgment = $this->judge('$name = $row->label() ?? null;');

        $this->assertCount(1, $judgment->sins);
        $this->assertStringContainsString('no-op', $judgment->sins[0]->message);
    }

    public function test_strips_noop_coalesce_to_null(): void
    {
        $fixed = $this->repent('$name = $row->label() ?? null;');

        $this->assertStringContainsString('$name = $row->label();', $fixed);
        $this->assertStringNotContainsString('?? null', $fixed);
    }

    public function test_strips_noop_inside_a_call(): void
    {
        // The user's case: T_Array::coalesce($x ?? null) — the no-op is stripped,
        // leaving the helper call intact (its semantics aren't ours to unwrap).
        $fixed = $this->repent('foreach (T_Array::coalesce($c?->getParameters() ?? null) as $p) {}');

        $this->assertStringContainsString('T_Array::coalesce($c?->getParameters())', $fixed);
        $this->assertStringNotContainsString('?? null', $fixed);
    }

    public function test_leaves_a_foreach_coalesce_null_alone(): void
    {
        // The foreach guard behaviour was removed — defaulting a nullable array
        // is PreferTypeCoalesce's job, and a `?? []` guard for a non-array
        // iterable is the developer's call. We must NOT strip the `?? null` here
        // and expose an unguarded nullable foreach.
        $code = 'foreach ($obj?->getItems() ?? null as $item) {}';

        $this->assertTrue($this->judge($code)->isRighteous());
        // repent makes no change (empty newContent) — it neither strips the
        // `?? null` nor adds a `?? []` guard.
        $this->assertStringNotContainsString('?? []', $this->repent($code));
    }

    public function test_does_not_guard_a_bare_nullsafe_foreach(): void
    {
        $code = 'foreach ($obj?->getItems() as $item) {}';

        $this->assertTrue($this->judge($code)->isRighteous());
    }

    public function test_does_not_flag_array_access_coalesce_to_null(): void
    {
        // `??` here suppresses the undefined-key notice — NOT a no-op.
        $judgment = $this->judge('$x = $data["offset"] ?? null;');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_property_or_variable_coalesce_to_null(): void
    {
        // Uninitialized typed property / undefined variable — `?? null` is load-bearing.
        $this->assertTrue($this->judge('$x = $obj->prop ?? null;')->isRighteous());
        $this->assertTrue($this->judge('$x = $maybeUndefined ?? null;')->isRighteous());
    }

    public function test_flags_call_return_coalesce_to_null(): void
    {
        // A call return is always defined → `?? null` truly is a no-op.
        $judgment = $this->judge('$x = $this->load() ?? null;');

        $this->assertCount(1, $judgment->sins);
    }

    public function test_does_not_flag_a_real_fallback(): void
    {
        $judgment = $this->judge('$name = $row->label ?? "untitled";');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_foreach_with_empty_array_fallback(): void
    {
        $judgment = $this->judge('foreach ($obj?->getItems() ?? [] as $item) {}');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_foreach_over_plain_value(): void
    {
        $judgment = $this->judge('foreach ($this->items as $item) {}');

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

    private function repent(string $body): string
    {
        return $this->prophet->repent('/x.php', "<?php\n" . $body)->newContent ?? '';
    }
}
