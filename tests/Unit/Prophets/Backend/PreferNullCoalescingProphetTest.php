<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferNullCoalescingProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferNullCoalescingProphetTest extends TestCase
{
    private PreferNullCoalescingProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PreferNullCoalescingProphet;
    }

    public function test_flags_not_identical_null_self_fallback(): void
    {
        $judgment = $this->judge('$label = $row->label !== null ? $row->label : "untitled";');

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('??', $judgment->warnings[0]->message);
    }

    public function test_flags_null_first_operand(): void
    {
        $judgment = $this->judge('$x = null !== $y ? $y : $d;');

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_flags_identical_null_inverted(): void
    {
        $judgment = $this->judge('$name = $user === null ? "guest" : $user;');

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_flags_isset_self_fallback(): void
    {
        $judgment = $this->judge('$port = isset($config["port"]) ? $config["port"] : 8080;');

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_flags_negated_isset(): void
    {
        $judgment = $this->judge('$port = ! isset($p) ? 8080 : $p;');

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_flags_is_null(): void
    {
        $judgment = $this->judge('$name = is_null($user) ? "guest" : $user;');

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_flags_negated_is_null(): void
    {
        $judgment = $this->judge('$name = ! is_null($user) ? $user : "guest";');

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_does_not_flag_a_real_two_outcome_ternary(): void
    {
        // Different values on each branch — a genuine decision, not a fallback.
        $judgment = $this->judge('$x = $marker->isGroup() ? Group::from($r) : Condition::from($r);');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_when_condition_value_differs_from_branch(): void
    {
        // Tests $a but returns $b — not a self-fallback.
        $judgment = $this->judge('$x = $a !== null ? $b : $c;');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_loose_comparison(): void
    {
        // `!= null` also swallows 0/''/[] — NOT equivalent to ??.
        $judgment = $this->judge('$x = $y != null ? $y : $d;');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_value_with_side_effects(): void
    {
        // Folding two evaluations of a call into one could change behaviour.
        $judgment = $this->judge('$x = $this->load() !== null ? $this->load() : $d;');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_short_ternary(): void
    {
        $judgment = $this->judge('$x = $y ?: $d;');

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
